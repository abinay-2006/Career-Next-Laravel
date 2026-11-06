/*
 * Career Next interview scheduling: the three things a page cannot do in Blade.
 *
 * One is the confirmation dialog in front of the actions that are hard to take back
 * (confirm, decline, cancel, complete). Each of those forms is already a plain POST
 * that works with scripting off; this only interposes a question, and the answer is
 * read from the form's own data-cn-confirm text so a page can never disagree with its
 * button. Another is the interview-type switch, which shows the meeting link or the
 * address instead of making the employer guess which field the server will demand.
 * The third is the calendar, because no server-side markup can tell whether this
 * browser is even able to open one.
 *
 * Progressive enhancement only: nothing here decides what is allowed. The gate the
 * controller computed is the authority, and the unique open-invitation index still
 * refuses a second live invitation for the same round if this file never runs.
 */
(function () {
    'use strict';

    if (window.__cnInterviewReady) {
        return;
    }
    window.__cnInterviewReady = true;

    var TYPE_FIELDS = {
        online: 'meeting_link',
        in_person: 'location',
        phone: null
    };

    // Everything below only ever touches forms that say they belong to this feature;
    // the role pages carry other forms, including AJAX ones, that must be left alone.
    function interviewForm(event)
    {
        var form = event.target;

        return form && form.matches && form.matches('[data-cn-int-form]') ? form : null;
    }

    /* ── Interview type ────────────────────────────────────────────────────── */

    /**
     * Reveal only the field the chosen type needs, and move the pressed look onto the
     * chip that now matches the radio. Both are re-read from the live form instead of
     * cached, because a rejected submission comes back with the employer's earlier
     * choice already picked.
     */
    function syncType(form)
    {
        var picked = form.querySelector('[data-cn-int-type]:checked');
        var show = null;

        if (picked) {
            var type = picked.getAttribute('data-cn-int-type');
            show = Object.prototype.hasOwnProperty.call(TYPE_FIELDS, type) ? TYPE_FIELDS[type] : null;
        }

        Array.prototype.forEach.call(form.querySelectorAll('[data-cn-int-field]'), function (block) {
            block.classList.toggle('is-hidden', block.getAttribute('data-cn-int-field') !== show);
        });

        Array.prototype.forEach.call(form.querySelectorAll('.cn-int-type-chip'), function (chip) {
            var input = chip.querySelector('[data-cn-int-type]');

            chip.classList.toggle('is-checked', !!input && input.checked);
        });
    }

    /* ── Calendar ──────────────────────────────────────────────────────────── */

    // Firefox reports the type it was given while drawing nothing but a text box, so
    // the probe checks both the type it settles on and the API it answers with.
    var hasPicker = (function () {
        var probe = document.createElement('input');

        probe.type = 'datetime-local';

        return probe.type === 'datetime-local' && typeof probe.showPicker === 'function';
    })();

    function pickerFields(scope)
    {
        var nodes = [];

        if (!scope || !scope.querySelectorAll) {
            return nodes;
        }

        if (scope.matches && scope.matches('[data-cn-int-picker]')) {
            nodes.push(scope);
        }

        Array.prototype.forEach.call(scope.querySelectorAll('[data-cn-int-picker]'), function (button) {
            nodes.push(button);
        });

        return nodes;
    }

    /**
     * The button arrives in the markup hidden, because a calendar that cannot open is
     * worse than no calendar at all. Revealing it is a no-op where the browser has no
     * date control, so a rejected submission and a swapped-in panel both come back with
     * the button only when it actually does something.
     */
    function showPickerButtons(scope)
    {
        if (!hasPicker) {
            return;
        }

        pickerFields(scope).forEach(function (button) {
            button.hidden = false;
        });
    }

    function fieldOf(button)
    {
        return document.getElementById(button.getAttribute('data-cn-int-picker'));
    }

    function openPicker(input)
    {
        if (!input || typeof input.showPicker !== 'function') {
            return;
        }

        try {
            input.showPicker();
        } catch (error) {
            // showPicker() only answers to a gesture the browser trusts; the glyph
            // inside the field is still there for that case.
        }
    }

    /* ── Confirmation dialog ───────────────────────────────────────────────── */

    var dialog = null;
    var box = null;
    var pending = null;
    var lastFocus = null;

    function build()
    {
        dialog = document.createElement('div');
        dialog.className = 'cn-int-dialog';
        dialog.hidden = true;

        box = document.createElement('div');
        box.className = 'cn-int-dialog-box';
        box.setAttribute('role', 'alertdialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-labelledby', 'cnIntDialogTitle');

        var title = document.createElement('h3');
        title.id = 'cnIntDialogTitle';
        title.textContent = 'Before you continue';

        var text = document.createElement('p');
        text.className = 'cn-int-dialog-text';
        text.setAttribute('data-cn-int-dialog-text', '');

        var actions = document.createElement('div');
        actions.className = 'cn-int-dialog-actions';

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'cn-int-btn-quiet';
        cancel.textContent = 'Keep it as it is';
        cancel.setAttribute('data-cn-int-dialog-cancel', '');

        var ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'cn-int-btn-primary';
        ok.textContent = 'Yes, continue';
        ok.setAttribute('data-cn-int-dialog-ok', '');

        actions.appendChild(cancel);
        actions.appendChild(ok);
        box.appendChild(title);
        box.appendChild(text);
        box.appendChild(actions);
        dialog.appendChild(box);
        document.body.appendChild(dialog);

        // The backdrop is only the backdrop, so a click beside the box answers "no"
        // instead of leaving the question hanging over the page.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                close();
            }
        });

        cancel.addEventListener('click', function () {
            pending = null;
            close();
        });

        ok.addEventListener('click', function () {
            var form = pending;

            pending = null;
            close();

            if (form) {
                // The second pass through the submit handler sees this marker, lets the
                // request through, and disables the buttons.
                form.setAttribute('data-cn-confirmed', '1');
                form.requestSubmit();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!dialog || dialog.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                pending = null;
                close();

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var focusable = box.querySelectorAll('button');

            if (!focusable.length) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    function open(message, form)
    {
        if (!dialog) {
            build();
        }

        pending = form;
        lastFocus = document.activeElement;
        box.querySelector('[data-cn-int-dialog-text]').textContent = message;
        dialog.hidden = false;
        box.querySelector('[data-cn-int-dialog-ok]').focus();
    }

    function close()
    {
        if (dialog) {
            dialog.hidden = true;
        }

        if (lastFocus && document.contains(lastFocus)) {
            lastFocus.focus();
        }

        lastFocus = null;
    }

    /* ── One press, one request ────────────────────────────────────────────── */

    function lock(form)
    {
        var buttons = form.querySelectorAll('button[type=submit], button:not([type])');

        Array.prototype.forEach.call(buttons, function (button) {
            button.setAttribute('data-cn-was-enabled', '1');
            button.disabled = true;
        });

        // If the response never arrives, the page is still here and still usable.
        setTimeout(function () {
            if (!document.contains(form)) {
                return;
            }

            Array.prototype.forEach.call(buttons, function (button) {
                button.disabled = false;
                button.removeAttribute('data-cn-was-enabled');
            });
        }, 15000);
    }

    /* ── Wiring ────────────────────────────────────────────────────────────── */

    // Delegated, because the role dashboards swap regions without reloading the page
    // and a node looked up once would belong to a page that no longer exists.
    document.addEventListener('submit', function (event) {
        var form = interviewForm(event);

        if (!form) {
            return;
        }

        if (form.hasAttribute('data-cn-confirmed')) {
            form.removeAttribute('data-cn-confirmed');
            lock(form);

            return;
        }

        var message = form.getAttribute('data-cn-confirm');

        // Constraint validation has already run by the time a submit event exists, so
        // an unanswered form here is genuinely ready to go.
        if (message !== null) {
            event.preventDefault();
            open(message, form);

            return;
        }

        lock(form);
    });

    document.addEventListener('change', function (event) {
        var target = event.target;

        if (target && target.getAttribute && target.getAttribute('data-cn-int-type')) {
            syncType(target.closest('[data-cn-int-form]') || target.closest('form'));
        }
    });

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        var button = target.closest('[data-cn-int-picker]');

        if (button) {
            openPicker(fieldOf(button));
        }
    });

    function boot()
    {
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-cn-int-form]:has([data-cn-int-type])'),
            syncType
        );

        showPickerButtons(document);

        // The employer dashboard swaps regions instead of reloading the page, so a form
        // that arrives after this file has run still gets its calendar button.
        if (window.MutationObserver && document.body) {
            new MutationObserver(function (records) {
                records.forEach(function (record) {
                    Array.prototype.forEach.call(record.addedNodes, function (node) {
                        if (node.nodeType === 1) {
                            showPickerButtons(node);
                        }
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
