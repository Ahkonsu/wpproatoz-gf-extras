<?php
if (!defined('ABSPATH')) {
    exit;
}

class GFET_Admin {
    private $settings;
    private $common_words;
    private $spam_terms;

    public function __construct($settings, $common_words, $spam_terms) {
        $this->settings = $settings;
        $this->common_words = $common_words;
        $this->spam_terms = $spam_terms;

        error_log('GFET: Admin class initialized at ' . date('Y-m-d H:i:s'));

        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_gfet_get_form_fields', array($this, 'ajax_get_form_fields'));
    }

    public function add_admin_menu() {
        $hook = add_options_page(
            'Gravity Forms Enhanced Tools',
            'GF Enhanced Tools',
            'manage_options',
            'gf-enhanced-tools',
            array($this, 'settings_page')
        );
        error_log('GFET: Admin menu added, hook: ' . $hook);
    }

    public function register_settings() {
        register_setting(
            'gf_enhanced_tools_group',
            'gf_enhanced_tools_settings',
            array($this, 'sanitize_settings')
        );

        register_setting(
            'gf_enhanced_tools_group',
            'gf_enhanced_tools_field_ids_map',
            array($this, 'sanitize_field_map')
        );

        register_setting(
            'gf_enhanced_tools_group',
            'gf_enhanced_tools_min_length_field_ids_map',
            array($this, 'sanitize_field_map')
        );

        register_setting(
            'gf_enhanced_tools_spam_group',
            'gf_enhanced_tools_spam_settings',
            array($this, 'sanitize_spam_settings')
        );

        register_setting(
            'gf_enhanced_tools_spam_group',
            'gf_enhanced_tools_spam_field_ids_map',
            array($this, 'sanitize_field_map')
        );

        register_setting(
            'gf_enhanced_tools_group',
            'gf_enhanced_tools_common_words',
            array($this, 'sanitize_common_words')
        );
    }

    public function sanitize_settings($input) {
        $output = array();

        $output['email_validator']           = isset($input['email_validator']) ? 'on' : 'off';
        $output['spam_filter']               = isset($input['spam_filter']) ? 'on' : 'off';
        $output['spam_predictor']            = isset($input['spam_predictor']) ? 'on' : 'off';
        $output['spam_all_fields']           = isset($input['spam_all_fields']) ? 'on' : 'off';
        $output['spam_regex_enabled']        = isset($input['spam_regex_enabled']) ? 'on' : 'off';
        $output['spam_whole_word']           = isset($input['spam_whole_word']) ? 'on' : 'off';
        $output['spam_pattern_enabled']      = isset($input['spam_pattern_enabled']) ? 'on' : 'off';
        $output['spam_pattern_threshold']    = isset($input['spam_pattern_threshold']) ? max(2, intval($input['spam_pattern_threshold'])) : 3;
        $output['spam_keyboard_enabled']     = isset($input['spam_keyboard_enabled']) ? 'on' : 'off';
        $output['spam_keyboard_threshold']   = isset($input['spam_keyboard_threshold']) ? min(1.0, max(0.0, floatval($input['spam_keyboard_threshold']))) : 0.8;
        $output['hide_validation_message']   = isset($input['hide_validation_message']) ? 'on' : 'off';
        $output['min_length_enabled']        = isset($input['min_length_enabled']) ? 'on' : 'off';

        $output['form_ids']                  = sanitize_text_field($input['form_ids'] ?? '');
        $output['mode']                      = in_array($input['mode'], ['limit', 'ban']) ? $input['mode'] : 'limit';
        $output['validation_message']        = sanitize_text_field($input['validation_message'] ?? '');
        $output['restricted_domains']        = sanitize_textarea_field($input['restricted_domains'] ?? '');
        $output['entry_block_terms']         = sanitize_textarea_field($input['entry_block_terms'] ?? '');
        $output['spam_predictor_threshold']  = isset($input['spam_predictor_threshold']) ? max(1, intval($input['spam_predictor_threshold'])) : 3;
        $output['min_length']                = isset($input['min_length']) ? max(1, intval($input['min_length'])) : 5;

        error_log('GFET: Sanitized settings: ' . print_r($output, true));
        return $output;
    }

    public function sanitize_spam_settings($input) {
        $output = array();

        $output['spam_form_ids'] = sanitize_text_field($input['spam_form_ids'] ?? '');

        error_log('GFET: Sanitized spam settings: ' . print_r($output, true));
        return $output;
    }

    public function sanitize_field_map($input) {
        $option_name = current_filter() === 'sanitize_option_gf_enhanced_tools_field_ids_map' ? 'gf_enhanced_tools_field_ids_map' : 'gf_enhanced_tools_spam_field_ids_map';
        error_log("GFET: Sanitizing field map, input: " . print_r($input, true));

        if (empty($input) || !is_string($input)) {
            error_log("GFET: Field map input empty or not a string, returning existing option for $option_name");
            return get_option($option_name, '{}');
        }

        $decoded = json_decode(stripslashes($input), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            error_log("GFET: JSON error or not an array: " . json_last_error_msg());
            return get_option($option_name, '{}');
        }

        $sanitized = array_map(function($formFields) {
            return is_array($formFields) ? array_map('intval', $formFields) : [];
        }, $decoded);

        $is_empty = empty(array_filter($sanitized, function($fields) { return !empty($fields); }));
        if ($is_empty) {
            error_log("GFET: Sanitized field map is empty, preserving existing option for $option_name");
            return get_option($option_name, '{}');
        }

        $result = wp_json_encode($sanitized);
        error_log("GFET: Sanitized field map for $option_name: " . $result);
        return $result;
    }

    public function sanitize_common_words($input) {
        $sanitized = sanitize_textarea_field($input);
        $words = array_filter(array_map('trim', explode("\n", $sanitized)));
        $sanitized_words = array_map('strtolower', array_unique($words));
        $result = implode("\n", $sanitized_words);
        error_log('GFET: Sanitized common words: ' . $result);
        return $result;
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=gf-enhanced-tools') . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_admin_assets($hook) {
        if (($hook !== 'toplevel_page_gf-enhanced-tools') && ($hook !== 'settings_page_gf-enhanced-tools')) {
            return;
        }

        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');

        wp_enqueue_script(
            'gfet-admin-fields',
            plugin_dir_url(__FILE__) . '../assets/js/admin_fields.js',
            ['jquery', 'select2'],
            null,
            true
        );

        wp_localize_script('gfet-admin-fields', 'gfetAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gfet_ajax')
        ]);

        wp_enqueue_style(
            'gfet-admin-fields',
            plugin_dir_url(__FILE__) . '../assets/css/admin_fields.css'
        );
    }

    public function ajax_get_form_fields() {
        check_ajax_referer('gfet_ajax', 'nonce');
        $form_id = absint($_POST['form_id']);
        $field_type = sanitize_text_field($_POST['field_type'] ?? 'email');
        $form = GFAPI::get_form($form_id);
        $fields = [];

        error_log("GFET: AJAX get_form_fields called for form_id=$form_id, field_type=$field_type");

        if (!$form) {
            error_log("GFET: Failed to retrieve form ID $form_id");
            wp_send_json_error('Form not found');
            return;
        }

        $all_field_types = [];
        foreach ($form['fields'] as $field) {
            $input_type = RGFormsModel::get_input_type($field);
            $all_field_types[] = "ID {$field->id}, label=" . ($field->label ?? 'No label') . ", type=$input_type";
        }
        error_log("GFET: All fields in form $form_id: " . implode('; ', $all_field_types));

        foreach ($form['fields'] as $field) {
            $input_type = RGFormsModel::get_input_type($field);
            error_log("GFET: Checking field ID {$field->id}, label=" . ($field->label ?? 'No label') . ", input_type=$input_type");

            if (!empty($field->label)) {
                if ($field_type === 'email' && $input_type === 'email') {
                    $fields[] = [
                        'id' => $field->id,
                        'label' => $field->label,
                        'type' => $input_type
                    ];
                } elseif ($field_type === 'text') {
                    $fields[] = [
                        'id' => $field->id,
                        'label' => $field->label,
                        'type' => $input_type
                    ];
                }
            }
        }

        if (empty($fields)) {
            error_log("GFET: No fields matched field_type=$field_type for form_id=$form_id");
        }

        error_log('GFET: Returning fields: ' . print_r($fields, true));
        wp_send_json_success($fields);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'gf-enhanced-tools'));
        }

        $settings = $this->settings;
        $spam_settings = get_option('gf_enhanced_tools_spam_settings', []);
        $default_message = __('Oh no! <strong>%s</strong> email accounts are not eligible for this form.', 'gf-enhanced-tools');
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';

        error_log('GFET: Rendering settings page, user: ' . wp_get_current_user()->user_login . ', tab: ' . $active_tab);
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

                    <h2>Enable domain validation and choose fields</h2>
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
                            <th>Email Form & Field Mapping for domain validation</th>
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
                                       value="<?php echo esc_attr($settings['form_ids'] ?? ''); ?>">
    
                                <input type="hidden" name="gf_enhanced_tools_field_ids_map" id="gfet_field_ids"
                                       value='<?php echo esc_attr(get_option('gf_enhanced_tools_field_ids_map', '{}')); ?>'>
    
                                <div id="gfet_field_selector_container"></div>
                                <p class="description">Select forms and their email fields for domain validation.</p>
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
                                    echo esc_textarea($settings['restricted_domains'] ?? ''); 
                                ?></textarea>
                                <p class="description">Enter one domain per line.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Validation Message</th>
                            <td>
                                <input type="text" name="gf_enhanced_tools_settings[validation_message]" 
                                       value="<?php echo esc_attr($settings['validation_message'] ?? ''); ?>" 
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
                    </table>

                    <h2>Enable Minimum Character Field Mapping and choose fields</h2>
                    <table class="form-table">
                        <tr>
                            <th>Minimum Character Field Mapping</th>
                            <td>
                                <div id="gfet_min_length_field_selector_container"></div>
                                <input type="hidden" name="gf_enhanced_tools_min_length_field_ids_map" id="gfet_min_length_field_ids"
                                       value='<?php echo esc_attr(get_option('gf_enhanced_tools_min_length_field_ids_map', '{}')); ?>'>
                                <p class="description">Select text fields to enforce minimum character length (requires Minimum Character Length enabled below).</p>
                            </td>
                        </tr>

                        <tr>
                            <th>Minimum Character Length</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[min_length_enabled]" 
                                       value="on" <?php checked($settings['min_length_enabled'] ?? 'off', 'on'); ?>>
                                Enable minimum character length validation for selected text fields
                                <br>
                                <input type="number" name="gf_enhanced_tools_settings[min_length]" 
                                       value="<?php echo esc_attr($settings['min_length'] ?? '5'); ?>" 
                                       min="1" style="margin-top: 10px;">
                                <p class="description">Set the minimum number of characters required for text fields selected in "Minimum Character Field Mapping".</p>
                            </td>
                        </tr>
                    </table>

                    <h2 style="font-size: 1.5em; font-weight: bold;">Spam Filter and Predictor</h2>
                    <p>Block spam submissions using disallowed terms, repetitive patterns, keyboard spam, and predict spam based on historical data.</p>

                    <table class="form-table">
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
                            <th>Enable Regex Matching</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_regex_enabled]" 
                                       value="on" <?php checked($settings['spam_regex_enabled'] ?? 'off', 'on'); ?>>
                                Enable regular expression matching for spam terms
                                <p class="description">Allows terms in Disallowed Comment Keys to be regex patterns (e.g., "/\bviagra\b/i").</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Enable Whole-Word Matching</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_whole_word]" 
                                       value="on" <?php checked($settings['spam_whole_word'] ?? 'off', 'on'); ?>>
                                Enable whole-word matching for non-regex terms
                                <p class="description">Ensures terms like "casino" don’t match words like "cassino".</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Enable Repetitive Pattern Detection</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_pattern_enabled]" 
                                       value="on" <?php checked($settings['spam_pattern_enabled'] ?? 'off', 'on'); ?>>
                                Enable detection of repetitive words or phrases
                                <p class="description">Flags submissions with repeated content (e.g., "ShirleyShirleyShirley" or "Please contact me by email" repeated) to bypass minimum character requirements.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Repetition Threshold</th>
                            <td>
                                <input type="number" name="gf_enhanced_tools_settings[spam_pattern_threshold]" 
                                       value="<?php echo esc_attr($settings['spam_pattern_threshold'] ?? '3'); ?>" 
                                       min="2" style="width: 70px;">
                                <p class="description">Minimum number of repetitions to flag as spam (e.g., 3 means a word or phrase repeated 3+ times).</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Enable Keyboard Spam Detection</th>
                            <td>
                                <input type="checkbox" name="gf_enhanced_tools_settings[spam_keyboard_enabled]" 
                                       value="on" <?php checked($settings['spam_keyboard_enabled'] ?? 'off', 'on'); ?>>
                                Enable detection of random, incoherent text
                                <p class="description">Flags submissions with keyboard spam (e.g., "dfasfasfs,gfyhsxyhzhzdfhyztded") to bypass minimum character requirements.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Keyboard Spam Threshold</th>
                            <td>
                                <input type="number" name="gf_enhanced_tools_settings[spam_keyboard_threshold]" 
                                       value="<?php echo esc_attr($settings['spam_keyboard_threshold'] ?? '0.8'); ?>" 
                                       min="0" max="1" step="0.1" style="width: 70px;">
                                <p class="description">Threshold for flagging random text (0.0–1.0, default: 0.8). Lower values are stricter, higher values are more lenient.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Common Words</th>
                            <td>
                                <textarea name="gf_enhanced_tools_common_words" rows="5" cols="50"><?php 
                                    echo esc_textarea(get_option('gf_enhanced_tools_common_words', implode("\n", $this->common_words))); 
                                ?></textarea>
                                <p class="description">Enter one word per line to ignore in spam detection (e.g., "the", "and"). These words are excluded from repetitive pattern and keyboard spam checks.</p>
                            </td>
                        </tr>
    
                        <tr>
                            <th>Entry Block Terms</th>
                            <td>
                                <textarea name="gf_enhanced_tools_settings[entry_block_terms]" rows="5" cols="50"><?php 
                                    echo esc_textarea($settings['entry_block_terms'] ?? ''); 
                                ?></textarea>
                                <p class="description">Enter one term per line to block submissions. Supports regex if enabled (e.g., "/\bviagra\b/i").</p>
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
                                       value="<?php echo esc_attr($settings['spam_predictor_threshold'] ?? '3'); ?>" 
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

            <?php elseif ($active_tab === 'spam-terms') : ?>
                <h2>Spam Terms Management</h2>
                <p>Manage spam terms stored in the database. Terms can be literal or regex patterns (if regex is enabled in Settings).</p>
                <?php $this->render_spam_terms_tab(); ?>

            <?php elseif ($active_tab === 'docs') : ?>
                <h2>Documentation</h2>
                <?php $this->render_documentation_tab(); ?>

            <?php endif; ?>
        </div>
        <?php
    }

    public function render_spam_terms_tab() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $spam_settings = get_option('gf_enhanced_tools_spam_settings', []);

        if (isset($_POST['gfet_spam_terms_action']) && check_admin_referer('gfet_spam_terms_nonce')) {
            if ($_POST['gfet_spam_terms_action'] === 'add_term') {
                $term = sanitize_text_field($_POST['new_term']);
                $form_id = intval($_POST['form_id']);
                $frequency = intval($_POST['frequency']);
                $is_phrase = (strpos($term, ' ') !== false) ? 1 : 0;

                if (strlen($term) >= 3 && $form_id > 0 && $frequency > 0) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO $table_name (term, form_id, is_phrase, frequency, last_seen) 
                             VALUES (%s, %d, %d, %d, NOW()) 
                             ON DUPLICATE KEY UPDATE frequency = %d, last_seen = NOW()",
                            $term, $form_id, $is_phrase, $frequency, $frequency
                        )
                    );
                    echo '<div class="updated"><p>Term added successfully.</p></div>';
                }
            } elseif ($_POST['gfet_spam_terms_action'] === 'update_term') {
                $id = intval($_POST['term_id']);
                $frequency = intval($_POST['frequency']);
                $wpdb->update($table_name, ['frequency' => $frequency, 'last_seen' => current_time('mysql')], ['id' => $id]);
                echo '<div class="updated"><p>Term updated successfully.</p></div>';
            } elseif ($_POST['gfet_spam_terms_action'] === 'delete_term') {
                $id = intval($_POST['term_id']);
                $wpdb->delete($table_name, ['id' => $id]);
                echo '<div class="updated"><p>Term deleted successfully.</p></div>';
            } elseif ($_POST['gfet_spam_terms_action'] === 'bulk_delete') {
                $form_ids = !empty($_POST['bulk_form_ids']) ? array_map('intval', explode(',', sanitize_text_field($_POST['bulk_form_ids']))) : [];
                if (empty($form_ids)) {
                    $wpdb->query("TRUNCATE TABLE $table_name");
                    echo '<div class="updated"><p>All spam terms deleted.</p></div>';
                } else {
                    $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));
                    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE form_id IN ($placeholders)", $form_ids));
                    echo '<div class="updated"><p>Spam terms for specified forms deleted.</p></div>';
                }
            } elseif ($_POST['gfet_spam_terms_action'] === 'bulk_cleanup') {
                $min_frequency = isset($_POST['min_frequency']) ? intval($_POST['min_frequency']) : 2;
                $common_words_sql = implode("','", array_map('esc_sql', $this->common_words));
                $query = "DELETE FROM $table_name WHERE frequency < %d OR term IN ('$common_words_sql')";
                $wpdb->query($wpdb->prepare($query, $min_frequency));
                $deleted = $wpdb->rows_affected;
                echo '<div class="updated"><p>' . $deleted . ' common or low-frequency terms deleted.</p></div>';
            } elseif ($_POST['gfet_spam_terms_action'] === 'update_historical') {
                $spam_settings = get_option('gf_enhanced_tools_spam_settings', []);
                $spam_form_ids = isset($spam_settings['spam_form_ids']) ? $spam_settings['spam_form_ids'] : '';
                $form_ids = !empty($spam_form_ids) ? array_filter(array_map('intval', explode(',', $spam_form_ids))) : [];
                if (!empty($form_ids)) {
                    foreach ($form_ids as $form_id) {
                        $this->spam_terms->update_spam_terms_from_historical_entries($form_id);
                    }
                    echo '<div class="updated"><p>Historical spam terms updated.</p></div>';
                } else {
                    echo '<div class="error"><p>No spam form IDs configured. Please select forms in the Spam Form & Field Mapping section.</p></div>';
                }
            }
        }

        $spam_terms = $wpdb->get_results("SELECT * FROM $table_name ORDER BY frequency DESC, last_seen DESC");
        ?>
        <form method="post" action="options.php">
            <?php 
            settings_fields('gf_enhanced_tools_spam_group'); 
            do_settings_sections('gf_enhanced_tools_spam_group');
            ?>
            <table class="form-table">
                <tr>
                    <th>Spam Form & Field Mapping</th>
                    <td>
                        <select id="gfet_spam_form_selector" multiple="multiple" style="width:100%;">
                            <?php
                            $saved_spam_form_ids = explode(',', $spam_settings['spam_form_ids'] ?? '');
                            foreach (GFAPI::get_forms(true) as $form) {
                                $selected = in_array($form['id'], $saved_spam_form_ids) ? 'selected' : '';
                                echo "<option value='{$form['id']}' {$selected}>{$form['title']} (ID {$form['id']})</option>";
                            }
                            ?>
                        </select>

                        <input type="hidden" name="gf_enhanced_tools_spam_settings[spam_form_ids]" id="gfet_spam_form_ids"
                               value="<?php echo esc_attr($spam_settings['spam_form_ids'] ?? ''); ?>">

                        <input type="hidden" name="gf_enhanced_tools_spam_field_ids_map" id="gfet_spam_field_ids"
                               value='<?php echo esc_attr(get_option('gf_enhanced_tools_spam_field_ids_map', '{}')); ?>'>

                        <div id="gfet_spam_field_selector_container"></div>
                        <p class="description">Select one or more forms and then choose the fields to monitor for spam terms in each.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Spam Settings'); ?>
        </form>

        <h3>Add New Term</h3>
        <form method="post" action="">
            <?php wp_nonce_field('gfet_spam_terms_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="new_term">Term/Phrase</label></th>
                    <td>
                        <input type="text" name="new_term" id="new_term" class="regular-text" placeholder="e.g., buy now or /\bviagra\b/i">
                        <p class="description">Minimum 3 characters, max 5 words. Use regex patterns like "/\bterm\b/i" if regex is enabled.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="form_id">Form ID</label></th>
                    <td><input type="number" name="form_id" id="form_id" min="1" required></td>
                </tr>
                <tr>
                    <th><label for="frequency">Initial Frequency</label></th>
                    <td><input type="number" name="frequency" id="frequency" min="1" value="1" required></td>
                </tr>
            </table>
            <input type="hidden" name="gfet_spam_terms_action" value="add_term">
            <?php submit_button('Add Term'); ?>
        </form>

        <h3>Existing Terms</h3>
        <?php if ($spam_terms) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Term/Phrase</th>
                        <th>Form ID</th>
                        <th>Type</th>
                        <th>Frequency</th>
                        <th>Last Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spam_terms as $term) : ?>
                        <tr>
                            <td><?php echo esc_html($term->term); ?></td>
                            <td><?php echo esc_html($term->form_id); ?></td>
                            <td><?php echo $term->is_phrase ? 'Phrase' : 'Word'; ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('gfet_spam_terms_nonce'); ?>
                                    <input type="number" name="frequency" value="<?php echo esc_attr($term->frequency); ?>" min="1" style="width:70px;">
                                    <input type="hidden" name="term_id" value="<?php echo esc_attr($term->id); ?>">
                                    <input type="hidden" name="gfet_spam_terms_action" value="update_term">
                                    <input type="submit" class="button" value="Update">
                                </form>
                            </td>
                            <td><?php echo esc_html($term->last_seen); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('gfet_spam_terms_nonce'); ?>
                                    <input type="hidden" name="term_id" value="<?php echo esc_attr($term->id); ?>">
                                    <input type="hidden" name="gfet_spam_terms_action" value="delete_term">
                                    <input type="submit" class="button button-link-delete" value="Delete" onclick="return confirm('Are you sure?');">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No spam terms found.</p>
        <?php endif; ?>

        <h3>Bulk Actions</h3>
        <form method="post" action="">
            <?php wp_nonce_field('gfet_spam_terms_nonce'); ?>
            <p>
                <label for="bulk_form_ids">Form IDs (optional):</label>
                <input type="text" name="bulk_form_ids" id="bulk_form_ids" placeholder="e.g., 152, 153">
                <p class="description">Leave blank to delete all terms, or specify Form IDs to delete terms for those forms only.</p>
            </p>
            <input type="hidden" name="gfet_spam_terms_action" value="bulk_delete">
            <?php submit_button('Bulk Delete Terms', 'delete', 'submit', false, ['onclick' => 'return confirm("Are you sure you want to delete these terms?");']); ?>
        </form>

        <form method="post" action="">
            <?php wp_nonce_field('gfet_spam_terms_nonce'); ?>
            <p>
                <label for="min_frequency">Minimum Frequency:</label>
                <input type="number" name="min_frequency" id="min_frequency" min="1" value="2">
                <p class="description">Delete terms with frequency below this value or matching common words (e.g., "the", "and").</p>
            </p>
            <input type="hidden" name="gfet_spam_terms_action" value="bulk_cleanup">
            <?php submit_button('Bulk Cleanup Terms', 'delete', 'submit', false, ['onclick' => 'return confirm("Are you sure you want to delete common or low-frequency terms?");']); ?>
        </form>

        <form method="post" action="">
            <?php wp_nonce_field('gfet_spam_terms_nonce'); ?>
            <input type="hidden" name="gfet_spam_terms_action" value="update_historical">
            <?php submit_button('Update Historical Spam Terms', 'secondary'); ?>
            <p class="description">This will process all past spam entries for configured forms into the spam terms database.</p>
        </form>
        <?php
    }

    public function render_documentation_tab() {
        $doc_file = plugin_dir_path(__FILE__) . '../documentation.txt';
        if (file_exists($doc_file)) {
            $content = file_get_contents($doc_file);
            echo '<pre style="white-space: pre-wrap;">' . esc_html($content) . '</pre>';
        } else {
            echo '<p>Error: Documentation file not found.</p>';
        }
    }
}
?>