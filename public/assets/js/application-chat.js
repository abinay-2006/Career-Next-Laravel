/*
 * Career Next application chat: composer, polling refresh and lazy history.
 *
 * Progressive enhancement only. Every conversation already renders complete, and
 * this file only keeps it current: it polls the thread that is open, appends rows
 * built with createElement/textContent (never innerHTML, so a message can never
 * become markup), and re-disables the composer if the employer revokes approval
 * while the page is still open.
 *
 * Listeners are delegated and state is re-read from the live DOM on every tick,
 * because the role dashboards swap regions without reloading the page and a cached
 * node or a page-level id map would go stale.
 */
(function () {
    'use strict';

    if (window.__cnChatReady) {
        return;
    }
    window.__cnChatReady = true;

    // Short enough that a reply lands while both people are still looking at the
    // thread, and still well inside the route's 60-per-minute throttle.
    var POLL_MS = 2500;
    var POLL_LIMIT = 50;

    // Rebuilt whenever the open conversation changes, so a swap cannot leak cursor state.
    var current = { id: null, lastId: 0, lastPoll: 0, failures: 0, nextAt: 0 };
    var timer = null;

    /**
     * Count a failed poll and back off instead of giving up: three strikes used to
     * stop polling until the page was reloaded, which is how a brief hiccup turned
     * into a thread that looked permanently dead.
     */
    function fail(root, message)
    {
        current.failures += 1;
        current.nextAt = Date.now() + Math.min(60000, POLL_MS * Math.pow(2, current.failures));

        if (current.failures === 3) {
            setStatus(root, message, true);
        }
    }

    function panel()
    {
        return document.querySelector('[data-cn-chat]');
    }

    function scrollBox(root)
    {
        return root.querySelector('[data-chat-scroll]');
    }

    function statusLine(root)
    {
        return root.querySelector('[data-chat-status]');
    }

    function setStatus(root, text, isError)
    {
        var line = statusLine(root);
        if (!line) {
            return;
        }
        line.textContent = text || '';
        line.classList.toggle('is-error', Boolean(isError));
    }

    function messageNodes(root)
    {
        return Array.prototype.slice.call(root.querySelectorAll('[data-chat-msg]'));
    }

    function maxMessageId(root)
    {
        return messageNodes(root).reduce(function (max, node) {
            var id = parseInt(node.getAttribute('data-chat-msg'), 10) || 0;
            return id > max ? id : max;
        }, 0);
    }

    function minMessageId(root)
    {
        var ids = messageNodes(root)
            .map(function (node) { return parseInt(node.getAttribute('data-chat-msg'), 10) || 0; })
            .filter(function (id) { return id > 0; });

        return ids.length ? Math.min.apply(null, ids) : 0;
    }

    /** True when the open conversation changed, i.e. the transcript needs a poll now. */
    function syncCursor(root)
    {
        var id = root.getAttribute('data-cn-chat');

        if (current.id === id) {
            return false;
        }

        current = {
            id: id,
            lastId: maxMessageId(root),
            lastPoll: 0,
            failures: 0,
            nextAt: 0
        };

        return true;
    }

    /** One transcript row, identical in shape to what the Blade view renders. */
    function buildMessage(message)
    {
        var article = document.createElement('article');
        article.className = 'cn-chat-msg ' + (message.mine ? 'is-mine' : 'is-theirs');
        article.setAttribute('data-chat-msg', String(message.id));
        article.setAttribute('data-chat-sender', message.sender_role);

        var name = document.createElement('span');
        name.className = 'cn-chat-msg-name';
        name.textContent = message.mine ? 'You' : (message.sender_name || 'Someone');

        var bubble = document.createElement('div');
        bubble.className = 'cn-chat-msg-bubble';

        var body = document.createElement('span');
        body.className = 'cn-chat-msg-body';
        // textContent is the whole defence here: the server already stripped tags, and
        // this keeps anything left from ever being parsed as markup.
        body.textContent = message.body;
        bubble.appendChild(body);

        var foot = document.createElement('div');
        foot.className = 'cn-chat-msg-foot';

        var time = document.createElement('time');
        time.className = 'cn-chat-msg-time';
        if (message.sent_at) {
            time.setAttribute('datetime', message.sent_at);
        }
        time.textContent = message.sent_label || 'Just now';
        foot.appendChild(time);

        if (message.mine) {
            var receipt = document.createElement('span');
            receipt.className = 'cn-chat-msg-receipt';
            receipt.setAttribute('data-chat-receipt', String(message.id));
            receipt.textContent = message.read_at ? 'Read' : 'Delivered';
            if (message.read_at) {
                receipt.classList.add('is-read');
            }
            foot.appendChild(receipt);
        }

        article.appendChild(name);
        article.appendChild(bubble);
        article.appendChild(foot);

        return article;
    }

    function clearEmptyState(root)
    {
        var empty = scrollBox(root).querySelector('.cn-chat-empty');
        if (empty) {
            empty.remove();
        }
    }

    function appendMessages(root, rows)
    {
        var box = scrollBox(root);
        var stuckToBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        var added = 0;

        rows.forEach(function (row) {
            if (root.querySelector('[data-chat-msg="' + row.id + '"]')) {
                return;
            }
            clearEmptyState(root);
            box.appendChild(buildMessage(row));
            added += 1;
            current.lastId = Math.max(current.lastId, row.id);
        });

        if (added && stuckToBottom) {
            box.scrollTop = box.scrollHeight;
        }

        return added;
    }

    /** Flip the composer when the server says the thread has stopped accepting traffic. */
    function applyGate(root, payload)
    {
        var input = root.querySelector('[data-chat-input]');
        var send = root.querySelector('.cn-chat-btn-send');
        var canSend = Boolean(payload.can_send);

        if (input) {
            input.disabled = !canSend;
        }
        if (send) {
            send.disabled = !canSend;
        }
        if (canSend) {
            return;
        }

        root.setAttribute('data-chat-can-send', '0');

        var flag = root.querySelector('.cn-chat-flag');
        if (flag) {
            flag.textContent = 'Closed';
            flag.classList.remove('is-open');
            flag.classList.add('is-closed');
        }

        if (payload.reason && !root.querySelector('.cn-chat-notice')) {
            var notice = document.createElement('div');
            notice.className = 'cn-chat-notice';
            notice.setAttribute('role', 'status');

            var icon = document.createElement('i');
            icon.className = 'fas fa-circle-info';
            icon.setAttribute('aria-hidden', 'true');

            var text = document.createElement('span');
            text.textContent = payload.reason;

            notice.appendChild(icon);
            notice.appendChild(text);

            var head = root.querySelector('.cn-chat-head');
            if (head && head.parentNode) {
                head.insertAdjacentElement('afterend', notice);
            }
        }
    }

    function markReceipts(root, ids)
    {
        (ids || []).forEach(function (id) {
            var receipt = root.querySelector('[data-chat-receipt="' + id + '"]');
            if (receipt && !receipt.classList.contains('is-read')) {
                receipt.classList.add('is-read');
                receipt.textContent = 'Read';
            }
        });
    }

    /**
     * Read a reply without letting its body decide the error copy.
     *
     * An expired session, a CSRF mismatch or a throttle all answer with an HTML page
     * on this project, and `response.json()` rejects on those, which used to surface
     * as "check your connection" on a perfectly working network.
     */
    function readJson(response)
    {
        return response.text().then(function (text) {
            var payload = null;
            try {
                payload = text ? JSON.parse(text) : null;
            } catch (ignored) {
                payload = null;
            }
            return {
                ok: response.ok,
                status: response.status,
                redirected: response.redirected,
                payload: payload && typeof payload === 'object' ? payload : null
            };
        });
    }

    /** What an HTTP status actually means here, when the body was not JSON. */
    function serverProblem(result)
    {
        if (result.payload && typeof result.payload.message === 'string' && result.payload.message !== '') {
            return result.payload.message;
        }
        if (result.redirected || result.status === 419 || result.status === 401) {
            return 'Your session expired. Sign in again to keep using Messages.';
        }
        if (result.status === 403) {
            return 'Messaging is closed for this application.';
        }
        if (result.status === 429) {
            return 'You are sending too quickly. Wait a few seconds and try again.';
        }
        if (result.status === 503) {
            return 'Messaging needs its database tables first.';
        }
        return 'The server replied with an error (HTTP ' + result.status + '). Try again.';
    }

    /** Only a fetch that never reached the server may be called a connection problem. */
    function offlineProblem(fallback)
    {
        if (navigator.onLine === false) {
            return 'You appear to be offline. Reconnect and try again.';
        }
        return fallback;
    }

    function pollOnce(root)
    {
        if (!root || document.hidden || Date.now() < current.nextAt) {
            return Promise.resolve();
        }

        var url = root.getAttribute('data-chat-poll')
            + '?after=' + current.lastId
            + '&since=' + current.lastPoll;

        return fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': root.getAttribute('data-chat-token') || ''
            },
            credentials: 'same-origin'
        })
            .then(readJson)
            .then(function (result) {
                var payload = result.payload;

                if (result.ok && payload && payload.success) {
                    current.failures = 0;
                    current.nextAt = 0;
                    current.lastPoll = payload.server_time || Math.floor(Date.now() / 1000);
                    appendMessages(root, payload.messages || []);
                    markReceipts(root, payload.read_ids || []);
                    applyGate(root, payload);
                    setStatus(root, '');
                    return;
                }

                fail(root, 'New messages are not loading right now. This page keeps retrying.');
                // A suspended or closed thread is an answer, not a fault, so the
                // composer is still flipped even though the poll "failed".
                if (payload && payload.can_send === false) {
                    applyGate(root, payload);
                } else {
                    // Covers the signed-out case too: the auth middleware redirects, so
                    // the reply is a 200 whose body is the login page rather than JSON.
                    setStatus(root, serverProblem(result), true);
                }
            })
            .catch(function () {
                fail(root, navigator.onLine === false
                    ? 'You appear to be offline. New messages arrive once the connection returns.'
                    : 'New messages are not loading right now. This page keeps retrying.');
            });
    }

    function stop()
    {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start()
    {
        var root = panel();
        if (!root) {
            stop();
            return;
        }

        var switched = syncCursor(root);

        // Scroll to the newest message once per conversation, not on every tick.
        var box = scrollBox(root);
        if (box && (switched || !timer)) {
            box.scrollTop = box.scrollHeight;
        }

        if (timer) {
            // Opening a different thread must not wait for the next tick.
            if (switched) {
                pollOnce(root);
            }
            return;
        }

        timer = setInterval(function () {
            var live = panel();
            if (!live) {
                stop();
                return;
            }
            if (syncCursor(live)) {
                pollOnce(live);
                return;
            }
            if (!document.hidden) {
                pollOnce(live);
            }
        }, POLL_MS);

        // The first fetch happens now rather than one interval from now, so a page
        // that was opened while the other side was typing is current immediately.
        pollOnce(root);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            var live = panel();
            if (live) {
                current.failures = 0;
                pollOnce(live);
            }
        }
    });

    // ── Sending ───────────────────────────────────────────────────────────────

    function sendForm(root, form, input)
    {
        var text = input.value;

        if (text.trim() === '') {
            setStatus(root, 'Write a message before sending.', true);
            input.focus();
            return;
        }

        setStatus(root, 'Sending…');
        form.classList.add('is-sending');

        var body = new URLSearchParams();
        body.set('_token', root.getAttribute('data-chat-token') || '');
        body.set('message', text);

        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: body.toString()
        })
            .then(readJson)
            .then(function (result) {
                var payload = result.payload;

                if (!result.ok || !payload || payload.success === false) {
                    var first = payload && payload.errors && payload.errors.message
                        ? payload.errors.message[0]
                        : serverProblem(result);
                    setStatus(root, first, true);
                    // Only a closed or un-migrated thread stops the composer; a 422 is
                    // the writer's own text and leaves the box ready for a retry.
                    if (result.status === 403 || result.status === 503) {
                        applyGate(root, { can_send: false, reason: first });
                    }
                    return;
                }

                input.value = '';
                clearEmptyState(root);
                appendMessages(root, payload.sent ? [payload.sent] : []);
                // The bubble appearing in the transcript is the confirmation, so there is
                // no toast on top of it.
                setStatus(root, '');
                // Anything the other side sent while this one was typing arrives now,
                // not one interval later.
                pollOnce(root);
            })
            .catch(function () {
                // Reached only when the request never got a usable answer at all; the
                // text stays in the box, so a retry is one click away.
                setStatus(root, offlineProblem('The message could not be sent. Try again.'), true);
            })
            .finally(function () {
                form.classList.remove('is-sending');
            });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest ? event.target.closest('[data-chat-form]') : null;
        if (!form) {
            return;
        }

        var root = form.closest('[data-cn-chat]');
        var input = form.querySelector('[data-chat-input]');
        if (!root || !input || !root.getAttribute('data-chat-poll')) {
            return; // No AJAX wiring: let the plain form post.
        }

        event.preventDefault();
        sendForm(root, form, input);
    });

    // ── Older messages ────────────────────────────────────────────────────────

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-chat-older]') : null;
        if (!button) {
            return;
        }

        var root = button.closest('[data-cn-chat]');
        if (!root) {
            return;
        }

        var box = scrollBox(root);
        var distanceFromBottom = box.scrollHeight - box.scrollTop;
        var before = minMessageId(root);
        var url = root.getAttribute('data-chat-history') + '?before=' + before;

        button.disabled = true;
        setStatus(root, 'Loading older messages…');

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(readJson)
            .then(function (result) {
                var payload = result.payload;

                if (!result.ok || !payload || !payload.success) {
                    button.disabled = false;
                    setStatus(root, serverProblem(result), true);
                    return;
                }

                var rows = (payload.messages || []).slice().reverse();
                rows.forEach(function (row) {
                    if (root.querySelector('[data-chat-msg="' + row.id + '"]')) {
                        return;
                    }
                    box.insertBefore(buildMessage(row), box.firstChild);
                });

                box.scrollTop = box.scrollHeight - distanceFromBottom;
                setStatus(root, '');

                if (!payload.has_more) {
                    button.classList.add('is-hidden');
                    button.disabled = true;
                } else {
                    button.disabled = false;
                }
            })
            .catch(function () {
                button.disabled = false;
                setStatus(root, offlineProblem('Older messages could not be loaded. Try again.'), true);
            });
    });

    // The server has already stamped these as read on page load, so the transcript
    // starts at the bottom and the receipts agree with what the recipient saw.
    document.addEventListener('cn:region-updated', function () {
        stop();
        start();
    });

    start();
})();
