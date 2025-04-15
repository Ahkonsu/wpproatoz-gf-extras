jQuery(document).ready(function($) {
    console.log('admin_fields.js loaded');

    var isInitializing = true;

    function updateFieldIdsMap(hiddenInputId) {
        if (isInitializing) {
            console.log('Skipping updateFieldIdsMap for #' + hiddenInputId + ' during initialization');
            return;
        }

        var $container = hiddenInputId === 'gfet_field_ids' ? $('#gfet_field_selector_container') : 
                         hiddenInputId === 'gfet_min_length_field_ids' ? $('#gfet_min_length_field_selector_container') : 
                         $('#gfet_spam_field_selector_container');
        var $formIdsInput = $('#' + hiddenInputId);
        if (!$formIdsInput.length) {
            console.error('Hidden input #' + hiddenInputId + ' not found');
            return;
        }
        var newFieldIdsMap = {};
        $('.gfet-field-selector', $container).each(function() {
            var formId = $(this).data('form-id');
            var values = $(this).val() || [];
            console.log('Collecting values for form ID ' + formId + ' in #' + hiddenInputId + ':', values);
            newFieldIdsMap[formId] = values;
        });
        $formIdsInput.val(JSON.stringify(newFieldIdsMap));
        console.log('Updated field IDs map for #' + hiddenInputId + ':', newFieldIdsMap);
    }

    function initializeFormSelectors(activeTab) {
        try {
            console.log('Checking DOM elements');
            if (!$('#gfet_field_selector_container').length) {
                console.error('Email field selector container not found');
            }
            if (!$('#gfet_min_length_field_selector_container').length) {
                console.error('Minimum character field selector container not found');
            }

            if (activeTab === 'settings') {
                var $emailSelector = $('#gfet_form_selector');
                var $emailFormIdsInput = $('#gfet_form_ids');
                if ($emailSelector.length && $emailFormIdsInput.length && !$emailSelector.hasClass('select2-hidden-accessible')) {
                    console.log('Initializing email form selector');
                    $emailSelector.select2({
                        placeholder: 'Select forms for email validation',
                        allowClear: true
                    }).on('change', function() {
                        var formIds = $(this).val() || [];
                        $emailFormIdsInput.val(formIds.join(','));
                        console.log('Email form selector changed, set #gfet_form_ids to:', $emailFormIdsInput.val());
                        console.log('Updating email field selectors for form IDs:', formIds);
                        updateFieldSelectors(formIds, '#gfet_field_selector_container', 'gfet_field_ids', 'email');
                        console.log('Updating minimum character field selectors for form IDs:', formIds);
                        updateFieldSelectors(formIds, '#gfet_min_length_field_selector_container', 'gfet_min_length_field_ids', 'text');
                    });

                    var initialFormIds = $emailFormIdsInput.val() ? $emailFormIdsInput.val().split(',').filter(id => id) : [];
                    console.log('Initial email form IDs:', initialFormIds);
                    console.log('Initializing email field selectors for form IDs:', initialFormIds);
                    updateFieldSelectors(initialFormIds, '#gfet_field_selector_container', 'gfet_field_ids', 'email');
                    console.log('Initializing minimum character field selectors for form IDs:', initialFormIds);
                    updateFieldSelectors(initialFormIds, '#gfet_min_length_field_selector_container', 'gfet_min_length_field_ids', 'text');
                } else {
                    console.log('Email selector or input not found on settings tab');
                }
            }

            if (activeTab === 'spam-terms') {
                var $spamSelector = $('#gfet_spam_form_selector');
                var $spamFormIdsInput = $('#gfet_spam_form_ids');
                if ($spamSelector.length && $spamFormIdsInput.length && !$spamSelector.hasClass('select2-hidden-accessible')) {
                    console.log('Initializing spam form selector');
                    $spamSelector.select2({
                        placeholder: 'Select forms for spam term monitoring',
                        allowClear: true
                    }).on('change', function() {
                        var formIds = $(this).val() || [];
                        $spamFormIdsInput.val(formIds.join(','));
                        console.log('Spam form selector changed, set #gfet_spam_form_ids to:', $spamFormIdsInput.val());
                        updateFieldSelectors(formIds, '#gfet_spam_field_selector_container', 'gfet_spam_field_ids', 'text');
                    });

                    var initialSpamFormIds = $spamFormIdsInput.val() ? $spamFormIdsInput.val().split(',').filter(id => id) : [];
                    console.log('Initial spam form IDs:', initialSpamFormIds);
                    updateFieldSelectors(initialSpamFormIds, '#gfet_spam_field_selector_container', 'gfet_spam_field_ids', 'text');
                } else {
                    console.log('Spam selector or input not found on spam-terms tab');
                }
            }

            isInitializing = false;
            console.log('Initialization complete, enabling field map updates');
        } catch (error) {
            console.error('Error during initializeFormSelectors:', error);
        }
    }

    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get('tab') || 'settings';
    console.log('Active tab on load:', activeTab);
    initializeFormSelectors(activeTab);

    $('.nav-tab-wrapper a').on('click', function(e) {
        var newTab = $(this).attr('href').split('tab=')[1] || 'settings';
        console.log('Tab clicked, switching to:', newTab);
        window.history.pushState({}, document.title, '?page=gf-enhanced-tools&tab=' + newTab);
        setTimeout(function() {
            initializeFormSelectors(newTab);
        }, 100);
    });

    function updateFieldSelectors(formIds, containerSelector, hiddenInputId, fieldType) {
        console.log('updateFieldSelectors called for #' + hiddenInputId + ', formIds:', formIds, 'fieldType:', fieldType);
        var $container = $(containerSelector);
        if (!$container.length) {
            console.error('Container not found:', containerSelector);
            return;
        }
        $container.empty();
        var $formIdsInput = $('#' + hiddenInputId);
        if (!$formIdsInput.length) {
            console.error('Hidden input #' + hiddenInputId + ' not found');
            $container.append('<p>Error: Hidden input not found.</p>');
            return;
        }
        var fieldIdsMapRaw = $formIdsInput.val() || '{}';
        var fieldIdsMap = JSON.parse(fieldIdsMapRaw);
        if (!fieldIdsMap || typeof fieldIdsMap !== 'object') {
            console.warn('Field IDs map is not an object, resetting to empty object:', fieldIdsMapRaw);
            fieldIdsMap = {};
        }
        console.log('Loaded field IDs map for #' + hiddenInputId + ':', fieldIdsMap);

        if (!formIds || formIds.length === 0) {
            console.log('No form IDs provided for #' + hiddenInputId + ', clearing container');
            $container.append('<p>No forms selected.</p>');
            updateFieldIdsMap(hiddenInputId);
            return;
        }

        var ajaxRequests = formIds.map(function(formId) {
            if (!formId) return null;

            var $formSection = $('<div class="gfet-form-section"><h4>Form ID: ' + formId + '</h4></div>');
            var $select = $('<select multiple="multiple" class="gfet-field-selector" data-form-id="' + formId + '" style="width:100%;"></select>');

            $formSection.append($select);
            $container.append($formSection);

            console.log('Requesting fields for form ID ' + formId + ', fieldType=' + fieldType);
            return $.ajax({
                url: gfetAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'gfet_get_form_fields',
                    form_id: formId,
                    field_type: fieldType,
                    nonce: gfetAjax.nonce
                },
                success: function(response) {
                    console.log('Received fields for form ID ' + formId + ' for #' + hiddenInputId + ':', response);
                    if (response.success) {
                        if (response.data.length === 0) {
                            console.log('No fields returned for form ID ' + formId + ', fieldType=' + fieldType);
                            $formSection.append('<p>No ' + (fieldType === 'text' ? 'text or textarea' : fieldType) + ' fields found for this form.</p>');
                        }
                        var fieldIdArray = Array.isArray(fieldIdsMap[formId]) ? fieldIdsMap[formId].map(String) : [];
                        console.log('Field IDs for form ' + formId + ':', fieldIdArray);
                        response.data.forEach(function(field) {
                            $select.append($('<option>', {
                                value: field.id,
                                text: field.label + ' (ID: ' + field.id + ', Type: ' + field.type + ')',
                                selected: fieldIdArray.includes(String(field.id))
                            }));
                        });
                        $select.select2({
                            placeholder: 'Select fields',
                            allowClear: true
                        }).val(fieldIdArray).trigger('change');
                        $select.on('change', function() {
                            updateFieldIdsMap(hiddenInputId);
                            console.log('Field selector changed for #' + hiddenInputId + ', updated value:', $formIdsInput.val());
                        });
                        updateFieldIdsMap(hiddenInputId);
                    } else {
                        console.error('Error in AJAX response for form ID ' + formId + ':', response);
                        $formSection.append('<p>Error loading fields: ' + (response.data || 'Unknown error') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error for form ID ' + formId + ':', status, error);
                    $formSection.append('<p>Error loading fields: ' + status + '</p>');
                }
            });
        }).filter(request => request !== null);

        $.when.apply($, ajaxRequests).then(function() {
            console.log('All AJAX requests for #' + hiddenInputId + ' completed');
            updateFieldIdsMap(hiddenInputId);
        });
    }

    $('form').on('change', '#gfet_form_selector, .gfet-field-selector, input[name="gf_enhanced_tools_settings[hide_validation_message]"], input[name="gf_enhanced_tools_settings[validation_message]"]', function() {
        console.log('Relevant input changed, updating field maps');
        updateFieldIdsMap('gfet_field_ids');
        updateFieldIdsMap('gfet_min_length_field_ids');
        console.log('Updated #gfet_field_ids to:', $('#gfet_field_ids').val());
        console.log('Updated #gfet_min_length_field_ids to:', $('#gfet_min_length_field_ids').val());
    });

    $('form').on('submit', function(e) {
        updateFieldIdsMap('gfet_field_ids');
        updateFieldIdsMap('gfet_min_length_field_ids');
        var fieldIds = $('#gfet_field_ids').val();
        if (fieldIds === '{"1":[],"2":[]}' || fieldIds === '{}') {
            console.warn('Warning: #gfet_field_ids is empty, fields may not be saved correctly:', fieldIds);
        }
        var minLengthFieldIds = $('#gfet_min_length_field_ids').val();
        if (minLengthFieldIds === '{"1":[],"2":[]}' || minLengthFieldIds === '{}') {
            console.warn('Warning: #gfet_min_length_field_ids is empty, fields may not be saved correctly:', minLengthFieldIds);
        }
        console.log('Form submitting with values:');
        console.log('#gfet_form_ids:', $('#gfet_form_ids').val());
        console.log('#gfet_field_ids:', fieldIds);
        console.log('#gfet_min_length_field_ids:', minLengthFieldIds);
        console.log('#gfet_spam_form_ids:', $('#gfet_spam_form_ids').val());
        console.log('#gfet_spam_field_ids:', $('#gfet_spam_field_ids').val());
    });

    $(window).on('beforeunload', function() {
        console.log('Page unloading, current field map states:');
        console.log('#gfet_field_ids:', $('#gfet_field_ids').val());
        console.log('#gfet_min_length_field_ids:', $('#gfet_min_length_field_ids').val());
    });
});