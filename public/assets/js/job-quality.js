/*
 * Job Quality Checker.
 *
 * The score is calculated by JobQualityService on the server. This script only
 * collects the fields the employer has typed, asks the endpoint to score them,
 * and paints the reply - it never adds up points itself, so a tampered or stale
 * number in the page cannot become the result.
 *
 * Handlers sit on document and match by attribute, which is what lets the panel
 * keep working after an AJAX swap replaces .cn-dashboard-main.
 */
(function () {
    'use strict';

    if (window.__cnJobQualityReady) {
        return;
    }

    window.__cnJobQualityReady = true;

    // Typing scores the posting on a pause, not on every keystroke, and a change
    // that lands while a reply is in flight is queued instead of dropped.
    var DEBOUNCE_MS = 550;
    var QUEUE_KEYS = ['txtTitle', 'txtTotal', 'txtSalaryFrom', 'txtSalaryTo', 'cmbQual', 'txtOther',
        'cmbExperience', 'cmbArrangement', 'cmbEmployment', 'txtDesc', 'required_skills', 'required_software'];
    var PICKER_FIELDS = ['required_skills', 'required_software'];

    var sessions = new WeakMap();

    function shellFor(form) {
        return form.querySelector('[data-cn-quality-shell]');
    }

    function session(form) {
        var state = sessions.get(form);

        if (!state) {
            state = { timer: null, inFlight: false, queued: false, lastSent: null, controller: null, report: null };
            sessions.set(form, state);
        }

        return state;
    }

    function pickFields(form) {
        var data = new FormData(form);
        var payload = {};

        QUEUE_KEYS.forEach(function (key) {
            if (data.has(key)) {
                payload[key] = String(data.get(key) || '');
            }
        });

        var jobId = form.querySelector('input[name="JobId"]');

        if (jobId && jobId.value) {
            payload.JobId = jobId.value;
        }

        return payload;
    }

    function isBlank(payload) {
        return Object.keys(payload).every(function (key) {
            return key === 'JobId' || payload[key].trim() === '';
        });
    }

    function showEmpty(shell) {
        shell.classList.remove('has-report', 'is-busy');
        shell.querySelector('[data-cn-quality-empty]').hidden = false;
        shell.querySelector('[data-cn-quality-busy]').hidden = true;
        shell.querySelector('[data-cn-quality-score]').textContent = '–';
        shell.querySelector('[data-cn-quality-band]').textContent = 'Waiting for your first edit';
        shell.removeAttribute('data-band');

        ['completed', 'warnings', 'suggestions', 'missing'].forEach(function (name) {
            shell.querySelector('[data-cn-quality-' + name + '-wrap]').hidden = true;
        });
    }

    function fieldElement(form, field) {
        if (!field) {
            return null;
        }

        if (PICKER_FIELDS.indexOf(field) !== -1) {
            var picker = form.querySelector('[data-cn-req-picker="' + field + '"]');

            if (!picker) {
                return null;
            }

            return picker.querySelector('.ts-control input') || picker.querySelector('select') || picker;
        }

        return form.querySelector('[name="' + field + '"]');
    }

    function focusField(event) {
        var jump = event.target instanceof Element ? event.target.closest('[data-cn-quality-focus]') : null;

        if (!jump) {
            return;
        }

        event.preventDefault();

        var shell = jump.closest('[data-cn-quality-shell]');
        var form = shell ? shell.closest('form') : null;
        var field = jump.getAttribute('data-cn-quality-focus');
        var target = form ? fieldElement(form, field) : null;

        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (typeof target.focus === 'function') {
            target.focus({ preventScroll: true });
        }

        var group = target.closest('.form-group');

        if (group) {
            group.classList.remove('cn-quality-flash');
            void group.offsetWidth;
            group.classList.add('cn-quality-flash');
            window.setTimeout(function () { group.classList.remove('cn-quality-flash'); }, 1400);
        }
    }

    function item(label, detail, tone, icon, field) {
        var li = document.createElement('li');
        li.className = 'cn-quality-item' + (tone ? ' is-' + tone : '');

        var mark = document.createElement('i');
        mark.className = 'fas ' + icon;
        mark.setAttribute('aria-hidden', 'true');
        li.append(mark);

        var text = document.createElement('span');
        text.className = 'cn-quality-item-text';

        var strong = document.createElement('span');
        strong.className = 'cn-quality-item-label';
        strong.textContent = label;
        text.append(strong);

        if (detail) {
            var note = document.createElement('span');
            note.className = 'cn-quality-item-note';
            note.textContent = detail;
            text.append(note);
        }

        li.append(text);

        if (field) {
            var jump = document.createElement('button');
            jump.type = 'button';
            jump.className = 'cn-quality-jump';
            jump.setAttribute('data-cn-quality-focus', field);
            jump.textContent = 'Fix this';
            li.append(jump);
        }

        return li;
    }

    function chip(label, field) {
        var li = document.createElement('li');
        var button = document.createElement('button');

        li.className = 'cn-quality-chip';
        button.type = 'button';
        button.textContent = label;

        if (field) {
            button.setAttribute('data-cn-quality-focus', field);
        } else {
            button.disabled = true;
        }

        li.append(button);

        return li;
    }

    function categoryRow(category) {
        var li = document.createElement('li');
        li.className = 'cn-quality-part is-' + category.state;

        var label = document.createElement('span');
        label.className = 'cn-quality-part-label';
        label.textContent = category.label;

        var track = document.createElement('span');
        track.className = 'cn-quality-part-track';

        var fill = document.createElement('i');
        fill.style.width = Math.round((category.earned / Math.max(1, category.max)) * 100) + '%';
        track.append(fill);

        var points = document.createElement('span');
        points.className = 'cn-quality-part-points';
        points.textContent = category.earned + '/' + category.max;

        li.append(label, track, points);

        return li;
    }

    function fillList(shell, name, nodes) {
        var list = shell.querySelector('[data-cn-quality-' + name + ']');
        var wrap = shell.querySelector('[data-cn-quality-' + name + '-wrap]');

        if (!list || !wrap) {
            return;
        }

        list.textContent = '';
        nodes.forEach(function (node) { list.append(node); });
        wrap.hidden = nodes.length === 0;
    }

    function render(shell, report) {
        shell.classList.add('has-report');
        shell.classList.remove('is-busy');
        shell.setAttribute('data-band', report.band);
        shell.querySelector('[data-cn-quality-empty]').hidden = true;
        shell.querySelector('[data-cn-quality-busy]').hidden = true;
        shell.querySelector('[data-cn-quality-score]').textContent = report.score;
        shell.querySelector('[data-cn-quality-fill]').style.width = report.score + '%';

        var band = shell.querySelector('[data-cn-quality-band]');
        band.textContent = report.label + ' · ' + report.counts.completed + ' checks passed, '
            + report.counts.warnings + ' to look at';
        band.className = 'cn-quality-band is-' + report.band;

        fillList(shell, 'categories', report.categories.map(categoryRow));

        fillList(shell, 'completed', report.completed.map(function (check) {
            return item(check.label, null, 'done', 'fa-circle-check');
        }));

        fillList(shell, 'warnings', report.warnings.map(function (warning) {
            var icon = warning.severity === 'critical'
                ? 'fa-circle-exclamation'
                : (warning.severity === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info');

            return item(warning.label, warning.detail, warning.severity, icon, warning.field);
        }));

        fillList(shell, 'suggestions', report.suggestions.map(function (suggestion) {
            return item(suggestion.label, null, 'idea', 'fa-arrow-right', suggestion.field);
        }));

        fillList(shell, 'missing', report.missing.map(function (entry) {
            return chip(entry.label, entry.field);
        }));
    }

    function fail(shell, message) {
        shell.classList.remove('is-busy');

        var band = shell.querySelector('[data-cn-quality-band]');
        band.textContent = message;
        band.className = 'cn-quality-band is-error';
    }

    function check(form) {
        var shell = shellFor(form);

        if (!shell) {
            return;
        }

        var state = session(form);
        var payload = pickFields(form);

        if (isBlank(payload)) {
            if (state.controller) {
                state.controller.abort();
                state.controller = null;
            }

            state.inFlight = false;
            state.lastSent = null;
            showEmpty(shell);

            return;
        }

        var encoded = JSON.stringify(payload);

        if (state.lastSent === encoded && state.report) {
            return;
        }

        if (state.inFlight) {
            state.queued = true;

            return;
        }

        var token = form.querySelector('input[name="_token"]');

        if (!token) {
            return;
        }

        var body = new FormData();

        Object.keys(payload).forEach(function (key) {
            body.append(key, payload[key]);
        });

        state.inFlight = true;
        state.lastSent = encoded;
        shell.classList.add('is-busy');
        shell.querySelector('[data-cn-quality-busy]').hidden = false;
        shell.querySelector('[data-cn-quality-empty]').hidden = true;

        if (state.controller) {
            state.controller.abort();
        }

        state.controller = new AbortController();

        fetch(shell.getAttribute('data-cn-quality-endpoint'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token.value,
            },
            credentials: 'same-origin',
            body: body,
            signal: state.controller.signal,
        })
            .then(function (response) {
                if (response.status === 419 || response.status === 401 || response.status === 403) {
                    fail(shell, 'Your session expired, so the posting could not be scored. Reload the page.');

                    return null;
                }

                return response.json().then(function (json) {
                    if (!response.ok || !json || !json.report) {
                        var first = json && json.errors ? Object.values(json.errors)[0][0] : null;

                        fail(shell, first || 'The checker could not score this posting.');

                        return null;
                    }

                    return json.report;
                });
            })
            .then(function (report) {
                if (report) {
                    state.report = report;
                    render(shell, report);
                }
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                fail(shell, 'The checker could not reach the server. Your job details are safe.');
            })
            .finally(function () {
                state.inFlight = false;
                state.controller = null;

                var busy = shell.querySelector('[data-cn-quality-busy]');

                if (busy) {
                    busy.hidden = true;
                }

                shell.classList.remove('is-busy');

                if (state.queued) {
                    state.queued = false;
                    window.setTimeout(function () { check(form); }, 60);
                }
            });
    }

    function schedule(form) {
        var state = session(form);

        window.clearTimeout(state.timer);
        state.timer = window.setTimeout(function () { check(form); }, DEBOUNCE_MS);
    }

    function formOf(target) {
        var element = target instanceof Element ? target : null;
        var form = element ? element.closest('form') : null;

        return form && form.querySelector('[data-cn-quality-shell]') ? form : null;
    }

    // The requirement pickers write their JSON into a hidden field without firing
    // an input event, so a click inside one of them counts as a change too.
    function isPickerClick(target) {
        return target instanceof Element && target.closest('.cn-req-picker') !== null;
    }

    document.addEventListener('input', function (event) {
        var form = formOf(event.target);

        if (form) {
            schedule(form);
        }
    });

    document.addEventListener('change', function (event) {
        var form = formOf(event.target);

        if (form) {
            schedule(form);
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target instanceof Element && event.target.closest('[data-cn-quality-focus]')) {
            focusField(event);

            return;
        }

        if (!isPickerClick(event.target)) {
            return;
        }

        var form = formOf(event.target);

        if (form) {
            schedule(form);
        }
    });

    document.addEventListener('reset', function (event) {
        var form = formOf(event.target);

        if (!form) {
            return;
        }

        window.setTimeout(function () {
            session(form).report = null;
            check(form);
        }, 30);
    });

    /**
     * openEditModal() fills the form by assignment, which fires no input event,
     * so the page tells the checker to score the saved listing on its own.
     */
    document.addEventListener('cn:quality-check', function (event) {
        var formId = event.detail && event.detail.form;
        var form = formId ? document.getElementById(formId) : null;

        if (form && form.querySelector('[data-cn-quality-shell]')) {
            session(form).lastSent = null;
            check(form);
        }
    });
})();
