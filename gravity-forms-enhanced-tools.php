<?php
/*
Plugin Name: WPProAtoZ Enhanced Tools for Gravity Forms
Plugin URI: https://wpproatoz.com
Description: Enhanced Tools for Gravity Forms is a WordPress plugin that extends Gravity Forms with advanced email domain validation, spam filtering, and spam prediction using past submissions. Restrict or allow submissions by email domain, block spam with Disallowed Comment Keys, and predict spam with a custom terms database.
Version: 2.1
Requires at least: 6.0
Requires PHP: 8.0
Author: WPProAtoZ.com
Author URI: https://wpproatoz.com
Text Domain: gravity-forms-enhanced-tools
Update URI: https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Plugin URI: https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Branch: main
Requires Plugins: gravityforms
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin updater
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Ahkonsu/wpproatoz-gf-extras/',
    __FILE__,
    'wpproatoz-gf-extras'
);

$myUpdateChecker->setBranch('main');

// Check Gravity Forms dependency
if (!class_exists('GFForms')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Gravity Forms Enhanced Tools requires Gravity Forms to be installed and activated.</p></div>';
    });
    return;
}

// Main plugin class
class GravityForms_Enhanced_Tools {
    private $settings;
    private $version = '2.1'; // Updated to 2.1
    public $field_map;

    public function __construct() {
        $default_settings = array(
            'email_validator' => 'on',
            'spam_filter' => 'on',
            'spam_predictor' => 'off',
            'restricted_domains' => "gmail.com\nhotmail.com\ntest.com",
            'form_ids' => '152',
            'field_id' => '9',
            'mode' => 'limit',
            'validation_message' => '',
            'entry_block_terms' => '',
            'spam_all_fields' => 'off',
            'hide_validation_message' => 'off',
            'spam_predictor_threshold' => 3
        );

        $this->settings = wp_parse_args(get_option('gf_enhanced_tools_settings', array()), $default_settings);

        register_activation_hook(__FILE__, array($this, 'create_spam_terms_table'));
        add_action('plugins_loaded', array($this, 'check_and_update_table'));

        $this->field_map = json_decode(get_option('gf_enhanced_tools_field_ids_map', '{}'), true);


        if (!wp_next_scheduled('gfet_cleanup_spam_terms')) {
            wp_schedule_event(time(), 'daily', 'gfet_cleanup_spam_terms');
        }
        add_action('gfet_cleanup_spam_terms', array($this, 'cleanup_spam_terms'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));

        if ($this->settings['email_validator'] === 'on') {
            $this->init_email_validator();
        }

        if ($this->settings['spam_filter'] === 'on') {
            add_filter('gform_entry_is_spam', array($this, 'spam_filter'), 10, 3);
        }

        if ($this->settings['spam_predictor'] === 'on') {
            add_action('gform_after_submission', array($this, 'collect_spam_terms'), 10, 2);
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_gfet_get_form_fields', array($this, 'ajax_get_form_fields'));
        
    }

    public function enqueue_admin_assets($hook) {
        // print 'Admin hook: ' . $hook;
        // exit;
        
        // Ensure we're only loading on the settings screen
        if (($hook !== 'toplevel_page_gf-enhanced-tools') && ($hook !== 'settings_page_gf-enhanced-tools')) {
            return;
        }
    
        // Select2
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    
        // Your custom JS
        wp_enqueue_script(
            'gfet-admin-fields',
            plugin_dir_url(__FILE__) . 'assets/js/admin_fields.js',
            ['jquery', 'select2'],
            null,
            true
        );
    
        wp_localize_script('gfet-admin-fields', 'gfetAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gfet_ajax')
        ]);
    
        // Your custom CSS
        wp_enqueue_style(
            'gfet-admin-fields',
            plugin_dir_url(__FILE__) . 'assets/css/admin_fields.css'
        );
    }

    public function ajax_get_form_fields() {
        check_ajax_referer('gfet_ajax', 'nonce');
        $form_id = absint($_POST['form_id']);
        $form = GFAPI::get_form($form_id);
        $fields = [];
    
        foreach ($form['fields'] as $field) {
            if (!empty($field->label)) {
                $fields[] = [
                    'id' => $field->id,
                    'label' => $field->label,
                ];
            }
        }
    
        wp_send_json_success($fields);
    }

    public function create_spam_terms_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            term VARCHAR(255) NOT NULL,
            form_id INT NOT NULL,
            is_phrase TINYINT(1) NOT NULL DEFAULT 0,
            frequency INT NOT NULL DEFAULT 1,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY term_form (term, form_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option('gfet_version', $this->version);
    }

    public function check_and_update_table() {
        global $wpdb;
        $installed_version = get_option('gfet_version', '0');
        $table_name = $wpdb->prefix . 'gf_spam_terms';

        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'is_phrase'");
        $needs_update = version_compare($installed_version, $this->version, '<') || empty($columns);

        if ($needs_update) {
            $this->create_spam_terms_table();

            if (!get_option('gfet_migration_completed')) {
                $null_form_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE form_id IS NULL OR form_id = 0");
                if ($null_form_rows > 0) {
                    $form_ids = array_filter(array_map('intval', explode(',', $this->settings['form_ids'])));
                    $default_form_id = !empty($form_ids) ? $form_ids[0] : 152;
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $table_name SET form_id = %d WHERE form_id IS NULL OR form_id = 0",
                            $default_form_id
                        )
                    );
                }

                $null_phrase_rows = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_phrase IS NULL");
                if ($null_phrase_rows > 0) {
                    $wpdb->query("UPDATE $table_name SET is_phrase = 0 WHERE is_phrase IS NULL");
                }

                update_option('gfet_migration_completed', true);
            }
        }
    }

    public function cleanup_spam_terms() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $ninety_days_ago = date('Y-m-d H:i:s', strtotime('-90 days'));
        $wpdb->query(
            $wpdb->prepare("DELETE FROM $table_name WHERE last_seen < %s", $ninety_days_ago)
        );
    }

    private function init_email_validator() {
        $domains = array_filter(explode("\n", $this->settings['restricted_domains']));
        $domains = array_map('trim', $domains);
        $form_ids = array_filter(array_map('intval', explode(',', $this->settings['form_ids'])));
        $validation_message = !empty($this->settings['validation_message']) 
            ? $this->settings['validation_message'] 
            : __('Oh no! <strong>%s</strong> email accounts are not eligible for this form.', 'gf-enhanced-tools');
        $hide_validation_message = $this->settings['hide_validation_message'] ?? 'off';

        foreach ($form_ids as $form_id) {
            $validator = new GFET_Email_Validator(array(
                'form_id'            => $form_id,
                'field_id'           => intval($this->settings['field_id']),
                'domains'            => $domains,
                'validation_message' => $validation_message,
                'mode'               => $this->settings['mode'],
                'hide_validation_message' => $hide_validation_message
            ));
        }
    }

    public function get_spam_entries($form_id) {
        $all_entries = array();
        $page_size = 100;
        $offset = 0;

        $search_criteria = array(
            'status' => 'spam',
        );

        do {
            $paging = array('offset' => $offset, 'page_size' => $page_size);
            $entries = GFAPI::get_entries($form_id, $search_criteria, null, $paging);
            $all_entries = array_merge($all_entries, $entries);
            $offset += $page_size;
        } while (!empty($entries) && count($entries) == $page_size);

        return $all_entries;
    }

    public function spam_filter($is_spam, $form, $entry) {
        $mod_keys = trim(get_option('disallowed_keys'));
        $words = !empty($mod_keys) ? explode("\n", $mod_keys) : array();
        $fields_to_check = array();

        if ($this->settings['spam_all_fields'] === 'on') {
            foreach ($form['fields'] as $field) {
                $id = $field->id;
                $value = rgar($entry, $id);
                if ($field->type === 'textarea') {
                    $value = wp_strip_all_tags($value);
                }
                if (!empty($value)) {
                    $fields_to_check[] = $value;
                }
            }
        } else {
            $email = '';
            $name = '';
            $phone = '';
            $company = '';
            $text = '';
            $message = '';
            $message_without_html = '';

            foreach ($form['fields'] as $field) {
                $id = $field->id;
                if ($field->type == 'email') $email = rgar($entry, $id);
                if ($field->type == 'name' && $field->nameFormat != 'simple') {
                    $name = rgar($entry, $id . '.3') . " " . rgar($entry, $id . '.6');
                }
                if ($field->type == 'text') {
                    $label = $field->label;
                    if ($label == 'First Name' || $label == 'Last Name') {
                        $name = rgar($entry, $id) . " " . ($name ?? '');
                    } elseif ($label == 'Phone') {
                        $phone = rgar($entry, $id);
                    } elseif ($label == 'Company') {
                        $company = rgar($entry, $id);
                    } else {
                        $text = rgar($entry, $id);
                    }
                }
                if ($field->type == 'textarea') {
                    $message = rgar($entry, $id);
                    $message_without_html = wp_strip_all_tags($message);
                }
            }
            $fields_to_check = array($name, $email, $phone, $company, $text, $message, $message_without_html);
        }

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            $word = preg_quote($word, '#');
            $pattern = "#$word#i";
            foreach ($fields_to_check as $field_value) {
                if ($field_value && preg_match($pattern, $field_value)) {
                    return true;
                }
            }
        }

        if ($this->settings['spam_predictor'] === 'on') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gf_spam_terms';
            $threshold = intval($this->settings['spam_predictor_threshold']);
            $form_id = $form['id'];

            $spam_terms = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT term, is_phrase FROM $table_name WHERE form_id = %d AND frequency >= %d",
                    $form_id,
                    $threshold
                ),
                ARRAY_A
            );

            foreach ($spam_terms as $term_data) {
                $term = $term_data['term'];
                $is_phrase = $term_data['is_phrase'];
                $pattern = $is_phrase ? "#\b" . preg_quote(trim($term), '#') . "\b#i" : "#\b" . preg_quote(trim($term), '#') . "\b#i";
                foreach ($fields_to_check as $field_value) {
                    if ($field_value && preg_match($pattern, $field_value)) {
                        return true;
                    }
                }
            }
        }

        return $is_spam;
    }

    public function collect_spam_terms($entry, $form) {
        if (!isset($entry['is_spam']) || $entry['is_spam'] != 1) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $form_id = $form['id'];
        $fields_to_check = array();

        foreach ($form['fields'] as $field) {
            $id = $field->id;
            $value = rgar($entry, $id);
            if ($field->type === 'textarea') {
                $value = wp_strip_all_tags($value);
            }
            if (!empty($value)) {
                $fields_to_check[] = $value;
            }
        }

        foreach ($fields_to_check as $field_value) {
            $words = preg_split('/\s+/', strtolower($field_value));
            $words = array_filter($words);

            foreach ($words as $word) {
                if (strlen($word) < 3) continue;
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT INTO $table_name (term, form_id, is_phrase, frequency, last_seen) 
                         VALUES (%s, %d, 0, 1, NOW()) 
                         ON DUPLICATE KEY UPDATE frequency = frequency + 1, last_seen = NOW()",
                        $word,
                        $form_id
                    )
                );
            }

            for ($length = 2; $length <= 5; $length++) {
                for ($i = 0; $i <= count($words) - $length; $i++) {
                    $phrase = implode(' ', array_slice($words, $i, $length));
                    if (strlen($phrase) >= 3) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT INTO $table_name (term, form_id, is_phrase, frequency, last_seen) 
                                 VALUES (%s, %d, 1, 1, NOW()) 
                                 ON DUPLICATE KEY UPDATE frequency = frequency + 1, last_seen = NOW()",
                                $phrase,
                                $form_id
                            )
                        );
                    }
                }
            }
        }

        $this->update_spam_terms_from_historical_entries($form_id);
    }

    public function update_spam_terms_from_historical_entries($form_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $spam_entries = $this->get_spam_entries($form_id);

        foreach ($spam_entries as $entry) {
            $fields_to_check = array();
            $form = GFAPI::get_form($entry['form_id']);
            foreach ($form['fields'] as $field) {
                $id = $field->id;
                $value = rgar($entry, $id);
                if ($field->type === 'textarea') {
                    $value = wp_strip_all_tags($value);
                }
                if (!empty($value)) {
                    $fields_to_check[] = $value;
                }
            }

            foreach ($fields_to_check as $field_value) {
                $words = preg_split('/\s+/', strtolower($field_value));
                $words = array_filter($words);

                foreach ($words as $word) {
                    if (strlen($word) < 3) continue;
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO $table_name (term, form_id, is_phrase, frequency, last_seen) 
                             VALUES (%s, %d, 0, 1, NOW()) 
                             ON DUPLICATE KEY UPDATE frequency = frequency + 1, last_seen = NOW()",
                            $word,
                            $form_id
                        )
                    );
                }

                for ($length = 2; $length <= 5; $length++) {
                    for ($i = 0; $i <= count($words) - $length; $i++) {
                        $phrase = implode(' ', array_slice($words, $i, $length));
                        if (strlen($phrase) >= 3) {
                            $wpdb->query(
                                $wpdb->prepare(
                                    "INSERT INTO $table_name (term, form_id, is_phrase, frequency, last_seen) 
                                     VALUES (%s, %d, 1, 1, NOW()) 
                                     ON DUPLICATE KEY UPDATE frequency = frequency + 1, last_seen = NOW()",
                                    $phrase,
                                    $form_id
                                )
                            );
                        }
                    }
                }
            }
        }
    }

    public function add_admin_menu() {
        add_options_page(
            'Gravity Forms Enhanced Tools',
            'GF Enhanced Tools',
            'manage_options',
            'gf-enhanced-tools',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        // Register the main plugin settings array
        register_setting(
            'gf_enhanced_tools_group',
            'gf_enhanced_tools_settings',
            array($this, 'sanitize_settings')
        );
    
        // Register the form_id => field_id JSON map
        register_setting(
            'gf_enhanced_tools_group',
            'gf_enhanced_tools_field_ids_map',
            array($this, 'sanitize_field_map')
        );
    }
    
    /**
     * Sanitize the main plugin settings.
     */
    public function sanitize_settings($input) {
        $output = array();
    
        $output['email_validator']           = isset($input['email_validator']) ? 'on' : 'off';
        $output['spam_filter']               = isset($input['spam_filter']) ? 'on' : 'off';
        $output['spam_predictor']            = isset($input['spam_predictor']) ? 'on' : 'off';
        $output['spam_all_fields']           = isset($input['spam_all_fields']) ? 'on' : 'off';
        $output['hide_validation_message']   = isset($input['hide_validation_message']) ? 'on' : 'off';
    
        $output['form_ids']                  = sanitize_text_field($input['form_ids']);
        $output['mode']                      = in_array($input['mode'], ['limit', 'ban']) ? $input['mode'] : 'limit';
        $output['validation_message']        = sanitize_text_field($input['validation_message']);
        $output['restricted_domains']        = sanitize_textarea_field($input['restricted_domains']);
        $output['entry_block_terms']         = sanitize_textarea_field($input['entry_block_terms']);
        $output['spam_predictor_threshold']  = isset($input['spam_predictor_threshold']) ? max(1, intval($input['spam_predictor_threshold'])) : 3;
    
        return $output;
    }
    
    /**
     * Sanitize the form_id => field_id JSON map.
     */


    public function sanitize_field_map($input) {
        error_log('[GFET] Raw input to sanitize_field_map: ' . print_r($input, true));
    
        $decoded = json_decode(stripslashes($input), true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[GFET] JSON error: ' . json_last_error_msg());
            return '{}';
        }
    
        return wp_json_encode(array_map('intval', $decoded));
    }



    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=gf-enhanced-tools') . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function settings_page() {
        $settings = $this->settings;
        $default_message = __('Oh no! <strong>%s</strong> email accounts are not eligible for this form.', 'gf-enhanced-tools');
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
        ?>
        <div class="wrap">
            <h1>Gravity Forms Enhanced Tools</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=gf-enhanced-tools&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=gf-enhanced-tools&tab=spam-terms" class="nav-tab <?php echo $active_tab === 'spam-terms' ? 'nav-tab-active' : ''; ?>">Spam Terms</a>
                <a href="?page=gf-enhanced-tools&tab=docs" class="nav-tab <?php echo $active_tab === 'docs' ? 'nav-tab-active' : ''; ?>">Documentation</a>
            </h2>
            <?php if ($active_tab === 'settings') : ?>
                <form method="post" action="options.php">
                    <?php 
                    settings_fields('gf_enhanced_tools_group'); 
                    do_settings_sections('gf_enhanced_tools_group');
                    ?>
    
                    <table class="form-table">
                        <tr>
                            <th>Email Domain Validator</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[email_validator]" 
                                       value="on" <?php checked($settings['email_validator'], 'on'); ?>>
                                Enable email domain validation
                            </td>
                        </tr>
    
                        <tr>
                            <th>Form & Field Mapping</th>
                            <td>
                                <select id="gfet_form_selector" multiple="multiple" style="width:100%;">
                                    <?php
                                    $saved_form_ids = explode(',', $settings['form_ids'] ?? '');
                                    foreach (GFAPI::get_forms(true) as $form) {
                                        $selected = in_array($form['id'], $saved_form_ids) ? 'selected' : '';
                                        echo "<option value='{$form['id']}' {$selected}>{$form['title']} (ID {$form['id']})</option>";
                                    }
                                    ?>
                                </select>
    
                                <input type="hidden" name="gf_enhanced_tools_settings[form_ids]" id="gfet_form_ids"
                                       value="<?php echo esc_attr($settings['form_ids']); ?>">
    
                                <input type="hidden" name="gf_enhanced_tools_field_ids_map" id="gfet_field_ids"
                                       value='<?php echo esc_attr(get_option('gf_enhanced_tools_field_ids_map', '{}')); ?>'>
    
                                <div id="gfet_field_selector_container"></div>
                                <p class="description">Select one or more forms and then choose the field used for email validation in each.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Validation Mode</th>
                            <td>
                                <select name="gf_enhanced_tools_settings[mode]">
                                    <option value="limit" <?php selected($settings['mode'], 'limit'); ?>>Limit to these domains</option>
                                    <option value="ban" <?php selected($settings['mode'], 'ban'); ?>>Ban these domains</option>
                                </select>
                                <p class="description">
                                    <strong>Limit:</strong> Only allows emails from the listed domains.<br>
                                    <strong>Ban:</strong> Blocks emails from the listed domains.
                                </p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Restricted Domains</th>
                            <td>
                                <textarea name="gf_enhanced_tools_settings[restricted_domains]" rows="5" cols="50"><?php 
                                    echo esc_textarea($settings['restricted_domains']); 
                                ?></textarea>
                                <p class="description">Enter one domain per line.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Validation Message</th>
                            <td>
                                <input type="text" name="gf_enhanced_tools_settings[validation_message]" 
                                       value="<?php echo esc_attr($settings['validation_message']); ?>" 
                                       placeholder="<?php echo esc_attr($default_message); ?>" 
                                       style="width: 100%; max-width: 400px;">
                                <p class="description">Custom message for invalid emails. Use %s for the domain.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Hide Validation Message</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[hide_validation_message]" 
                                       value="on" <?php checked($settings['hide_validation_message'], 'on'); ?>>
                                Hide the validation message for restricted domains
                            </td>
                        </tr>
    
                        <tr>
                            <th>Spam Filter</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_filter]" 
                                       value="on" <?php checked($settings['spam_filter'], 'on'); ?>>
                                Enable spam filtering using WordPress Disallowed Comment Keys
                            </td>
                        </tr>
    
                        <tr>
                            <th>Check All Fields for Spam</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_all_fields]" 
                                       value="on" <?php checked($settings['spam_all_fields'], 'on'); ?>>
                                Check all form fields for spam terms
                            </td>
                        </tr>
    
                        <tr>
                            <th>Entry Block Terms</th>
                            <td>
                                <textarea name="gf_enhanced_tools_settings[entry_block_terms]" rows="5" cols="50"><?php 
                                    echo esc_textarea($settings['entry_block_terms']); 
                                ?></textarea>
                                <p class="description">Enter one term per line to block submissions.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Spam Predictor</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_predictor]" 
                                       value="on" <?php checked($settings['spam_predictor'], 'on'); ?>>
                                Enable spam prediction using past spam submissions
                            </td>
                        </tr>
    
                        <tr>
                            <th>Spam Predictor Threshold</th>
                            <td>
                                <input type="number" name="gf_enhanced_tools_settings[spam_predictor_threshold]" 
                                       value="<?php echo esc_attr($settings['spam_predictor_threshold']); ?>" 
                                       min="1">
                                <p class="description">
                                    Minimum frequency a term/phrase must appear in spam submissions to be blocked (default: 3). 
                                    <br><strong>Lower values (e.g., 1–2):</strong> Catches more spam but may flag legit content.<br>
                                    <strong>Higher values (e.g., 5+):</strong> More conservative, fewer false positives.
                                </p>
                            </td>
                        </tr>
                    </table>
    
                    <?php submit_button(); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

}

// Email Validator Class
class GFET_Email_Validator {
    private $args;

    public function __construct($args) {
        $this->args = wp_parse_args($args, array(
            'form_id'            => false,
            'field_id'           => false,
            'domains'            => false,
            'validation_message' => __('Sorry, <strong>%s</strong> email accounts are not eligible for this form.', 'gf-enhanced-tools'),
            'mode'               => 'ban',
            'hide_validation_message' => 'off'
        ));

        if ($this->args['field_id'] && !is_array($this->args['field_id'])) {
            $this->args['field_id'] = array($this->args['field_id']);
        }

        $form_filter = $this->args['form_id'] ? "_{$this->args['form_id']}" : '';
        add_filter("gform_validation{$form_filter}", array($this, 'validate'));
    }

    public function validate($validation_result) {
        $form = $validation_result['form'];
        foreach ($form['fields'] as &$field) {
            if (!method_exists('RGFormsModel', 'get_input_type') || 
                RGFormsModel::get_input_type($field) != 'email') continue;
            if ($this->args['field_id'] && !in_array($field->id, $this->args['field_id'])) continue;
            
            $page_number = class_exists('GFFormDisplay') ? GFFormDisplay::get_source_page($form['id']) : 0;
            if ($page_number > 0 && $field->pageNumber != $page_number) continue;
            if (method_exists('GFFormsModel', 'is_field_hidden') && 
                GFFormsModel::is_field_hidden($form, $field, array())) continue;

            $domain = $this->get_email_domain($field);
            if ($this->is_domain_valid($domain) || empty($domain)) continue;

            $validation_result['is_valid'] = false;
            $field['failed_validation'] = true;
            if ($this->args['hide_validation_message'] !== 'on') {
                $field['validation_message'] = sprintf($this->args['validation_message'], $domain);
            }
        }
        $validation_result['form'] = $form;
        return $validation_result;
    }

    private function get_email_domain($field) {
        $email = explode('@', rgpost("input_{$field->id}"));
        return trim(rgar($email, 1));
    }

    private function is_domain_valid($domain) {
        $mode = $this->args['mode'];
        $domain = strtolower($domain);
        foreach ((array)$this->args['domains'] as $_domain) {
            $_domain = strtolower($_domain);
            $full_match = $domain == $_domain;
            $suffix_match = strpos($_domain, '.') === 0 && $this->string_ends_with($domain, $_domain);
            if ($mode == 'ban' && ($full_match || $suffix_match)) return false;
            if ($mode == 'limit' && ($full_match || $suffix_match)) return true;
        }
        return $mode == 'limit' ? false : true;
    }

    private function string_ends_with($string, $text) {
        $length = strlen($string);
        $text_length = strlen($text);
        if ($text_length > $length) return false;
        return substr_compare($string, $text, $length - $text_length, $text_length) === 0;
    }
}

// Initialize plugin
new GravityForms_Enhanced_Tools();

// Helper function
if (!function_exists('rgar')) {
    function rgar($array, $key) {
        return isset($array[$key]) ? $array[$key] : '';
    }
}