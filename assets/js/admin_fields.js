jQuery(document).ready(function($) {
    console.log('admin_fields.js loaded');

    // Function to initialize form selectors based on active tab
    function initializeFormSelectors(activeTab) {
        // Email Form & Field Mapping (Settings tab)
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
                    updateFieldSelectors(formIds, '#gfet_field_selector_container', 'gfet_field_ids');
                });

                var initialFormIds = $emailFormIdsInput.val() ? $emailFormIdsInput.val().split(',').filter(id => id) : [];
                console.log('Initial email form IDs:', initialFormIds);
                updateFieldSelectors(initialFormIds, '#gfet_field_selector_container', 'gfet_field_ids');
            } else {
                console.log('Email selector or input not found on settings tab');
            }
        }

        // Spam Form & Field Mapping (Spam Terms tab)
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
                    updateFieldSelectors(formIds, '#gfet_spam_field_selector_container', 'gfet_spam_field_ids');
                });

                var initialSpamFormIds = $spamFormIdsInput.val() ? $spamFormIdsInput.val().split(',').filter(id => id) : [];
                console.log('Initial spam form IDs:', initialSpamFormIds);
                updateFieldSelectors(initialSpamFormIds, '#gfet_spam_field_selector_container', 'gfet_spam_field_ids');
            } else {
                console.log('Spam selector or input not found on spam-terms tab');
            }
        }
    }

    // Determine active tab from URL or default to 'settings'
    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get('tab') || 'settings';
    console.log('Active tab on load:', activeTab);
    initializeFormSelectors(activeTab);

    // Re-initialize on tab click
    $('.nav-tab-wrapper a').on('click', function(e) {
        var newTab = $(this).attr('href').split('tab=')[1] || 'settings';
        console.log('Tab clicked, switching to:', newTab);
        window.history.pushState({}, document.title, '?page=gf-enhanced-tools&tab=' + newTab);
        setTimeout(function() {
            initializeFormSelectors(newTab);
        }, 100); // Delay to ensure DOM updates
    });

    function updateFieldSelectors(formIds, containerSelector, hiddenInputId) {
        var $container = $(containerSelector);
        $container.empty();
        var $formIdsInput = $('#' + hiddenInputId);
        if (!$formIdsInput.length) {
            console.error('Hidden input #' + hiddenInputId + ' not found');
            return;
        }
        var fieldIdsMapRaw = $formIdsInput.val() || '[]';
        var fieldIdsMap = JSON.parse(fieldIdsMapRaw);
        if (!fieldIdsMap || typeof fieldIdsMap !== 'object') {
            console.warn('Field IDs map is not an object, resetting to empty object:', fieldIdsMapRaw);
            fieldIdsMap = {};
        }
        console.log('Loaded field IDs map for #' + hiddenInputId + ':', fieldIdsMap);

        formIds.forEach(function(formId) {
            if (!formId) return;

            var $formSection = $('<div class="gfet-form-section"><h4>Form ID: ' + formId + '</h4></div>');
            var $select = $('<select multiple="multiple" class="gfet-field-selector" data-form-id="' + formId + '" style="width:100%;"></select>');

            $formSection.append($select);
            $container.append($formSection);

            $.ajax({
                url: gfetAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'gfet_get_form_fields',
                    form_id: formId,
                    nonce: gfetAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var fieldIdArray = Array.isArray(fieldIdsMap[formId]) ? fieldIdsMap[formId].map(String) : [];
                        console.log('Field IDs for form ' + formId + ':', fieldIdArray);
                        response.data.forEach(function(field) {
                            $select.append($('<option>', {
                                value: field.id,
                                text: field.label + ' (ID: ' + field.id + ')',
                                selected: fieldIdArray.includes(String(field.id))
                            }));
                        });
                        $select.select2({
                            placeholder: 'Select fields',
                            allowClear: true
                        }).val(fieldIdArray).trigger('change'); // Set initial values for Select2
                        $select.on('change', function() {
                            updateFieldIdsMap();
                            console.log('Field selector changed for #' + hiddenInputId + ', updated value:', $formIdsInput.val());
                        });
                    }
                },
                error: function() {
                    $formSection.append('<p>Error loading fields.</p>');
                }
            });
        });

        function updateFieldIdsMap() {
            var newFieldIdsMap = {};
            $('.gfet-field-selector').each(function() {
                var formId = $(this).data('form-id');
                newFieldIdsMap[formId] = $(this).val() || [];
            });
            $formIdsInput.val(JSON.stringify(newFieldIdsMap));
            console.log('Updated field IDs map for #' + hiddenInputId + ':', newFieldIdsMap);
        }

        updateFieldIdsMap();
    }

    // Log form submission for debugging
    $('form').on('submit', function() {
        console.log('Form submitting with values:');
        console.log('#gfet_form_ids:', $('#gfet_form_ids').val());
        console.log('#gfet_field_ids:', $('#gfet_field_ids').val());
        console.log('#gfet_spam_form_ids:', $('#gfet_spam_form_ids').val());
        console.log('#gfet_spam_field_ids:', $('#gfet_spam_field_ids').val());
    });
});