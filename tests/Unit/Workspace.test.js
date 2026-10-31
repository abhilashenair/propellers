import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function workspace({ secure = false, clipboard, copyResult = true } = {}) {
    const makeElement = () => ({ listeners: {}, open: false, addEventListener(event, fn) { this.listeners[event] = fn; }, async emit(event) { await this.listeners[event]?.(); } });
    const groups = [makeElement(), makeElement(), makeElement()];
    const button = Object.assign(makeElement(), { dataset: { copyEmbed: 'code', status: 'status' }, focus() { state.focus = 'button'; } });
    const code = { value: '<iframe src="https://example.test/propeller/embed/tracking"></iframe>', focus() { state.focus = 'code'; }, select() { state.selected = true; } };
    const status = { textContent: '' };
    const state = { focus: '', selected: false, copyCommands: 0 };
    const document = { querySelectorAll: query => query === '.nav-group' ? groups : [button], getElementById: id => id === 'code' ? code : status, execCommand(command) { assert.equal(command, 'copy'); state.copyCommands++; return copyResult; } };
    runInNewContext(readFileSync(new URL('../../public/workspace.js', import.meta.url), 'utf8'), { document, window: { isSecureContext: secure }, navigator: { clipboard } });
    return { groups, button, code, status, state };
}

test('opening one accordion closes its siblings and an open group can close', async () => {
    const ui = workspace();
    ui.groups[0].open = true; await ui.groups[0].emit('toggle');
    ui.groups[1].open = true; await ui.groups[1].emit('toggle');
    assert.equal(ui.groups[0].open, false);
    assert.equal(ui.groups[1].open, true);
    ui.groups[1].open = false; await ui.groups[1].emit('toggle');
    assert.equal(ui.groups.every(group => !group.open), true);
});

test('secure copy uses clipboard API with the exact embed code', async () => {
    let copied;
    const ui = workspace({ secure: true, clipboard: { writeText: async value => { copied = value; } } });
    await ui.button.emit('click');
    assert.equal(copied, ui.code.value);
    assert.equal(ui.status.textContent, 'Embed code copied.');
    assert.equal(ui.state.copyCommands, 0);
});

test('HTTP copies via selection fallback', async () => {
    const ui = workspace(); await ui.button.emit('click');
    assert.equal(ui.state.selected, true);
    assert.equal(ui.state.copyCommands, 1);
    assert.equal(ui.status.textContent, 'Embed code copied.');
});

test('denied clipboard access falls back to copying selected text', async () => {
    const ui = workspace({ secure: true, clipboard: { writeText: async () => { throw new Error('denied'); } } });
    await ui.button.emit('click');
    assert.equal(ui.state.copyCommands, 1);
    assert.equal(ui.status.textContent, 'Embed code copied.');
});

test('unavailable copy leaves code selected with manual copy instructions', async () => {
    const ui = workspace({ copyResult: false }); await ui.button.emit('click');
    assert.equal(ui.state.selected, true);
    assert.equal(ui.state.focus, 'code');
    assert.match(ui.status.textContent, /Press Ctrl\+C or Command\+C/);
});
