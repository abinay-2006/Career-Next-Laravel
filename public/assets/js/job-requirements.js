/**
 * Structured Required Skills / Required Software pickers, shared by the employer
 * job forms and the job seeker's own Skills & Software section. Each container
 * renders a Tom Select tag input plus one editable row per entry and mirrors its
 * state into a hidden JSON field that the server validates strictly.
 *
 * Employers classify each entry as Mandatory/Preferred; a seeker recording what
 * they know sets data-cn-req-types="off" to drop that column.
 *
 * Safe to re-run after AJAX region swaps: containers are tracked in a WeakSet
 * and only fresh DOM nodes are initialised.
 */
(function () {
    'use strict';

    if (typeof window.TomSelect === 'undefined') {
        return;
    }

    var TYPES = ['mandatory', 'preferred'];
    var PROFICIENCIES = ['basic', 'intermediate', 'advanced', 'expert'];

    var SKILL_GROUPS = {
        'Programming': ['JavaScript', 'TypeScript', 'Python', 'Java', 'C', 'C++', 'C#', 'PHP', 'Go', 'Rust', 'Kotlin', 'Swift', 'Ruby', 'R', 'MATLAB', 'Shell Scripting'],
        'Web Frameworks': ['React', 'Angular', 'Vue.js', 'Next.js', 'Laravel', 'Django', 'Flask', 'Spring Boot', 'ASP.NET', 'Express.js', 'Node.js', 'HTML5', 'CSS3', 'Tailwind CSS'],
        'Databases': ['MySQL', 'PostgreSQL', 'MongoDB', 'Oracle SQL', 'Microsoft SQL Server', 'SQLite', 'Redis', 'Firebase', 'DynamoDB'],
        'Cloud & DevOps': ['AWS', 'Azure', 'Google Cloud', 'Docker', 'Kubernetes', 'Terraform', 'CI/CD', 'Linux', 'Nginx', 'Jenkins', 'GitHub Actions'],
        'Data & Analytics': ['Excel', 'SQL Analytics', 'Power BI', 'Tableau', 'Pandas', 'NumPy', 'Machine Learning', 'Deep Learning', 'Data Visualization', 'Statistics', 'ETL', 'Apache Spark'],
        'Design': ['UI Design', 'UX Design', 'Figma', 'Adobe XD', 'Sketch', 'Prototyping', 'Wireframing', 'Graphic Design', 'Typography', 'Photoshop', 'Illustrator'],
        'Marketing': ['SEO', 'Digital Marketing', 'Social Media Marketing', 'Content Writing', 'Email Marketing', 'Google Ads', 'Google Analytics', 'Brand Strategy', 'Copywriting'],
        'Engineering': ['AutoCAD', 'SolidWorks', 'CATIA', 'MATLAB Simulink', 'Circuit Design', 'PLC Programming', 'SCADA', 'HVAC', 'Structural Analysis', 'Lean Manufacturing'],
        'Business & Finance': ['Accounting', 'Tally', 'SAP', 'Financial Analysis', 'Budgeting', 'GST Compliance', 'Payroll', 'Salesforce', 'CRM', 'Business Analysis', 'Project Management', 'Agile / Scrum'],
        'Soft Skills': ['Communication', 'Teamwork', 'Leadership', 'Problem Solving', 'Time Management', 'Critical Thinking', 'Presentation Skills', 'Customer Handling', 'Adaptability']
    };

    var SOFTWARE_LIST = [
        'Visual Studio Code', 'Visual Studio', 'Android Studio', 'IntelliJ IDEA', 'Eclipse',
        'Adobe Photoshop', 'Adobe Illustrator', 'Figma', 'Microsoft Excel', 'Microsoft Word',
        'Power BI', 'Tableau', 'AutoCAD', 'SolidWorks', 'SAP', 'Tally', 'Salesforce',
        'Git', 'GitHub', 'Docker', 'Postman'
    ];

    var initialised = new WeakSet();

    function cleanName(value) {
        return String(value).trim().replace(/\s+/g, ' ').slice(0, 100);
    }

    function optionList(values, emptyLabel) {
        return '<option value="">' + emptyLabel + '</option>' + values.map(function (v) {
            return '<option value="' + v + '">' + v.charAt(0).toUpperCase() + v.slice(1) + '</option>';
        }).join('');
    }

    function buildPicker(container) {
        var field = container.getAttribute('data-cn-req-picker');
        var kind = container.getAttribute('data-cn-req-kind');
        var showType = container.getAttribute('data-cn-req-types') !== 'off';
        var form = container.closest('form') || container;

        var selectEl = container.querySelector('.js-req-select');
        var rowsEl = container.querySelector('.js-req-rows');
        var hiddenEl = container.querySelector('input[type="hidden"][name="' + field + '"]');

        if (!selectEl || !rowsEl || !hiddenEl) return;

        selectEl.innerHTML = (kind === 'skills'
            ? Object.keys(SKILL_GROUPS).map(function (group) {
                return '<optgroup label="' + group + '">' + SKILL_GROUPS[group].map(function (s) {
                    return '<option value="' + s + '">' + s + '</option>';
                }).join('') + '</optgroup>';
            }).join('')
            : SOFTWARE_LIST.map(function (s) {
                return '<option value="' + s + '">' + s + '</option>';
            }).join(''));

        var entries = [];
        var syncing = false;

        /*
         * Tom Select fires item_add/item_remove even for the `silent` variants,
         * so every programmatic change goes through this guard to keep the
         * handlers from editing `entries` while it is being reconciled.
         */
        function refresh() {
            syncing = true;
            ts.setValue(entries.map(function (entry) {
                return entry.name;
            }), true);
            syncing = false;

            renderRows();
        }

        var ts = new TomSelect(selectEl, {
            persistOptions: false,
            hideSelected: false,
            create: true,
            createOnBlur: true,
            delimiter: ',',
            labelField: 'label',
            valueField: 'value',
            searchField: ['label'],
            placeholder: kind === 'skills'
                ? 'Search skills or type your own…'
                : 'Search software or type your own…',
            onCreate: function (input) {
                var value = cleanName(input);
                var lower = value.toLowerCase();
                var options = ts.options || {};

                for (var key in options) {
                    if (String(options[key].label || '').trim().toLowerCase() === lower) {
                        return options[key];
                    }
                }

                return { value: value, label: value };
            }
        });

        function syncHidden() {
            hiddenEl.value = entries.length ? JSON.stringify(entries) : '';
        }

        function indexOfName(name) {
            var lower = name.toLowerCase();
            for (var i = 0; i < entries.length; i++) {
                if (entries[i].name.toLowerCase() === lower) return i;
            }
            return -1;
        }

        function newEntry(name, saved) {
            var entry = {
                name: name,
                proficiency: PROFICIENCIES.indexOf(saved && saved.proficiency) > -1
                    ? saved.proficiency
                    : null
            };

            if (showType) {
                entry.type = TYPES.indexOf(saved && saved.type) > -1 ? saved.type : 'mandatory';
            }

            return entry;
        }

        function renderRows() {
            rowsEl.innerHTML = '';

            entries.forEach(function (entry) {
                var row = document.createElement('div');
                row.className = 'cn-req-row';

                var name = document.createElement('span');
                name.className = 'cn-req-name';
                name.textContent = entry.name;

                var typeCell = null;
                var typeSel = null;

                if (showType) {
                    typeCell = document.createElement('label');
                    typeCell.className = 'cn-req-cell';
                    typeCell.innerHTML = '<span>Type</span>';
                    typeSel = document.createElement('select');
                    typeSel.className = 'form-select cn-req-type';
                    typeSel.setAttribute('aria-label', 'Requirement type for ' + entry.name);
                    typeSel.innerHTML =
                        '<option value="mandatory"' + (entry.type === 'mandatory' ? ' selected' : '') + '>Mandatory</option>' +
                        '<option value="preferred"' + (entry.type === 'preferred' ? ' selected' : '') + '>Preferred</option>';
                    typeCell.appendChild(typeSel);
                }

                var profCell = document.createElement('label');
                profCell.className = 'cn-req-cell';
                profCell.innerHTML = '<span>Proficiency</span>';
                var profSel = document.createElement('select');
                profSel.className = 'form-select cn-req-prof';
                profSel.setAttribute('aria-label', 'Proficiency for ' + entry.name);
                profSel.innerHTML = optionList(PROFICIENCIES, 'Any');
                if (entry.proficiency) profSel.value = entry.proficiency;
                profCell.appendChild(profSel);

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'cn-req-remove';
                remove.setAttribute('aria-label', 'Remove ' + entry.name);
                remove.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';

                if (typeSel) {
                    typeSel.addEventListener('change', function () {
                        var i = indexOfName(entry.name);
                        if (i > -1) {
                            entries[i].type = TYPES.indexOf(typeSel.value) > -1 ? typeSel.value : 'mandatory';
                            syncHidden();
                        }
                    });
                }

                profSel.addEventListener('change', function () {
                    var i = indexOfName(entry.name);
                    if (i > -1) {
                        entries[i].proficiency = profSel.value || null;
                        syncHidden();
                    }
                });

                remove.addEventListener('click', function () {
                    removeByName(entry.name);
                });

                row.appendChild(name);
                if (typeCell) row.appendChild(typeCell);
                row.appendChild(profCell);
                row.appendChild(remove);
                rowsEl.appendChild(row);
            });

            syncHidden();
        }

        function removeByName(name) {
            var i = indexOfName(name);
            if (i > -1) entries.splice(i, 1);

            refresh();
        }

        ts.on('item_add', function (value) {
            if (syncing) return;

            value = cleanName(value);

            if (indexOfName(value) === -1) {
                entries.push(newEntry(value));
            }

            // Re-spells a case/spacing variant back to the entry that already
            // exists, so one requirement never shows as two chips.
            refresh();
        });

        ts.on('item_remove', function (value) {
            if (syncing) return;

            removeByName(cleanName(value));
        });

        function load(initial) {
            var items = Array.isArray(initial) ? initial : [];

            entries = [];
            items.forEach(function (entry) {
                if (!entry || typeof entry.name !== 'string') return;
                var name = cleanName(entry.name);
                if (name === '' || indexOfName(name) !== -1) return;
                entries.push(newEntry(name, entry));
            });

            refresh();
        }

        /**
         * Merge names the page already knows — the skills on a saved resume —
         * without dropping anything the seeker picked or set a level for.
         */
        function add(names) {
            var changed = false;

            (Array.isArray(names) ? names : []).forEach(function (candidate) {
                var name = cleanName(candidate);
                if (name === '' || indexOfName(name) !== -1) return;
                entries.push(newEntry(name));
                changed = true;
            });

            if (changed) refresh();
        }

        function clear() {
            entries = [];
            refresh();
        }

        form.addEventListener('reset', function () {
            window.setTimeout(clear, 0);
        });

        // A full-page validation redirect re-renders the form with the typed
        // JSON already in the hidden field, so restore the rows from it.
        var initial = [];
        try {
            initial = JSON.parse(hiddenEl.value || '[]');
        } catch (error) {
            initial = [];
        }
        load(Array.isArray(initial) ? initial : []);

        container.api = { load: load, add: add, clear: clear };

        initialised.add(container);
    }

    function initAll() {
        document.querySelectorAll('[data-cn-req-picker]').forEach(function (container) {
            if (!initialised.has(container)) {
                buildPicker(container);
            }
        });
    }

    window.CareerNextRequirementPickers = {
        init: initAll,
        /** Replace a picker's entries from a saved job's JSON payload. */
        load: function (fieldSelector, items) {
            var container = document.querySelector(fieldSelector);
            if (container && container.api) container.api.load(items);
        },
        /** Merge names into a picker, keeping everything already listed. */
        add: function (fieldSelector, names) {
            var container = document.querySelector(fieldSelector);
            if (container && container.api) container.api.add(names);
        }
    };

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('cn:region-updated', initAll);
    initAll();
})();
