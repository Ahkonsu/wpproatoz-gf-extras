<?php
if (!defined('ABSPATH')) {
    exit;
}

class GFET_Spam_Terms {
    private $settings;
    private $spam_settings;
    private $common_words;

    public function __construct($settings, $common_words) {
        $this->settings = $settings;
        $this->spam_settings = get_option('gf_enhanced_tools_spam_settings', []);
        $this->common_words = $common_words;

        if ($this->settings['spam_filter'] === 'on') {
            add_filter('gform_entry_is_spam', array($this, 'spam_filter'), 10, 3);
        }

        if ($this->settings['spam_predictor'] === 'on') {
            add_action('gform_after_submission', array($this, 'collect_spam_terms'), 10, 2);
        }
    }

    public function spam_filter($is_spam, $form, $entry) {
        $spam_form_ids = array_filter(array_map('intval', explode(',', $this->spam_settings['spam_form_ids'] ?? '')));
        if (!in_array($form['id'], $spam_form_ids)) {
            return $is_spam; // Skip if form isn’t selected for spam filtering
        }

        $spam_field_ids_map = json_decode(get_option('gf_enhanced_tools_spam_field_ids_map', '{}'), true);
        $field_ids = isset($spam_field_ids_map[$form['id']]) ? array_map('intval', $spam_field_ids_map[$form['id']]) : [];

        $mod_keys = trim(get_option('disallowed_keys'));
        $words = !empty($mod_keys) ? explode("\n", $mod_keys) : array();
        $fields_to_check = array();

        if ($this->settings['spam_all_fields'] === 'on' || empty($field_ids)) {
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
            foreach ($field_ids as $id) {
                $value = rgar($entry, $id);
                $field = GFAPI::get_field($form, $id);
                if ($field && $field->type === 'textarea') {
                    $value = wp_strip_all_tags($value);
                }
                if (!empty($value)) {
                    $fields_to_check[] = $value;
                }
            }
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

        $spam_form_ids = array_filter(array_map('intval', explode(',', $this->spam_settings['spam_form_ids'] ?? '')));
        if (!in_array($form['id'], $spam_form_ids)) {
            return; // Skip if form isn’t selected for spam term collection
        }

        $spam_field_ids_map = json_decode(get_option('gf_enhanced_tools_spam_field_ids_map', '{}'), true);
        $field_ids = isset($spam_field_ids_map[$form['id']]) ? array_map('intval', $spam_field_ids_map[$form['id']]) : [];

        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $form_id = $form['id'];
        $fields_to_check = array();

        if ($this->settings['spam_all_fields'] === 'on' || empty($field_ids)) {
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
            foreach ($field_ids as $id) {
                $value = rgar($entry, $id);
                $field = GFAPI::get_field($form, $id);
                if ($field && $field->type === 'textarea') {
                    $value = wp_strip_all_tags($value);
                }
                if (!empty($value)) {
                    $fields_to_check[] = $value;
                }
            }
        }

        foreach ($fields_to_check as $field_value) {
            $words = preg_split('/\s+/', strtolower($field_value));
            $words = array_filter($words);

            foreach ($words as $word) {
                if (strlen($word) < 3 || in_array($word, $this->common_words)) continue;
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
                    if (strlen($phrase) >= 3 && !$this->contains_common_words($phrase)) {
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

        $spam_field_ids_map = json_decode(get_option('gf_enhanced_tools_spam_field_ids_map', '{}'), true);
        $field_ids = isset($spam_field_ids_map[$form_id]) ? array_map('intval', $spam_field_ids_map[$form_id]) : [];

        foreach ($spam_entries as $entry) {
            $fields_to_check = array();
            $form = GFAPI::get_form($entry['form_id']);
            if ($this->settings['spam_all_fields'] === 'on' || empty($field_ids)) {
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
                foreach ($field_ids as $id) {
                    $value = rgar($entry, $id);
                    $field = GFAPI::get_field($form, $id);
                    if ($field && $field->type === 'textarea') {
                        $value = wp_strip_all_tags($value);
                    }
                    if (!empty($value)) {
                        $fields_to_check[] = $value;
                    }
                }
            }

            foreach ($fields_to_check as $field_value) {
                $words = preg_split('/\s+/', strtolower($field_value));
                $words = array_filter($words);

                foreach ($words as $word) {
                    if (strlen($word) < 3 || in_array($word, $this->common_words)) continue;
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
                        if (strlen($phrase) >= 3 && !$this->contains_common_words($phrase)) {
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

    private function get_spam_entries($form_id) {
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

    private function contains_common_words($phrase) {
        $phrase_words = explode(' ', strtolower($phrase));
        foreach ($phrase_words as $word) {
            if (in_array($word, $this->common_words)) {
                return true;
            }
        }
        return false;
    }
}