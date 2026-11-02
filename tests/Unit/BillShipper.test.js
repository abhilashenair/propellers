import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

function form() {
    const customer = { listeners: {}, selectedOptions: [{ value: '1', dataset: { name: 'Customer Name', phone: '+91 0123456789', address: 'Street\nCity', postalCode: '001234', taxNumber: 'GST' } }], addEventListener(name, fn) { this.listeners[name] = fn; } };
    const button = { listeners: {}, addEventListener(name, fn) { this.listeners[name] = fn; } };
    const status = { textContent: '' };
    const fields = Object.fromEntries(['shipper_name', 'shipper_phone', 'shipper_address', 'shipper_postal_code', 'shipper_tax_number'].map(name => [name, { value: 'Restored value' }]));
    const document = { querySelector(query) {
        if (query === '[data-shipper-customer]') return customer;
        if (query === '[data-use-customer]') return button;
        if (query === '[data-shipper-status]') return status;
        return fields[query.match(/name="(.+)"/)[1]];
    } };
    runInNewContext(readFileSync(new URL('../../public/bill-shipper.js', import.meta.url), 'utf8'), { document });
    return { customer, button, status, fields };
}

test('initialization preserves user-entered shipper details after validation', () => {
    const ui = form();
    assert.equal(ui.fields.shipper_name.value, 'Restored value');
    assert.equal(ui.fields.shipper_postal_code.value, 'Restored value');
});

test('choosing a customer fills editable shipper details and preserves postcode zeros', () => {
    const ui = form(); ui.customer.listeners.change();
    assert.equal(ui.fields.shipper_name.value, 'Customer Name');
    assert.equal(ui.fields.shipper_phone.value, '+91 0123456789');
    assert.equal(ui.fields.shipper_address.value, 'Street\nCity');
    assert.equal(ui.fields.shipper_postal_code.value, '001234');
    assert.equal(ui.fields.shipper_tax_number.value, 'GST');
    ui.fields.shipper_name.value = 'Different shipper';
    ui.button.listeners.click();
    assert.equal(ui.fields.shipper_name.value, 'Customer Name');
});

test('an unselected customer does not clear manual details and missing postal codes remain empty', () => {
    const ui = form(); ui.customer.selectedOptions = [{ value: '' }]; ui.button.listeners.click();
    assert.equal(ui.fields.shipper_name.value, 'Restored value');
    assert.match(ui.status.textContent, /Select a billing customer/);
    ui.customer.selectedOptions = [{ value: '2', dataset: { name: 'Customer without ZIP' } }]; ui.customer.listeners.change();
    assert.equal(ui.fields.shipper_postal_code.value, '');
});
