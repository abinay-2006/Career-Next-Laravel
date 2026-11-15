'use strict';
/**
 * Behavioural harness for public/assets/js/copilot.js, run by tests/Feature/CopilotTest.php.
 * Loads the real script against a minimal DOM stub, submits a message with a stubbed fetch
 * and prints the resulting UI state as JSON.
 *
 * Usage: node tests/js/copilot-frontend.cjs <path-to-copilot.js> <scenario>
 */
const fs = require('fs');
const vm = require('vm');

const [scriptPath, scenario] = process.argv.slice(2);

class El {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.parent = null; this.listeners = {}; this.attrs = {}; this.dataset = {}; this.className = ''; this.disabled = false; this.value = ''; this.scrollTop = 0; this.scrollHeight = 0; this._text = ''; this.selectors = {}; this.classes = new Set(); }
    get classList() { const c = this.classes; return { toggle: (n, on) => (on ? c.add(n) : c.delete(n)), contains: (n) => c.has(n) }; }
    get textContent() { return this._text + this.children.map((ch) => ch.textContent).join(''); }
    set textContent(v) { this._text = String(v); this.children = []; }
    append(...nodes) { nodes.forEach((n) => { n.parent = this; this.children.push(n); }); }
    replaceChildren() { this.children = []; }
    remove() { if (this.parent) this.parent.children = this.parent.children.filter((c) => c !== this); this.parent = null; }
    setAttribute(k, v) { this.attrs[k] = v; }
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
    focus() {}
    querySelector(sel) { return this.selectors[sel] || null; }
    querySelectorAll(sel) { return this.selectors[sel] ? [this.selectors[sel]] : []; }
    async fire(type, event = {}) { for (const fn of this.listeners[type] || []) await fn({ preventDefault() {}, ...event }); }
}
const textNode = (t) => { const n = new El('#text'); n._text = String(t); return n; };

const root = new El(); const launch = new El('button'); const panel = new El(); const messages = new El();
const form = new El('form'); const input = new El('textarea'); const button = new El('button');
const close = new El('button'); const reset = new El('button');
Object.assign(root.selectors, { '.cn-copilot-launch': launch, '.cn-copilot-panel': panel, '.cn-copilot-messages': messages, '.cn-copilot-form': form, '[data-copilot-close]': close, '[data-copilot-new]': reset });
Object.assign(form.selectors, { textarea: input, button });
root.dataset = { messageUrl: '/copilot/message', resetUrl: '/copilot/reset', csrf: 'token' };

const headers = (type, extra = {}) => ({ get: (name) => (name.toLowerCase() === 'content-type' ? type : (extra[name.toLowerCase()] || null)) });
const scenarios = {
    html500: () => Promise.resolve({ ok: false, status: 500, headers: headers('text/html; charset=UTF-8'), json: () => { throw new Error('json() must not be called on HTML'); }, text: () => Promise.resolve('<!DOCTYPE html><html>Server Error</html>') }),
    html503: () => Promise.resolve({ ok: false, status: 503, headers: headers('text/html'), json: () => { throw new Error('json() must not be called on HTML'); } }),
    html504: () => Promise.resolve({ ok: false, status: 504, headers: headers('text/html'), json: () => { throw new Error('json() must not be called on HTML'); } }),
    emptyJson500: () => Promise.resolve({ ok: false, status: 500, headers: headers('application/json'), json: () => Promise.reject(new SyntaxError('Unexpected end of JSON input')) }),
    network: () => Promise.reject(new TypeError('Failed to fetch')),
    aborted: () => Promise.reject(Object.assign(new Error('The operation was aborted.'), { name: 'AbortError' })),
    json503: () => Promise.resolve({ ok: false, status: 503, headers: headers('application/json', { 'x-copilot-safe-error': '1' }), json: () => Promise.resolve({ message: 'The AI assistant is temporarily busy. Please try again in a moment.' }) }),
    json503quota: () => Promise.resolve({ ok: false, status: 503, headers: headers('application/json', { 'x-copilot-safe-error': '1' }), json: () => Promise.resolve({ message: 'The AI assistant has reached its current usage limit. Please try again after the quota resets. Estimated wait: about 15 hours.' }) }),
    json503rateLimit: () => Promise.resolve({ ok: false, status: 503, headers: headers('application/json', { 'x-copilot-safe-error': '1', 'retry-after': '2862' }), json: () => Promise.resolve({ message: 'The AI assistant is receiving more requests than its AI service currently allows. Please try again in about 48 minutes.', retry_after: 2862 }) }),
    invalidJson200:() => Promise.resolve({ ok: true, status: 200, headers: headers('application/json'), json: () => Promise.reject(new SyntaxError('Unexpected token < in JSON')) }),
    json502: () => Promise.resolve({ ok: false, status: 502, headers: headers('application/json', { 'x-copilot-safe-error': '1' }), json: () => Promise.resolve({ message: 'The AI assistant could not process that request. Please try again later.' }) }),
    json401: () => Promise.resolve({ ok: false, status: 401, headers: headers('application/json'), json: () => Promise.resolve({ message: 'Unauthenticated.' }) }),
    json403: () => Promise.resolve({ ok: false, status: 403, headers: headers('application/json'), json: () => Promise.resolve({ message: 'Forbidden.' }) }),
    json500: () => Promise.resolve({ ok: false, status: 500, headers: headers('application/json'), json: () => Promise.resolve({ message: 'Internal Server Error' }) }),
    json419: () => Promise.resolve({ ok: false, status: 419, headers: headers('application/json'), json: () => Promise.resolve({ message: 'CSRF token mismatch.' }) }),
    json429: () => Promise.resolve({ ok: false, status: 429, headers: headers('application/json'), json: () => Promise.resolve({ message: 'Too Many Attempts.' }) }),
    success: () => Promise.resolve({ ok: true, status: 200, headers: headers('application/json'), json: () => Promise.resolve({ reply: 'Hello there' }) }),
    doubleSubmit: () => new Promise((resolve) => setTimeout(() => resolve({ ok: true, status: 200, headers: headers('application/json'), json: () => Promise.resolve({ reply: 'Only once' }) }), 20)),
};
let jsonCalledOnHtml = false;
let fetchCalls = 0;
const fetchStub = (...args) => (fetchCalls++, scenarios[scenario](...args)).then((r) => {
    if (!String(r.headers.get('content-type')).includes('json')) { const original = r.json; r.json = () => { jsonCalledOnHtml = true; return original(); }; }
    return r;
});

const sandbox = {
    document: { querySelectorAll: () => [root], createElement: (t) => new El(t), createTextNode: textNode, addEventListener() {} },
    location: { origin: 'http://localhost' }, URL, fetch: fetchStub, AbortController, setTimeout, clearTimeout, console,
};
vm.runInNewContext(fs.readFileSync(scriptPath, 'utf8'), sandbox);

(async () => {
    input.value = 'Hello Copilot';
    const first = form.fire('submit');
    // While the first request is pending, the UI must be locked and further submits ignored.
    const duringRequest = { buttonDisabled: button.disabled, loadingVisible: messages.children.some((c) => c.textContent === 'Thinking…') };
    input.value = 'Second message';
    await form.fire('submit');
    await first;
    const items = messages.children.map((c) => ({ className: c.className, text: c.textContent }));
    process.stdout.write(JSON.stringify({ items, duringRequest, fetchCalls, buttonDisabled: button.disabled, loadingVisible: items.some((i) => i.text === 'Thinking…'), jsonCalledOnHtml }));
})().catch((error) => { process.stdout.write(JSON.stringify({ crash: String(error && error.stack || error) })); process.exitCode = 1; });
