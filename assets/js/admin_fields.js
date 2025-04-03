jQuery(function($) {
    const $formSelector = $('#gfet_form_selector');
    const $fieldSelectorContainer = $('#gfet_field_selector_container');
    const $formIdsInput = $('#gfet_form_ids');
    const $fieldMapInput = $('#gfet_field_ids');

    let fieldMap = JSON.parse($fieldMapInput.val() || '{}');

    // Initialize Select2
    $formSelector.select2({
        placeholder: 'Select forms to configure',
        width: 'resolve'
    });

    /**
     * Renders the field dropdown for a given form
     */
    function renderFieldSelect(formId, savedFieldId, formTitle) {
        const $wrap = $('<div class="gfet-field-wrap" data-form-id="' + formId + '">')
            .append(`<label for="gfet_field_select_${formId}">Field for "<strong>${formTitle}</strong>":</label>`)
            .append(`<select class="gfet-field-select" id="gfet_field_select_${formId}" data-form-id="${formId}"><option>Loading...</option></select>`);

        $fieldSelectorContainer.append($wrap);

        $.post(gfetAjax.ajaxurl, {
            action: 'gfet_get_form_fields',
            nonce: gfetAjax.nonce,
            form_id: formId
        }, function(res) {
            if (res.success) {
                const $select = $wrap.find('select');
                $select.empty().append('<option value="">-- Select Field --</option>');
                res.data.forEach(f => {
                    const selected = (f.id == savedFieldId) ? 'selected' : '';
                    $select.append(`<option value="${f.id}" ${selected}>${f.label} (ID ${f.id})</option>`);
                });
            } else {
                $wrap.append(`<p style="color:red;">Error loading fields for form ${formId}</p>`);
            }
        });
    }

    /**
     * Rebuild the field selector area for currently selected forms
     */
    function rebuildFieldSelectors(formIds) {
        $fieldSelectorContainer.empty();

        formIds.forEach(formId => {
            const savedFieldId = fieldMap[formId] || '';
            const formTitle = $formSelector.find(`option[value="${formId}"]`).text();
            renderFieldSelect(formId, savedFieldId, formTitle);
        });
    }

    /**
     * On form selector change
     */
    $formSelector.on('change', function() {
        const selectedForms = $(this).val() || [];
        $formIdsInput.val(selectedForms.join(','));
    
        // Prune the map to only selected forms
        const newMap = {};
        selectedForms.forEach(formId => {
            if (fieldMap[formId]) {
                newMap[formId] = fieldMap[formId];
            }
        });
    
        fieldMap = newMap;
        $fieldMapInput.val(JSON.stringify(fieldMap));
    
        rebuildFieldSelectors(selectedForms);
    });

    /**
     * On individual field dropdown change
     */
    $fieldSelectorContainer.on('change', '.gfet-field-select', function() {
        const formId = $(this).data('form-id');
        const fieldId = $(this).val();
    
        fieldMap[formId] = fieldId;
        $fieldMapInput.val(JSON.stringify(fieldMap));
    });

    /**
     * Initial page load setup
     */
    const initialForms = $formIdsInput.val().split(',').filter(Boolean);
    rebuildFieldSelectors(initialForms);
});
