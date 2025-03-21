<?php
/*
Plugin Name: Gravity Forms Enhanced Tools
Plugin URI: https://wpproatoz.com
Description: Enhanced tools for Gravity Forms including email domain validation and spam filtering
Version: 1.7
Requires at least: 5.2
Requires PHP:      8.0
Author: WPProAtoZ.com
Author URI: https://wpproatoz.com
Text Domain:       wpproatoz-code-snippets
Update URI:        https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Plugin URI: https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Branch: main  // 
*/
// Exit if accessed directly
//////////////////
//plugin updater
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/Ahkonsu/wpproatoz-gf-extras/',
	__FILE__,
	'wpproatoz-gf-extras'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('your-token-here');



/////////////end updater code


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Log function for debugging
function gfet_log($message) {
    if (WP_DEBUG === true) {
        error_log('[GF Enhanced Tools] ' . $message);
    }
}

// Check Gravity Forms dependency
if (!class_exists('GFForms')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Gravity Forms Enhanced Tools requires Gravity Forms to be installed and activated.</p></div>';
    });
    gfet_log('Gravity Forms not found');
    return;
}

// Main plugin class
class GravityForms_Enhanced_Tools {
    private $settings;

    public function __construct() {
        gfet_log('Plugin constructor called');
        try {
            $this->settings = get_option('gf_enhanced_tools_settings', array(
                'email_validator' => 'on',
                'spam_filter' => 'on',
                'restricted_domains' => "gmail.com\nhotmail.com\ntest.com",
                'form_ids' => '152',
                'field_id' => '9',
                'mode' => 'limit',
                'validation_message' => '',
                'entry_block_terms' => '',
                'spam_all_fields' => 'off',
                'hide_validation_message' => 'off'
            ));

            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));

            if ($this->settings['email_validator'] === 'on') {
                $this->init_email_validator();
            }

            if ($this->settings['spam_filter'] === 'on') {
                add_filter('gform_entry_is_spam', array($this, 'spam_filter'), 10, 3);
            }
        } catch (Exception $e) {
            gfet_log('Constructor error: ' . $e->getMessage());
        }
    }

    private function init_email_validator() {
        gfet_log('Initializing email validator');
        try {
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
        } catch (Exception $e) {
            gfet_log('Email validator init error: ' . $e->getMessage());
        }
    }

    public function spam_filter($is_spam, $form, $entry) {
        try {
            $mod_keys = trim(get_option('disallowed_keys'));
            if (empty($mod_keys)) return $is_spam;

            $words = explode("\n", $mod_keys);
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
                    if ($field->type == 'email') {
                        $email = rgar($entry, $id);
                    }
                    if ($field->type == 'name' && $field->nameFormat != 'simple') {
                        $name = rgar($entry, $id . '.3') . " " . rgar($entry, $id . '.6');
                    }
                    if ($field->type == 'text') {
                        $label = $field->label;
                        if ($label == 'First Name' || $label == 'Last Name') {
                            $name = rgar($entry, $id) . " " . ($name ?? '');
                        }
                        elseif ($label == 'Phone') {
                            $phone = rgar($entry, $id);
                        }
                        elseif ($label == 'Company') {
                            $company = rgar($entry, $id);
                        }
                        else {
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

            foreach ((array)$words as $word) {
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
            return $is_spam;
        } catch (Exception $e) {
            gfet_log('Spam filter error: ' . $e->getMessage());
            return $is_spam;
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
        $new_input['restricted_domains'] = sanitize_textarea_field($input['restricted_domains']);
        $new_input['form_ids'] = sanitize_text_field($input['form_ids']);
        $new_input['field_id'] = intval($input['field_id']);
        $new_input['mode'] = in_array($input['mode'], array('ban', 'limit')) ? $input['mode'] : 'limit';
        $new_input['validation_message'] = sanitize_text_field($input['validation_message']);
        $new_input['entry_block_terms'] = sanitize_textarea_field($input['entry_block_terms']);
        $new_input['spam_all_fields'] = isset($input['spam_all_fields']) ? 'on' : 'off';
        $new_input['hide_validation_message'] = isset($input['hide_validation_message']) ? 'on' : 'off';

        if (!empty($new_input['entry_block_terms']) && $new_input['spam_filter'] === 'on') {
            $existing_keys = trim(get_option('disallowed_keys', ''));
            $new_terms = array_filter(explode("\n", $new_input['entry_block_terms']));
            $new_terms = array_map('trim', $new_terms);
            
            $existing_array = $existing_keys ? explode("\n", $existing_keys) : array();
            $combined = array_unique(array_merge($existing_array, $new_terms));
            $updated_keys = implode("\n", array_filter($combined));
            
            update_option('disallowed_keys', $updated_keys);
            gfet_log('Updated disallowed_keys with new terms');
        }

        return $new_input;
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
                                       value="on" <?php checked($settings['email_validator'] ?? 'on', 'on'); ?>>
                                Enable email domain validation
                            </td>
                        </tr>
                        <tr>
                            <th>Form IDs</th>
                            <td>
                                <input type="text" name="gf_enhanced_tools_settings[form_ids]" 
                                       value="<?php echo esc_attr($settings['form_ids'] ?? '152'); ?>">
                                <p class="description">Enter Form IDs separated by commas (e.g., 152, 153, 154)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Field ID</th>
                            <td>
                                <input type="number" name="gf_enhanced_tools_settings[field_id]" 
                                       value="<?php echo esc_attr($settings['field_id'] ?? '9'); ?>">
                                <p class="description">The email field ID to validate (applies to all specified forms)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Validation Mode</th>
                            <td>
                                <select name="gf_enhanced_tools_settings[mode]">
                                    <option value="limit" <?php selected($settings['mode'] ?? 'limit', 'limit'); ?>>Limit to these domains</option>
                                    <option value="ban" <?php selected($settings['mode'] ?? 'limit', 'ban'); ?>>Ban these domains</option>
                                </select>
                                <p class="description">
                                    <strong>Limit:</strong> Only allows emails from the listed domains (e.g., only gmail.com if listed).<br>
                                    <strong>Ban:</strong> Blocks emails from the listed domains (e.g., blocks gmail.com if listed, allows others).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Restricted Domains</th>
                            <td>
                                <textarea name="gf_enhanced_tools_settings[restricted_domains]" rows="5" cols="50"><?php 
                                    echo esc_textarea($settings['restricted_domains'] ?? "gmail.com\nhotmail.com\ntest.com"); 
                                ?></textarea>
                                <p class="description">
                                    Enter one domain per line. In <strong>Limit</strong> mode, only these domains are allowed. 
                                    In <strong>Ban</strong> mode, these domains are blocked.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Validation Message</th>
                            <td>
                                <input type="text" name="gf_enhanced_tools_settings[validation_message]" 
                                       value="<?php echo esc_attr($settings['validation_message'] ?? ''); ?>" 
                                       placeholder="<?php echo esc_attr($default_message); ?>" 
                                       style="width: 100%; max-width: 400px;">
                                <p class="description">Custom message for invalid emails. Use %s for the domain. Leave blank for default: "<?php echo esc_html($default_message); ?>"</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Hide Validation Message</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[hide_validation_message]" 
                                       value="on" <?php checked($settings['hide_validation_message'] ?? 'off', 'on'); ?>>
                                Hide the validation message for restricted domains (form will fail silently)
                            </td>
                        </tr>
                        <tr>
                            <th>Spam Filter</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_filter]" 
                                       value="on" <?php checked($settings['spam_filter'] ?? 'on', 'on'); ?>>
                                Enable spam filtering using WordPress Disallowed Comment Keys
                            </td>
                        </tr>
                        <tr>
                            <th>Check All Fields for Spam</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_all_fields]" 
                                       value="on" <?php checked($settings['spam_all_fields'] ?? 'off', 'on'); ?>>
                                Check all form fields for spam terms (if unchecked, only checks email, name, phone, company, and message fields)
                            </td>
                        </tr>
                        <tr>
                            <th>Entry Block Terms</th>
                            <td>
                                <textarea name="gf_enhanced_tools_settings[entry_block_terms]" rows="5" cols="50"><?php 
                                    echo esc_textarea($settings['entry_block_terms'] ?? ''); 
                                ?></textarea>
                                <p class="description">
                                    Enter one term per line to block form submissions containing these terms. 
                                    These will be added to WordPress Disallowed Comment Keys when saved (if Spam Filter is enabled).
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php elseif ($active_tab === 'docs') : ?>
                <div class="gfet-docs" style="margin-top: 20px; line-height: 1.6; word-wrap: break-word; max-width: 800px;">
                    <?php
                    $readme_file = plugin_dir_path(__FILE__) . 'README.txt';
                    if (file_exists($readme_file)) {
                        $readme_content = file_get_contents($readme_file);
                        echo nl2br(esc_html($readme_content));
                    } else {
                        echo '<p class="error" style="color: red;">README.txt file not found in the plugin directory.</p>';
                    }
                    ?>
                </div>
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
        try {
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
        } catch (Exception $e) {
            gfet_log('Email validation error: ' . $e->getMessage());
            return $validation_result;
        }
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
try {
    new GravityForms_Enhanced_Tools();
    gfet_log('Plugin initialized successfully');
} catch (Exception $e) {
    gfet_log('Plugin initialization failed: ' . $e->getMessage());
}

// Helper function
if (!function_exists('rgar')) {
    function rgar($array, $key) {
        return isset($array[$key]) ? $array[$key] : '';
    }
}
