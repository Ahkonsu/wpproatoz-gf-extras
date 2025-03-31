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
        register_setting('gf_enhanced_tools_group', 'gf_enhanced_tools_settings', array($this, 'sanitize_settings'));
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=gf-enhanced-tools') . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function sanitize_settings($input) {
        $new_input = array();
        $new_input['email_validator'] = isset($input['email_validator']) ? 'on' : 'off';
        $new_input['spam_filter'] = isset($input['spam_filter']) ? 'on' : 'off';
        $new_input['spam_predictor'] = isset($input['spam_predictor']) ? 'on' : 'off';
        $new_input['restricted_domains'] = sanitize_textarea_field($input['restricted_domains']);
        $new_input['form_ids'] = sanitize_text_field($input['form_ids']);
        $new_input['field_id'] = intval($input['field_id']);
        $new_input['mode'] = in_array($input['mode'], array('ban', 'limit')) ? $input['mode'] : 'limit';
        $new_input['validation_message'] = sanitize_text_field($input['validation_message']);
        $new_input['entry_block_terms'] = sanitize_textarea_field($input['entry_block_terms']);
        $new_input['spam_all_fields'] = isset($input['spam_all_fields']) ? 'on' : 'off';
        $new_input['hide_validation_message'] = isset($input['hide_validation_message']) ? 'on' : 'off';
        $new_input['spam_predictor_threshold'] = intval($input['spam_predictor_threshold']) ?: 3;

        if (!empty($new_input['entry_block_terms']) && $new_input['spam_filter'] === 'on') {
            $existing_keys = trim(get_option('disallowed_keys', ''));
            $new_terms = array_filter(explode("\n", $new_input['entry_block_terms']));
            $new_terms = array_map('trim', $new_terms);
            $existing_array = $existing_keys ? explode("\n", $existing_keys) : array();
            $combined = array_unique(array_merge($existing_array, $new_terms));
            $updated_keys = implode("\n", array_filter($combined));
            update_option('disallowed_keys', $updated_keys);
        }

        return $new_input;
    }

    public function settings_page() {
        $settings = $this->settings;
        $default_message = __('Oh no! <strong>%s</strong> email accounts are not eligible for this form.', 'gf-enhanced-tools');
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';

        if (isset($_POST['gfet_action']) && check_admin_referer('gfet_spam_terms_action')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gf_spam_terms';

            if ($_POST['gfet_action'] === 'add_term' && !empty($_POST['new_term']) && isset($_POST['new_term_frequency'])) {
                $new_term = sanitize_text_field($_POST['new_term']);
                $new_frequency = max(1, intval($_POST['new_term_frequency']));
                $form_id = intval($_POST['form_id']);
                $word_count = count(preg_split('/\s+/', trim($new_term)));

                if (strlen($new_term) >= 3 && $form_id > 0 && $word_count <= 5) {
                    $is_phrase = ($word_count > 1) ? 1 : 0;
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO $table_name (term, form_id, is_phrase, frequency, last_seen) 
                             VALUES (%s, %d, %d, %d, NOW()) 
                             ON DUPLICATE KEY UPDATE frequency = %d, last_seen = NOW()",
                            $new_term, $form_id, $is_phrase, $new_frequency, $new_frequency
                        )
                    );
                    echo '<div class="notice notice-success"><p>Term/Phrase added.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Term must be at least 3 characters, Form ID must be valid, and phrases must not exceed 5 words.</p></div>';
                }
            } elseif ($_POST['gfet_action'] === 'edit_term' && !empty($_POST['term_id']) && !empty($_POST['new_frequency'])) {
                $term_id = intval($_POST['term_id']);
                $new_frequency = max(0, intval($_POST['new_frequency']));
                $wpdb->update(
                    $table_name,
                    array('frequency' => $new_frequency),
                    array('id' => $term_id),
                    array('%d'),
                    array('%d')
                );
                echo '<div class="notice notice-success"><p>Term frequency updated.</p></div>';
            } elseif ($_POST['gfet_action'] === 'delete_term' && !empty($_POST['term_id'])) {
                $term_id = intval($_POST['term_id']);
                $wpdb->delete($table_name, array('id' => $term_id), array('%d'));
                echo '<div class="notice notice-success"><p>Term deleted.</p></div>';
            } elseif ($_POST['gfet_action'] === 'update_historical') {
                $form_ids = array_filter(array_map('intval', explode(',', $this->settings['form_ids'])));
                foreach ($form_ids as $form_id) {
                    $this->update_spam_terms_from_historical_entries($form_id);
                }
                echo '<div class="notice notice-success"><p>Historical spam terms updated.</p></div>';
            } elseif ($_POST['gfet_action'] === 'bulk_delete') {
                $bulk_form_ids = isset($_POST['bulk_form_ids']) ? sanitize_text_field($_POST['bulk_form_ids']) : '';
                if (empty($bulk_form_ids)) {
                    $wpdb->query("TRUNCATE TABLE $table_name");
                    echo '<div class="notice notice-success"><p>All spam terms deleted.</p></div>';
                } else {
                    $form_ids = array_filter(array_map('intval', explode(',', $bulk_form_ids)));
                    if (!empty($form_ids)) {
                        $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));
                        $wpdb->query(
                            $wpdb->prepare(
                                "DELETE FROM $table_name WHERE form_id IN ($placeholders)",
                                ...$form_ids
                            )
                        );
                        echo '<div class="notice notice-success"><p>Spam terms for specified Form IDs deleted.</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Invalid Form IDs provided.</p></div>';
                    }
                }
            }
        }

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
                            <th>Form IDs</th>
                            <td>
                                <input type="text" name="gf_enhanced_tools_settings[form_ids]" 
                                       value="<?php echo esc_attr($settings['form_ids']); ?>">
                                <p class="description">Enter Form IDs separated by commas (e.g., 152, 153, 154)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Field ID</th>
                            <td>
                                <input type="number" name="gf_enhanced_tools_settings[field_id]" 
                                       value="<?php echo esc_attr($settings['field_id']); ?>">
                                <p class="description">The email field ID to validate (applies to all specified forms)</p>
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
                                    <br><strong>Lower values (e.g., 1-2):</strong> Catch more spam but may flag legitimate submissions (e.g., "contact us").
                                    <br><strong>Higher values (e.g., 5+):</strong> More conservative, reducing false positives.
                                    <br>Test with your form data; start at 3 and adjust.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php elseif ($active_tab === 'spam-terms') : ?>
                <h2>Spam Terms Database</h2>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('gfet_spam_terms_action'); ?>
                    <input type="hidden" name="gfet_action" value="add_term">
                    <label for="new_term">Add New Spam Term/Phrase:</label>
                    <input type="text" name="new_term" id="new_term" style="width: 300px;" placeholder="e.g., buy now cheap">
                    <label for="new_term_frequency">Initial Frequency:</label>
                    <input type="number" name="new_term_frequency" id="new_term_frequency" value="3" min="1" style="width: 60px;">
                    <label for="form_id">Form ID:</label>
                    <input type="number" name="form_id" id="form_id" style="width: 60px;" placeholder="e.g., 152">
                    <input type="submit" value="Add Term" class="button">
                    <p class="description">Enter a term or phrase (min 3 chars, max 5 words), set frequency, and specify Form ID.</p>
                </form>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('gfet_spam_terms_action'); ?>
                    <input type="hidden" name="gfet_action" value="update_historical">
                    <input type="submit" value="Update Historical Spam Terms" class="button">
                    <p class="description">Update terms/phrases from historical spam entries for configured forms.</p>
                </form>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('gfet_spam_terms_action'); ?>
                    <input type="hidden" name="gfet_action" value="bulk_delete">
                    <label for="bulk_form_ids">Bulk Delete Terms (Optional Form IDs):</label>
                    <input type="text" name="bulk_form_ids" id="bulk_form_ids" style="width: 200px;" placeholder="e.g., 152, 153">
                    <input type="submit" value="Bulk Delete Terms" class="button" onclick="return confirm('Are you sure you want to delete these spam terms? Leave Form IDs blank to delete all terms.');">
                    <p class="description">Enter Form IDs (comma-separated) to delete terms for specific forms, or leave blank to delete all terms.</p>
                </form>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'gf_spam_terms';

                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $terms = $wpdb->get_results("SELECT id, term, form_id, is_phrase, frequency, last_seen FROM $table_name ORDER BY form_id, frequency DESC", ARRAY_A);
                    if ($terms) {
                        echo '<table class="widefat"><thead><tr><th>Term/Phrase</th><th>Form ID</th><th>Type</th><th>Frequency</th><th>Last Seen</th><th>Actions</th></tr></thead><tbody>';
                        foreach ($terms as $term) {
                            echo '<tr>';
                            echo '<td>' . esc_html($term['term']) . '</td>';
                            echo '<td>' . esc_html($term['form_id']) . '</td>';
                            echo '<td>' . ($term['is_phrase'] ? 'Phrase' : 'Word') . '</td>';
                            echo '<td>' . esc_html($term['frequency']) . '</td>';
                            echo '<td>' . esc_html($term['last_seen']) . '</td>';
                            echo '<td>';
                            echo '<form method="post" style="display:inline;">';
                            wp_nonce_field('gfet_spam_terms_action');
                            echo '<input type="hidden" name="gfet_action" value="edit_term">';
                            echo '<input type="hidden" name="term_id" value="' . esc_attr($term['id']) . '">';
                            echo '<input type="number" name="new_frequency" value="' . esc_attr($term['frequency']) . '" min="0" style="width:60px;">';
                            echo '<input type="submit" value="Update" class="button">';
                            echo '</form>';
                            echo ' | ';
                            echo '<form method="post" style="display:inline;">';
                            wp_nonce_field('gfet_spam_terms_action');
                            echo '<input type="hidden" name="gfet_action" value="delete_term">';
                            echo '<input type="hidden" name="term_id" value="' . esc_attr($term['id']) . '">';
                            echo '<input type="submit" value="Delete" class="button" onclick="return confirm(\'Are you sure you want to delete this term/phrase?\');">';
                            echo '</form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<p>No spam terms or phrases recorded yet.</p>';
                    }
                } else {
                    echo '<p class="error" style="color: red;">Spam terms table not found. Please reactivate the plugin to create it.</p>';
                }
                ?>
            <?php elseif ($active_tab === 'docs') : ?>
                <?php
                $plugin_dir = plugin_dir_path(__FILE__);
                $readme_file = $plugin_dir . 'documentation.txt';
                if (file_exists($readme_file)) {
                    echo '<pre>' . esc_html(file_get_contents($readme_file)) . '</pre>';
                } else {
                    echo '<p class="error" style="color: red;">documentation.txt file not found.</p>';
                }
                ?>
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