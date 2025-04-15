<?php
if (!defined('ABSPATH')) {
    exit;
}

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
        error_log('GFET Email Validator: Validating form ID ' . $validation_result['form']['id']);
        $form = $validation_result['form'];
        foreach ($form['fields'] as &$field) {
            if (!method_exists('RGFormsModel', 'get_input_type') || 
                RGFormsModel::get_input_type($field) != 'email') {
                error_log('GFET Email Validator: Skipping field ID ' . $field->id . ' (not email)');
                continue;
            }
            if ($this->args['field_id'] && !in_array($field->id, $this->args['field_id'])) {
                error_log('GFET Email Validator: Skipping field ID ' . $field->id . ' (not in field_ids)');
                continue;
            }
            
            $page_number = class_exists('GFFormDisplay') ? GFFormDisplay::get_source_page($form['id']) : 0;
            if ($page_number > 0 && $field->pageNumber != $page_number) {
                error_log('GFET Email Validator: Skipping field ID ' . $field->id . ' (wrong page)');
                continue;
            }
            if (method_exists('GFFormsModel', 'is_field_hidden') && 
                GFFormsModel::is_field_hidden($form, $field, array())) {
                error_log('GFET Email Validator: Skipping field ID ' . $field->id . ' (hidden)');
                continue;
            }

            $domain = $this->get_email_domain($field);
            error_log('GFET Email Validator: Checking domain ' . $domain . ' for field ID ' . $field->id);
            if ($this->is_domain_valid($domain) || empty($domain)) {
                error_log('GFET Email Validator: Domain ' . $domain . ' is valid or empty');
                continue;
            }

            error_log('GFET Email Validator: Domain ' . $domain . ' is invalid');
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