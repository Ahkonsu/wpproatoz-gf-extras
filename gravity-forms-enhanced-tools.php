<?php
/*
Plugin Name: Gravity Forms Enhanced Tools from WPProAtoZ 
Plugin URI: https://wpproatoz.com
Description: Enhanced Tools for Gravity Forms is a WordPress plugin that extends Gravity Forms with advanced email domain validation, spam filtering, minimum character length enforcement, and spam prediction using past submissions. Restrict or allow submissions by email domain, block spam with Disallowed Comment Keys, enforce text field length, and predict spam with a custom terms database.
Version: 2.3
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

// Include separated classes and helpers
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-email-validator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-spam-terms.php';

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
    private $version = '2.2';
    public $field_map;
    private $common_words;
    private $admin;
    private $spam_terms;

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
            'spam_predictor_threshold' => 3,
            'min_length_enabled' => 'off',
            'min_length' => 5
        );

        $this->settings = wp_parse_args(get_option('gf_enhanced_tools_settings', array()), $default_settings);
        $this->common_words = [
            'the', 'for', 'you', 'have', 'and', 'to', 'is', 'in', 'it', 'of',
            'that', 'this', 'on', 'with', 'are', 'be', 'at', 'by', 'not', 'or',
            'was', 'but', 'from', 'they', 'we', 'an', 'he', 'she', 'as', 'do'
        ];

        register_activation_hook(__FILE__, array($this, 'create_spam_terms_table'));
        add_action('plugins_loaded', array($this, 'check_and_update_table'));

        $this->field_map = json_decode(get_option('gf_enhanced_tools_field_ids_map', '{}'), true);

        if (!wp_next_scheduled('gfet_cleanup_spam_terms')) {
            wp_schedule_event(time(), 'daily', 'gfet_cleanup_spam_terms');
        }
        add_action('gfet_cleanup_spam_terms', array($this, 'cleanup_spam_terms'));

        // Instantiate spam terms class first, then admin class with spam_terms reference
        $this->spam_terms = new GFET_Spam_Terms($this->settings, $this->common_words);
        $this->admin = new GFET_Admin($this->settings, $this->common_words, $this->spam_terms);

        if ($this->settings['email_validator'] === 'on') {
            $this->init_email_validator();
        }

        if ($this->settings['min_length_enabled'] === 'on') {
            add_filter('gform_validation', array($this, 'validate_min_length'));
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
        error_log("GFET: Plugin activated, version set to {$this->version}");
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
        if (!class_exists('GFET_Email_Validator')) {
            error_log('GFET Error: GFET_Email_Validator class not loaded in init_email_validator');
            return;
        }

        $domains = array_filter(explode("\n", $this->settings['restricted_domains']));
        $domains = array_map('trim', $domains);
        $form_ids = array_filter(array_map('intval', explode(',', $this->settings['form_ids'])));
        $validation_message = !empty($this->settings['validation_message']) 
            ? $this->settings['validation_message'] 
            : __('Oh no! <strong>%s</strong> email accounts are not eligible for this form.', 'gf-enhanced-tools');
        $hide_validation_message = $this->settings['hide_validation_message'] ?? 'off';

        $field_ids_map = json_decode(get_option('gf_enhanced_tools_field_ids_map', '{}'), true);

        foreach ($form_ids as $form_id) {
            $field_ids = isset($field_ids_map[$form_id]) ? array_map('intval', $field_ids_map[$form_id]) : [];
            if (empty($field_ids)) {
                error_log('GFET Email Validator: No field IDs for form ID ' . $form_id);
                continue; // Skip if no fields selected
            }

            $validator = new GFET_Email_Validator(array(
                'form_id'            => $form_id,
                'field_id'           => $field_ids,
                'domains'            => $domains,
                'validation_message' => $validation_message,
                'mode'               => $this->settings['mode'],
                'hide_validation_message' => $hide_validation_message
            ));
        }
    }

    public function validate_min_length($validation_result) {
        $form = $validation_result['form'];
        $form_ids = array_filter(array_map('intval', explode(',', $this->settings['form_ids'])));
        if (!in_array($form['id'], $form_ids)) {
            return $validation_result; // Skip if form isn’t selected
        }

        $field_ids_map = json_decode(get_option('gf_enhanced_tools_min_length_field_ids_map', '{}'), true);
        $field_ids = isset($field_ids_map[$form['id']]) ? array_map('intval', $field_ids_map[$form['id']]) : [];
        if (empty($field_ids)) {
            return $validation_result; // No fields to validate
        }

        $min_length = intval($this->settings['min_length']);

        foreach ($form['fields'] as &$field) {
            if (!in_array($field->id, $field_ids)) {
                continue; // Skip fields not in the map
            }

            $value = rgpost("input_{$field->id}");
            if (!empty($value) && strlen(trim($value)) < $min_length) {
                $validation_result['is_valid'] = false;
                $field['failed_validation'] = true;
                $field['validation_message'] = sprintf(
                    __('This field must be at least %d characters long.', 'gf-enhanced-tools'),
                    $min_length
                );
            }
        }

        $validation_result['form'] = $form;
        return $validation_result;
    }
}

// Initialize plugin
new GravityForms_Enhanced_Tools();