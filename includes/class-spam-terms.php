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

        // Log initialization
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GFET: GFET_Spam_Terms initialized at ' . date('Y-m-d H:i:s'));
        }
    }

    public function spam_filter($is_spam, $form, $entry) {
        if ($is_spam) {
            return $is_spam;
        }

        $spam_form_ids = array_filter(array_map('intval', explode(',', $this->spam_settings['spam_form_ids'] ?? '')));
        if (!in_array($form['id'], $spam_form_ids)) {
            return $is_spam; // Skip if form isn’t selected for spam filtering
        }

        $spam_field_ids_map = json_decode(get_option('gf_enhanced_tools_spam_field_ids_map', '{}'), true);
        $field_ids = isset($spam_field_ids_map[$form['id']]) ? array_map('intval', $spam_field_ids_map[$form['id']]) : [];
        $fields_to_check = array();

        // Get spam detection settings
        $regex_enabled = $this->settings['spam_regex_enabled'] === 'on';
        $whole_word_enabled = $this->settings['spam_whole_word'] === 'on';
        $pattern_enabled = $this->settings['spam_pattern_enabled'] === 'on';
        $pattern_threshold = intval($this->settings['spam_pattern_threshold'] ?? 3);
        $keyboard_enabled = $this->settings['spam_keyboard_enabled'] === 'on';
        $keyboard_threshold = floatval($this->settings['spam_keyboard_threshold'] ?? 0.8);

        // Collect fields to check
        if ($this->settings['spam_all_fields'] === 'on' || empty($field_ids)) {
            foreach ($form['fields'] as $field) {
                $id = $field->id;
                $value = rgar($entry, $id);
                if ($field->type === 'textarea') {
                    $value = wp_strip_all_tags($value);
                }
                if (!empty($value) && is_string($value)) {
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
                if (!empty($value) && is_string($value)) {
                    $fields_to_check[] = $value;
                }
            }
        }

        // Check for keyboard spam
        if ($keyboard_enabled) {
            foreach ($fields_to_check as $field_value) {
                $keyboard_result = $this->detect_keyboard_spam($field_value, $keyboard_threshold);
                if ($keyboard_result['is_spam']) {
                    $this->log_spam($entry, $form, 'keyboard_spam', $keyboard_result['details']);
                    return true;
                }
            }
        }

        // Check for repetitive patterns
        if ($pattern_enabled) {
            foreach ($fields_to_check as $field_value) {
                $pattern_result = $this->detect_repetitive_patterns($field_value, $pattern_threshold);
                if ($pattern_result['is_spam']) {
                    $this->log_spam($entry, $form, 'repetitive_pattern', $pattern_result['details']);
                    return true;
                }
            }
        }

        // Check manual spam terms (from Disallowed Comment Keys)
        $mod_keys = trim(get_option('disallowed_keys'));
        $words = !empty($mod_keys) ? explode("\n", $mod_keys) : array();
        $matched_terms = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) {
                continue;
            }

            $is_match = false;

            if ($regex_enabled && $this->is_valid_regex($word)) {
                // Handle regex term
                try {
                    foreach ($fields_to_check as $field_value) {
                        if ($field_value && preg_match($word, $field_value)) {
                            $is_match = true;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    error_log('GFET: Invalid regex pattern: ' . $word . ' - Error: ' . $e->getMessage());
                    continue;
                }
            } else {
                // Handle non-regex term
                $word_escaped = preg_quote($word, '#');
                $pattern = $whole_word_enabled ? "#\b{$word_escaped}\b#i" : "#{$word_escaped}#i";
                try {
                    foreach ($fields_to_check as $field_value) {
                        if ($field_value && preg_match($pattern, $field_value)) {
                            $is_match = true;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    error_log('GFET: Pattern error for term: ' . $word . ' - Error: ' . $e->getMessage());
                    continue;
                }
            }

            if ($is_match) {
                $matched_terms[] = $word;
            }
        }

        if (!empty($matched_terms)) {
            $this->log_spam($entry, $form, 'spam_term', implode(', ', $matched_terms));
            return true;
        }

        // Check predicted spam terms from database
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
                $term_escaped = preg_quote(trim($term), '#');
                $pattern = $is_phrase ? "#\b{$term_escaped}\b#i" : "#\b{$term_escaped}\b#i";

                foreach ($fields_to_check as $field_value) {
                    if ($field_value && preg_match($pattern, $field_value)) {
                        $matched_terms[] = $term;
                        break;
                    }
                }
            }

            if (!empty($matched_terms)) {
                $this->log_spam($entry, $form, 'spam_term_predicted', implode(', ', $matched_terms));
                return true;
            }
        }

        return $is_spam;
    }

    private function detect_keyboard_spam($text, $threshold) {
        $result = ['is_spam' => false, 'details' => ''];

        // Skip short inputs
        if (strlen($text) < 10) {
            return $result;
        }

        // Normalize text
        $text = strtolower(preg_replace('/[^\w\s]/', '', $text));
        $words = array_filter(preg_split('/\s+/', $text));

        // Calculate consonant-to-vowel ratio
        $consonants = preg_match_all('/[bcdfghjklmnpqrstvwxyz]/i', $text, $matches);
        $vowels = preg_match_all('/[aeiou]/i', $text, $matches);
        $ratio = $vowels > 0 ? $consonants / $vowels : $consonants;
        $ratio_score = min($ratio / 3, 1); // Normalize to 0–1, high ratio = more random

        // Calculate entropy (measure of randomness)
        $char_counts = array_count_values(str_split($text));
        $length = strlen($text);
        $entropy = 0;
        foreach ($char_counts as $count) {
            $p = $count / $length;
            $entropy -= $p * log($p, 2);
        }
        $entropy_score = min($entropy / 8, 1); // Normalize to 0–1, high entropy = more random

        // Check word recognizability
        $non_recognizable = 0;
        $total_words = count($words);
        foreach ($words as $word) {
            if (strlen($word) >= 3 && !in_array($word, $this->common_words)) {
                // Simple heuristic: non-dictionary-like words are short and consonant-heavy
                $word_consonants = preg_match_all('/[bcdfghjklmnpqrstvwxyz]/i', $word);
                $word_vowels = preg_match_all('/[aeiou]/i', $word);
                if ($word_vowels == 0 || ($word_consonants / ($word_vowels + 1)) > 2) {
                    $non_recognizable++;
                }
            }
        }
        $recognizability_score = $total_words > 0 ? $non_recognizable / $total_words : 0;

        // Combine scores (weighted average)
        $spam_score = ($ratio_score * 0.4) + ($entropy_score * 0.3) + ($recognizability_score * 0.3);

        if ($spam_score >= $threshold) {
            $result['is_spam'] = true;
            $result['details'] = sprintf(
                'Keyboard spam detected, score: %.2f, ratio: %.2f, entropy: %.2f, non-recognizable: %d/%d',
                $spam_score,
                $ratio,
                $entropy,
                $non_recognizable,
                $total_words
            );
        }

        return $result;
    }

    private function detect_repetitive_patterns($text, $threshold) {
        $result = ['is_spam' => false, 'details' => ''];

        // Normalize text
        $text = strtolower(preg_replace('/[^\w\s]/', ' ', $text));
        $words = array_filter(preg_split('/\s+/', $text));

        // Check for repeated words
        $word_counts = [];
        $consecutive_counts = [];
        $previous_word = null;
        $consecutive_count = 1;

        foreach ($words as $word) {
            if (strlen($word) < 3 || in_array($word, $this->common_words)) {
                continue;
            }
            $word_counts[$word] = isset($word_counts[$word]) ? $word_counts[$word] + 1 : 1;

            if ($word === $previous_word) {
                $consecutive_count++;
            } else {
                if ($previous_word && $consecutive_count >= $threshold) {
                    $consecutive_counts[$previous_word] = $consecutive_count;
                }
                $consecutive_count = 1;
            }
            $previous_word = $word;
        }

        if ($previous_word && $consecutive_count >= $threshold) {
            $consecutive_counts[$previous_word] = $consecutive_count;
        }

        foreach ($word_counts as $word => $count) {
            if ($count >= $threshold) {
                $result['is_spam'] = true;
                $result['details'] .= "Repeated word: $word, count: $count; ";
            }
        }

        foreach ($consecutive_counts as $word => $count) {
            if ($count >= $threshold) {
                $result['is_spam'] = true;
                $result['details'] .= "Consecutive word: $word, count: $count; ";
            }
        }

        // Check for repeated phrases (2–5 words)
        $text_clean = implode(' ', $words);
        for ($length = 2; $length <= 5; $length++) {
            $phrases = [];
            for ($i = 0; $i <= count($words) - $length; $i++) {
                $phrase = implode(' ', array_slice($words, $i, $length));
                if (strlen($phrase) >= 3 && !$this->contains_common_words($phrase)) {
                    $phrases[$phrase] = isset($phrases[$phrase]) ? $phrases[$phrase] + 1 : 1;
                }
            }

            foreach ($phrases as $phrase => $count) {
                if ($count >= $threshold) {
                    $result['is_spam'] = true;
                    $result['details'] .= "Repeated phrase: $phrase, count: $count; ";
                }
            }
        }

        if ($result['is_spam']) {
            $result['details'] = trim($result['details'], '; ');
        }

        return $result;
    }

    public function collect_spam_terms($entry, $form) {
        if (!isset($entry['is_spam']) || $entry['is_spam'] != 1) {
            return;
        }

        $spam_form_ids = array_filter(array_map('intval', explode(',', $this->spam_settings['spam_form_ids'] ?? '')));
        if (!in_array($form['id'], $spam_form_ids)) {
            return; // Skip if form isn’t selected for spam term collection
        }

        $spam_field_ids_map = json_decode(get_option('gf_enhanced_tools_spam_field_ids_map', '{}'), true);
        $field_ids = isset($spam_field_ids_map[$form['id']]) ? array_map('intval', $spam_field_ids_map[$form['id']]) : [];
        $fields_to_check = array();

        if ($this->settings['spam_all_fields'] === 'on' || empty($field_ids)) {
            foreach ($form['fields'] as $field) {
                $id = $field->id;
                $value = rgar($entry, $id);
                if ($field->type === 'textarea') {
                    $value = wp_strip_all_tags($value);
                }
                if (!empty($value) && is_string($value)) {
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
                if (!empty($value) && is_string($value)) {
                    $fields_to_check[] = $value;
                }
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gf_spam_terms';
        $form_id = $form['id'];

        foreach ($fields_to_check as $field_value) {
            $words = preg_split('/\s+/', strtolower($field_value));
            $words = array_filter($words);

            foreach ($words as $word) {
                if (strlen($word) < 3 || in_array($word, $this->common_words)) {
                    continue;
                }
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
                    if (!empty($value) && is_string($value)) {
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
                    if (!empty($value) && is_string($value)) {
                        $fields_to_check[] = $value;
                    }
                }
            }

            foreach ($fields_to_check as $field_value) {
                $words = preg_split('/\s+/', strtolower($field_value));
                $words = array_filter($words);

                foreach ($words as $word) {
                    if (strlen($word) < 3 || in_array($word, $this->common_words)) {
                        continue;
                    }
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

    private function is_valid_regex($pattern) {
        // Check if pattern is enclosed in delimiters (e.g., /pattern/i)
        if (preg_match('/^\/.*\/[a-z]*$/i', $pattern)) {
            // Test the pattern
            $result = @preg_match($pattern, '');
            return $result !== false;
        }
        return false;
    }

    private function log_spam($entry, $form, $reason, $details = '') {
        $log_entry = [
            'form_id'   => $form['id'],
            'entry_id'  => rgar($entry, 'id', 'unknown'),
            'reason'    => $reason,
            'details'   => $details,
            'timestamp' => current_time('mysql'),
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GFET Spam Detected: ' . print_r($log_entry, true));
        }
    }
}
?>