/**
 * Career Preferences — Tom Select init for the jobseeker preferences form.
 * Safe against double-init and the dashboard's AJAX region swaps
 * (content is replaced via `cn:region-updated`, not DOMContentLoaded).
 */
(function () {
    'use strict';

    if (typeof window.TomSelect === 'undefined') {
        return;
    }

    var TAG_SELECT_IDS = [
        'prefs-job-titles',
        'prefs-locations',
        'prefs-employment-types',
        'prefs-work-arrangements',
        'prefs-industries'
    ];

    function cleanTag(value) {
        return String(value).trim().replace(/\s+/g, ' ').slice(0, 100);
    }

    function initTagSelect(el) {
        if (el.tomselect) {
            return;
        }

        var ts = new TomSelect(el, {
            persistOptions: false,
            hideSelected: false,
            create: true,
            createOnBlur: true,
            allowEmptyOption: false,
            delimiter: ',',
            plugins: ['remove_button'],
            labelField: 'label',
            valueField: 'value',
            searchField: ['label'],
            placeholder: el.dataset.cnPlaceholder || 'Type to search or add…',
            maxItems: parseInt(el.dataset.cnMax || '0', 10) || null,
            onCreate: function (input) {
                var value = cleanTag(input);
                var lower = value.toLowerCase();
                var options = ts.options || {};

                for (var key in options) {
                    if (String(options[key].label || options[key].value).trim().toLowerCase() === lower) {
                        return options[key];
                    }
                }

                return { value: value, label: value };
            },
            onItemAdd: function (value) {
                var lower = String(value).trim().toLowerCase();
                var items = ts.getValue();

                items.forEach(function (item) {
                    if (item !== value && String(item).trim().toLowerCase() === lower) {
                        ts.removeItem(item);
                    }
                });
            }
        });
    }

    function initAll() {
        TAG_SELECT_IDS.forEach(function (id) {
            var el = document.getElementById(id);

            if (el && el.form && el.form.matches('[data-cn-career-preferences]')) {
                initTagSelect(el);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('cn:region-updated', initAll);

    // Collapsible "Additional preferences" section.
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.cn-prefs-extra-toggle');

        if (!toggle) {
            return;
        }

        var panel = document.getElementById('prefs-extra-fields');

        if (!panel) {
            return;
        }

        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        panel.hidden = expanded;
        toggle.querySelector('i').className = expanded ? 'fas fa-plus' : 'fas fa-minus';
    });
})();
