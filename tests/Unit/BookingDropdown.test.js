import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function dropdown(search = async () => [{ name: 'Delhi' }, { name: 'Mumbai' }]) {
    class Element {
        constructor() { this.listeners = {}; this.attrs = {}; this.children = []; this.value = ''; this.hidden = true; this.id = 'origin'; this.textContent = ''; }
        addEventListener(name, fn) { (this.listeners[name] ??= []).push(fn); }
        async emit(name, extra = {}) { await Promise.all((this.listeners[name] ?? []).map(fn => fn({ preventDefault() {}, ...extra }))); }
        setAttribute(key, value) { this.attrs[key] = value; }
        removeAttribute(key) { delete this.attrs[key]; }
        replaceChildren() { this.children = []; }
        appendChild(element) { this.children.push(element); }
        setCustomValidity(message) { this.validity = message; }
        scrollIntoView() {}
        click() { this.emit('click'); }
        focus() { document.activeElement = this; }
        set innerHTML(value) { throw new Error('Unsafe HTML rendering'); }
    }
    const input = new Element(), value = new Element(), list = new Element(), hint = new Element();
    const parts = { '.booking-search': input, '.booking-value': value, '.booking-results': list, '.booking-hint': hint };
    const field = { dataset: { type: 'origin', searchUrl: 'http://example.test/propeller/booking-options/search' }, querySelector: key => parts[key] };
    const document = { activeElement: input, querySelectorAll: () => [field], createElement: () => new Element() };
    const timers = new Map(), requests = [];
    let timerId = 0;
    runInNewContext(readFileSync(new URL('../../public/booking-options.js', import.meta.url), 'utf8'), {
        document, window: { location: { href: 'http://example.test/propeller/bills/create' } }, URL, AbortController,
        setTimeout: fn => { timers.set(++timerId, fn); return timerId; }, clearTimeout: id => timers.delete(id),
        fetch: async (url, options) => { requests.push({ url, options }); return { ok: true, json: async () => search(url.searchParams.get('q')) }; },
    });
    return { input, value, list, hint, requests, flush: async () => { const pending = [...timers.values()]; timers.clear(); await Promise.all(pending.map(fn => fn())); } };
}

test('keyboard selection updates submitted value and closes the searchable list', async () => {
    const ui = dropdown();
    await ui.input.emit('focus');
    assert.equal(ui.list.children.length, 2);
    await ui.input.emit('keydown', { key: 'ArrowUp' });
    assert.equal(ui.list.children[1].attrs['aria-selected'], 'true');
    await ui.input.emit('keydown', { key: 'Enter' });
    assert.equal(ui.value.value, 'Mumbai');
    assert.equal(ui.input.value, 'Mumbai');
    assert.equal(ui.list.hidden, true);
    assert.equal(ui.input.validity, '');
});

test('typing clears the old selection and debounces server search', async () => {
    const ui = dropdown();
    ui.value.value = 'Delhi';
    ui.input.value = 'M'; await ui.input.emit('input');
    ui.input.value = 'Mu'; await ui.input.emit('input');
    assert.equal(ui.value.value, '');
    assert.ok(ui.input.validity.includes('select'));
    assert.equal(ui.requests.length, 0);
    await ui.flush();
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.requests[0].url.searchParams.get('q'), 'Mu');
    assert.equal(ui.requests[0].url.pathname, '/propeller/booking-options/search');
});

test('option names are rendered as text and mouse selection works', async () => {
    const ui = dropdown(async () => [{ name: '<script>example()</script>' }]);
    await ui.input.emit('focus');
    assert.equal(ui.list.children[0].textContent, '<script>example()</script>');
    await ui.list.children[0].emit('click');
    assert.equal(ui.value.value, '<script>example()</script>');
});

test('empty results and network errors give useful messages', async () => {
    const empty = dropdown(async () => []);
    await empty.input.emit('focus');
    assert.match(empty.list.children[0].textContent, /No matching options/);
    const error = dropdown(async () => { throw new Error('offline'); });
    await error.input.emit('focus');
    assert.match(error.hint.textContent, /Could not load/);
    assert.equal(error.list.hidden, true);
});

test('stale responses cannot overwrite the latest search and Escape cancels pending searches', async () => {
    let finishFirst;
    const ui = dropdown(query => query === '' ? new Promise(resolve => { finishFirst = resolve; }) : [{ name: 'Latest result' }]);
    const first = ui.input.emit('focus');
    await Promise.resolve();
    ui.input.value = 'Latest'; await ui.input.emit('input'); await ui.flush();
    finishFirst([{ name: 'Stale result' }]); await first;
    assert.equal(ui.list.children[0].textContent, 'Latest result');
    ui.input.value = 'Pending'; await ui.input.emit('input');
    await ui.input.emit('keydown', { key: 'Escape' }); await ui.flush();
    assert.equal(ui.list.hidden, true);
    assert.equal(ui.requests.length, 2);
});
