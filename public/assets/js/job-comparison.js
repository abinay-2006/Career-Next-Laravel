/*
 * Job Offer Comparison Lab.
 *
 * Every number on this page is calculated by the server. This script moves the
 * sliders, asks the server to re-score, and paints what comes back - it never
 * computes a score itself. Handlers are delegated from document so they survive
 * an AJAX region swap.
 */
(function () {
    'use strict';

    if (window.__cnJobComparisonReady) return;
    window.__cnJobComparisonReady = true;

    const WEIGHT_KEYS = ['salary', 'location', 'work_life_balance', 'career_growth', 'skill_match'];
    const NOT_ENOUGH = 'Not enough information to compare';

    // The shared-link query is adopted once by the server, so a refresh asks for
    // the address without it - otherwise jobs the seeker just removed come back.
    const canonicalPageUrl = (function () {
        const url = new URL(window.location.href);
        url.searchParams.delete('jobs');
        return url.toString();
    })();

    function scope() {
        return document.querySelector('.cn-cmp-page');
    }

    function notify(type, message) {
        if (typeof window.showAlert === 'function') window.showAlert(type, message);
    }

    function text(element, value) {
        if (element) element.textContent = value;
    }

    function fill(element, children) {
        if (!element) return;

        element.textContent = '';
        children.forEach((child) => {
            if (child) element.append(child);
        });
    }

    function make(tag, className, content) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (content !== undefined && content !== null) element.textContent = content;
        return element;
    }

    function factorLabel(key) {
        const slider = document.querySelector('label[for="cnCmpWeight_' + key + '"]');
        if (slider) return slider.textContent.trim();

        return key;
    }

    function jobTitle(jobId) {
        const title = document.querySelector('[data-cmp-column="' + jobId + '"] .cn-cmp-col-title');
        return title ? title.textContent.trim() : '';
    }

    function jobHref(jobId) {
        const title = document.querySelector('[data-cmp-column="' + jobId + '"] .cn-cmp-col-title');
        return title ? title.getAttribute('href') : null;
    }

    function scoreNodes(score) {
        if (score.personalized === null || score.personalized === undefined) {
            return [make('small', null, score.status === 'no_priorities' ? 'No priorities set' : 'Not enough data')];
        }

        return [make('b', null, score.personalized), make('small', null, 'of 100')];
    }

    function totalNodes(score, jobId) {
        if (score.personalized === null || score.personalized === undefined) {
            return [make('span', 'cn-cmp-unrated', score.message)];
        }

        const nodes = [make('strong', null, score.personalized)];
        const used = make('span', 'cn-cmp-used');

        WEIGHT_KEYS.forEach((key) => {
            const point = score.points && score.points[key];
            if (!point || !point.share || point.share <= 0) return;

            const chip = make('span', null, factorLabel(key) + ' ' + point.share + '%');
            chip.setAttribute('data-cmp-used', key + '-' + jobId);
            used.append(chip);
        });

        nodes.push(used);
        return nodes;
    }

    /**
     * Paint the server's verdict over the table, the results panel and the
     * priority sliders.
     */
    function applyResults(results, weights) {
        const page = scope();
        if (!page || !results) return;

        Object.entries(results.jobs || {}).forEach(([jobId, score]) => {
            fill(page.querySelector('[data-cmp-score="' + jobId + '"]'), scoreNodes(score));

            const cell = page.querySelector('[data-cmp-total="' + jobId + '"]');
            if (cell) fill(cell, totalNodes(score, jobId));
        });

        Object.entries(results.shares || {}).forEach(([key, share]) => {
            // The slider already sits under a "Comparison Weight" caption, so it
            // answers with the number alone; the table row has no caption and keeps
            // the words.
            text(page.querySelector('[data-cmp-weight-share="' + key + '"]'), share + '%');
            text(page.querySelector('[data-cmp-weight-share-label="' + key + '"]'), share + '% weight');

            const bar = page.querySelector('[data-cmp-weight-bar="' + key + '"]');
            if (bar) bar.style.width = share + '%';
        });

        Object.entries(weights || {}).forEach(([key, value]) => {
            text(page.querySelector('[data-cmp-weight-value="' + key + '"]'), value);

            const slider = page.querySelector('#cnCmpWeight_' + key);
            if (slider && Number(slider.value) !== Number(value)) slider.value = value;
        });

        page.querySelectorAll('[data-cmp-column]').forEach((column) => column.classList.remove('is-best'));

        const highest = results.highest;
        const winner = page.querySelector('[data-cmp-winner]');

        if (highest && highest.job_id) {
            const best = page.querySelector('[data-cmp-column="' + highest.job_id + '"]');
            if (best) best.classList.add('is-best');

            if (winner) {
                winner.hidden = false;

                const link = winner.querySelector('[data-cmp-winner-link]');
                if (link) {
                    link.textContent = highest.label;
                    const href = jobHref(highest.job_id);
                    if (href) link.setAttribute('href', href);
                }
            }
        } else if (winner) {
            winner.hidden = true;
        }

        text(page.querySelector('[data-cmp-explanation]'), results.explanation || '');

        const message = page.querySelector('[data-cmp-message]');
        if (message) {
            message.textContent = results.message || '';
            message.hidden = !results.message;
        }

        page.querySelectorAll('[data-cmp-highlight]').forEach((item) => {
            const jobId = (results.highlights || {})[item.getAttribute('data-cmp-highlight')];
            const title = jobId ? jobTitle(jobId) : '';

            text(item.querySelector('[data-cmp-highlight-job]'), title || NOT_ENOUGH);
            item.classList.toggle('is-void', !title);
        });
    }

    /* ───────────────────────── priority sliders ───────────────────────── */

    let inFlight = false;
    let dirty = false;
    let debounce = null;

    function weightsForm() {
        return document.querySelector('[data-cmp-weights-form]');
    }

    function postWeights(form) {
        if (!form) return;

        // A change that lands while a re-score is in flight is queued, so the
        // last slider position always wins.
        if (inFlight) {
            dirty = true;
            return;
        }

        inFlight = true;
        dirty = false;
        form.requestSubmit();
    }

    document.addEventListener('input', (event) => {
        const slider = event.target;
        if (!(slider instanceof HTMLInputElement) || !slider.hasAttribute('data-cmp-weight')) return;
        if (WEIGHT_KEYS.indexOf(slider.name) === -1) return;

        // Only the slider's own value is echoed; shares and scores come from the
        // server, never from here.
        text(document.querySelector('[data-cmp-weight-value="' + slider.name + '"]'), slider.value);

        window.clearTimeout(debounce);
        debounce = window.setTimeout(() => postWeights(weightsForm()), 220);
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;

        const form = weightsForm();

        if (event.target.closest('[data-cmp-reset]') && form) {
            event.preventDefault();

            window.clearTimeout(debounce);

            const flag = form.querySelector('input[name="reset"]');
            if (flag) flag.value = '1';

            inFlight = true;
            dirty = false;
            form.requestSubmit();

            if (flag) flag.value = '0';
            return;
        }

        if (event.target.closest('[data-cmp-print]')) {
            event.preventDefault();
            window.print();
            return;
        }

        const copy = event.target.closest('[data-cmp-copy]');
        if (copy) {
            event.preventDefault();
            copyLink(copy.getAttribute('data-cmp-link') || window.location.href);
        }
    });

    function copyLink(link) {
        const done = () => notify('success', 'Comparison link copied. Anyone signed in to your account can open it.');

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(done).catch(() => fallbackCopy(link, done));
            return;
        }

        fallbackCopy(link, done);
    }

    function fallbackCopy(link, done) {
        const field = make('textarea');
        field.value = link;
        field.setAttribute('readonly', 'readonly');
        field.setAttribute('aria-hidden', 'true');
        field.style.position = 'fixed';
        field.style.top = '-1000px';
        document.body.append(field);
        field.select();

        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch {
            copied = false;
        }

        field.remove();

        if (copied) {
            done();
            return;
        }

        notify('error', 'The link could not be copied. Copy it from the address bar instead.');
    }

    /* ─────────────────────── add / remove toggles ─────────────────────── */

    function markToggle(form, inComparison) {
        const button = form.querySelector('button');
        if (!button) return;

        button.classList.toggle('in-compare', inComparison);
        button.setAttribute('aria-pressed', inComparison ? 'true' : 'false');

        text(button.querySelector('[data-cmp-toggle-label]'), inComparison ? 'In comparison' : 'Add to Compare');

        const icon = button.querySelector('i');
        if (icon) icon.className = 'fas ' + (inComparison ? 'fa-check' : 'fa-scale-balanced');
    }

    /**
     * Bring the table in line with the session after a job is added or removed
     * from this page. Only the page fragment is swapped.
     */
    async function refreshPage() {
        const page = scope();
        if (!page) return;

        try {
            const response = await fetch(canonicalPageUrl, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('unexpected response');

            const next = new DOMParser()
                .parseFromString(await response.text(), 'text/html')
                .querySelector('.cn-cmp-page');

            if (next) page.replaceWith(next);
        } catch {
            notify('error', 'Your comparison changed, but the table could not refresh. Reload the page to see it.');
        }
    }

    /* ─────────────────────── comparison is full ─────────────────────── */

    let lastFocused = null;

    function openDialog(dialog, pendingJob, entries) {
        lastFocused = document.activeElement;

        text(dialog.querySelector('[data-cmp-pending]'), (pendingJob && pendingJob.title) || 'this job');

        dialog.querySelectorAll('[data-cmp-replace-with]').forEach((field) => {
            field.value = pendingJob && pendingJob.id ? pendingJob.id : '';
        });

        if (Array.isArray(entries)) renderSlots(dialog, entries);

        dialog.hidden = false;

        const first = dialog.querySelector('button');
        if (first) first.focus();
    }

    function closeDialog(dialog) {
        dialog.hidden = true;

        if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
        lastFocused = null;
    }

    /**
     * The four slots are whatever the session holds right now, so the list is
     * rebuilt from the inert template each time the dialog opens.
     */
    function renderSlots(dialog, entries) {
        const list = dialog.querySelector('[data-cmp-dialog-list]');
        const rowTemplate = document.querySelector('[data-cmp-row-template]');
        if (!list || !rowTemplate) return;

        const incoming = dialog.querySelector('[data-cmp-replace-with]');
        const value = incoming ? incoming.value : '';

        list.textContent = '';

        entries.forEach((entry) => {
            const row = rowTemplate.content.firstElementChild;
            if (!row) return;

            const clone = row.cloneNode(true);

            text(clone.querySelector('[data-cmp-row-title]'), entry.title || 'Job ' + entry.id);

            const company = clone.querySelector('[data-cmp-row-company]');
            if (company) {
                company.textContent = entry.company || '';
                company.hidden = !entry.company;
            }

            const form = clone.querySelector('form');
            if (form) form.action = form.action.replace('__JOB__', entry.id);

            const field = clone.querySelector('[data-cmp-replace-with]');
            if (field) field.value = value;

            list.append(clone);
        });
    }

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;

        const dialog = document.querySelector('[data-cmp-dialog]');
        if (!dialog) return;

        if (event.target.closest('[data-cmp-dialog-close]') || event.target === dialog) {
            closeDialog(dialog);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        const dialog = document.querySelector('[data-cmp-dialog]:not([hidden])');
        if (dialog) closeDialog(dialog);
    });

    /* ───────────────────── one place for AJAX replies ───────────────────── */

    document.addEventListener('cn:ajax-success', (event) => {
        const payload = event.detail || {};
        const form = event.target;

        if (form instanceof HTMLFormElement && form.matches('[data-cmp-weights-form]')) {
            inFlight = false;
            applyResults(payload.results, payload.weights);
            if (dirty) postWeights(form);
            return;
        }

        if (form instanceof HTMLFormElement && form.matches('[data-cmp-toggle]')) {
            if (payload.state === 'added' || payload.state === 'duplicate') {
                markToggle(form, true);
                refreshPage();
                return;
            }

            if (payload.state === 'removed') {
                markToggle(form, false);
                refreshPage();
                return;
            }

            if (payload.state === 'full') {
                const dialog = document.querySelector('[data-cmp-dialog]');

                if (dialog) {
                    openDialog(dialog, payload.added, payload.entries);
                    return;
                }

                notify('info', payload.message);
                return;
            }

            return;
        }

        if (payload.results) applyResults(payload.results, payload.weights);
    });

    /* ─────────────────────────── scroll cue ─────────────────────────── */

    function updateScrollHint(scroller) {
        const hint = document.querySelector('[data-cmp-scrollhint]');
        if (!hint) return;

        const atEnd = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 4;
        hint.classList.toggle('is-hidden', atEnd || scroller.scrollWidth <= scroller.clientWidth);
    }

    document.addEventListener('scroll', (event) => {
        const scroller = event.target;
        if (!(scroller instanceof HTMLElement) || !scroller.classList.contains('cn-cmp-scroller')) return;

        updateScrollHint(scroller);
    }, true);

    function boot() {
        document.querySelectorAll('.cn-cmp-scroller').forEach(updateScrollHint);
    }

    document.addEventListener('cn:region-updated', boot);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
