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

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_gfet_get_form_fields', array($this, 'ajax_get_form_fields'));
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
            'gf_enhanced_tools_spam_group',
            'gf_enhanced_tools_spam_settings',
            array($this, 'sanitize_spam_settings')
        );

        register_setting(
            'gf_enhanced_tools_spam_group',
            'gf_enhanced_tools_spam_field_ids_map',
            array($this, 'sanitize_field_map')
        );
    }

    public function sanitize_settings($input) {
        $output = array();

        $output['email_validator']           = isset($input['email_validator']) ? 'on' : 'off';
        $output['spam_filter']               = isset($input['spam_filter']) ? 'on' : 'off';
        $output['spam_predictor']            = isset($input['spam_predictor']) ? 'on' : 'off';
        $output['spam_all_fields']           = isset($input['spam_all_fields']) ? 'on' : 'off';
        $output['hide_validation_message']   = isset($input['hide_validation_message']) ? 'on' : 'off';
        $output['min_length_enabled']        = isset($input['min_length_enabled']) ? 'on' : 'off';

        $output['form_ids']                  = sanitize_text_field($input['form_ids'] ?? '');
        $output['mode']                      = in_array($input['mode'], ['limit', 'ban']) ? $input['mode'] : 'limit';
        $output['validation_message']        = sanitize_text_field($input['validation_message'] ?? '');
        $output['restricted_domains']        = sanitize_textarea_field($input['restricted_domains'] ?? '');
        $output['entry_block_terms']         = sanitize_textarea_field($input['entry_block_terms'] ?? '');
        $output['spam_predictor_threshold']  = isset($input['spam_predictor_threshold']) ? max(1, intval($input['spam_predictor_threshold'])) : 3;
        $output['min_length']                = isset($input['min_length']) ? max(1, intval($input['min_length'])) : 5;

        error_log('Sanitized settings: ' . print_r($output, true));
        return $output;
    }

    public function sanitize_spam_settings($input) {
        $output = array();

        $output['spam_form_ids'] = sanitize_text_field($input['spam_form_ids'] ?? '');

        error_log('Sanitized spam settings: ' . print_r($output, true));
        return $output;
    }

    public function sanitize_field_map($input) {
        error_log('[GFET] Raw input to sanitize_field_map: ' . print_r($input, true));

        if (empty($input) || !is_string($input)) {
            error_log('[GFET] Field map input empty or not a string, returning []');
            return '[]'; // Return empty array if input is empty or invalid
        }

        $decoded = json_decode(stripslashes($input), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            error_log('[GFET] JSON error or not an array: ' . json_last_error_msg());
            return '[]'; // Return empty array on error
        }

        $sanitized = array_map(function($formFields) {
            return is_array($formFields) ? array_map('intval', $formFields) : [];
        }, $decoded);

        $result = wp_json_encode($sanitized);
        error_log('[GFET] Sanitized field map: ' . $result);
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

    public function settings_page() {
        $settings = $this->settings;
        $spam_settings = get_option('gf_enhanced_tools_spam_settings', []);
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
    
                    <h2 style="font-size: 1.5em; font-weight: bold;">Domain Validator and Minimum Characters</h2>
                    <p>Restrict submissions by email domain and enforce a minimum character length for text fields.</p>

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
                            <th>Email Form & Field Mapping</th>
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
                                       value='<?php echo esc_attr(get_option('gf_enhanced_tools_field_ids_map', '[]')); ?>'>
    
                                <div id="gfet_field_selector_container"></div>
                                <p class="description">Select one or more forms and their fields. Email fields are used for domain banning/allowing; text fields enforce the minimum character length (if enabled below).</p>
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
                                <p class="description">Set the minimum number of characters required for text fields selected in "Email Form & Field Mapping".</p>
                            </td>
                        </tr>
                    </table>

                    <h2 style="font-size: 1.5em; font-weight: bold;">Spam Filter and Predictor</h2>
                    <p>Block spam submissions using disallowed terms and predict spam based on historical data.</p>

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
                            <th>Entry Block Terms</th>
                            <td>
                                <textarea name="gf_enhanced_tools_settings[entry_block_terms]" rows="5" cols="50"><?php 
                                    echo esc_textarea($settings['entry_block_terms'] ?? ''); 
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
                               value='<?php echo esc_attr(get_option('gf_enhanced_tools_spam_field_ids_map', '[]')); ?>'>

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
                        <input type="text" name="new_term" id="new_term" class="regular-text" placeholder="e.g., buy now">
                        <p class="description">Minimum 3 characters, max 5 words.</p>
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