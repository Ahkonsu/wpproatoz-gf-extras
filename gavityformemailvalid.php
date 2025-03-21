<?php
/*
Plugin Name: Gravity Forms Enhanced Tools
Plugin URI: https://wpproatoz.com
Description: Enhanced tools for Gravity Forms including email domain validation and spam filtering
Version: 1.0
Requires at least: 5.2
Requires PHP:      7.4
Author: WPProAtoZ.com
Author URI: https://wpproatoz.com
Text Domain:       wpproatoz-code-snippets
Update URI:        https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Plugin URI: https://github.com/Ahkonsu/wpproatoz-gf-extras/releases
GitHub Branch: main  // 
*/

//////////////////
//plugin uopdater
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

class GravityForms_Enhanced_Tools {
    private $settings;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->settings = get_option('gf_enhanced_tools_settings', array(
            'email_validator' => 'on',
            'spam_filter' => 'on'
        ));
        
        if ($this->settings['email_validator'] === 'on') {
            $this->init_email_validator();
        }
        
        if ($this->settings['spam_filter'] === 'on') {
            add_filter('gform_entry_is_spam', array($this, 'nhs_gforms_content_blacklist'), 10, 3);
        }
    }

    private function init_email_validator() {
        new GW_Email_Domain_Validator(array(
            'form_id'            => 1,
            'field_id'           => 3,
            'domains'            => array('mc-2.com'),
            'validation_message' => __('Oh no! <strong>%s</strong> email accounts are not eligible for this form.'),
            'mode'               => 'limit',
        ));
    }

    // Email Domain Validator Class
    private function get_email_validator_class() {
        return new class extends GW_Email_Domain_Validator {
            function __construct($args) {
                $this->_args = wp_parse_args($args, array(
                    'form_id'            => false,
                    'field_id'           => false,
                    'domains'            => false,
                    'validation_message' => __('Sorry, <strong>%s</strong> email accounts are not eligible for this form.'),
                    'mode'               => 'ban',
                ));

                if ($this->_args['field_id'] && !is_array($this->_args['field_id'])) {
                    $this->_args['field_id'] = array($this->_args['field_id']);
                }

                $form_filter = $this->_args['form_id'] ? "_{$this->_args['form_id']}" : '';
                add_filter("gform_validation{$form_filter}", array($this, 'validate'));
            }

            function validate($validation_result) {
                $form = $validation_result['form'];
                foreach ($form['fields'] as &$field) {
                    if (RGFormsModel::get_input_type($field) != 'email') continue;
                    if ($this->_args['field_id'] && !in_array($field['id'], $this->_args['field_id'])) continue;
                    
                    $page_number = GFFormDisplay::get_source_page($form['id']);
                    if ($page_number > 0 && $field->pageNumber != $page_number) continue;
                    if (GFFormsModel::is_field_hidden($form, $field, array())) continue;

                    $domain = $this->get_email_domain($field);
                    if ($this->is_domain_valid($domain) || empty($domain)) continue;

                    $validation_result['is_valid'] = false;
                    $field['failed_validation'] = true;
                    $field['validation_message'] = sprintf($this->_args['validation_message'], $domain);
                }
                $validation_result['form'] = $form;
                return $validation_result;
            }

            function get_email_domain($field) {
                $email = explode('@', rgpost("input_{$field['id']}"));
                return trim(rgar($email, 1));
            }

            function is_domain_valid($domain) {
                $mode = $this->_args['mode'];
                $domain = strtolower($domain);
                foreach ($this->_args['domains'] as $_domain) {
                    $_domain = strtolower($_domain);
                    $full_match = $domain == $_domain;
                    $suffix_match = strpos($_domain, '.') === 0 && $this->string_ends_with($domain, $_domain);
                    $has_match = $full_match || $suffix_match;

                    if ($mode == 'ban' && $has_match) return false;
                    elseif ($mode == 'limit' && $has_match) return true;
                }
                return $mode == 'limit' ? false : true;
            }

            function string_ends_with($string, $text) {
                $length = strlen($string);
                $text_length = strlen($text);
                if ($text_length > $length) return false;
                return substr_compare($string, $text, $length - $text_length, $text_length) === 0;
            }
        };
    }

    // Spam Filter Function
    public function nhs_gforms_content_blacklist($is_spam, $form, $entry) {
        $mod_keys = trim(get_option('blacklist_keys'));
        if ('' === $mod_keys) return $is_spam;

        $words = explode("\n", $mod_keys);
        foreach ($form['fields'] as $field) {
            $id = $field['id'];
            if ($field['type'] == 'email') {
                $email = rgar($entry, $id);
            }
            if ($field['type'] == 'name' && $field['nameFormat'] != 'simple') {
                $name = rgar($entry, $id . '.3') . " " . rgar($entry, $id . '.6');
            }
            if ($field['type'] == 'text') {
                $label = $field['label'];
                if ($label == 'First Name' || $label == 'Last Name') {
                    $name = rgar($entry, $id) . " " . ($name ?? '');
                }
                elseif ($label == 'Phone') $phone = rgar($entry, $id);
                elseif ($label == 'Company') $company = rgar($entry, $id);
                else $text = rgar($entry, $id);
            }
            if ($field['type'] == 'textarea') {
                $message = rgar($entry, $id);
                $message_without_html = wp_strip_all_tags($message);
            }
        }

        foreach ((array)$words as $word) {
            $word = trim($word);
            if (empty($word)) continue;
            $word = preg_quote($word, '#');
            $pattern = "#$word#i";
            
            if (preg_match($pattern, $name ?? '') || preg_match($pattern, $email ?? '') || 
                preg_match($pattern, $phone ?? '') || preg_match($pattern, $company ?? '') || 
                preg_match($pattern, $text ?? '') || preg_match($pattern, $message ?? '') || 
                preg_match($pattern, $message_without_html ?? '')) {
                return true;
            }
        }
        return $is_spam;
    }

    // Admin Page
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
        register_setting('gf_enhanced_tools_group', 'gf_enhanced_tools_settings');
    }

    public function settings_page() {
        $settings = $this->settings;
        ?>
        <div class="wrap">
            <h1>Gravity Forms Enhanced Tools Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('gf_enhanced_tools_group'); ?>
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
                        <th>Spam Filter</th>
                        <td>
                            <input type="checkbox" name="gf_enhanced_tools_settings[spam_filter]" 
                                   value="on" <?php checked($settings['spam_filter'], 'on'); ?>>
                            Enable spam filtering using WordPress Disallowed Comment Keys
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new GravityForms_Enhanced_Tools();