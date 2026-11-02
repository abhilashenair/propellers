(() => {
    const customer = document.querySelector('[data-shipper-customer]');
    if (!customer) return;
    const status = document.querySelector('[data-shipper-status]');
    const fields = { shipper_name: 'name', shipper_phone: 'phone', shipper_address: 'address', shipper_postal_code: 'postalCode', shipper_tax_number: 'taxNumber' };
    const fill = () => {
        const selected = customer.selectedOptions[0];
        if (!selected?.value) {
            status.textContent = 'Select a billing customer above first.';
            return;
        }
        Object.entries(fields).forEach(([name, key]) => {
            document.querySelector('[name="' + name + '"]').value = selected.dataset[key] ?? '';
        });
        status.textContent = 'Customer details copied. Confirm the shipper phone and ZIP / postal code before saving.';
    };
    customer.addEventListener('change', fill);
    document.querySelector('[data-use-customer]')?.addEventListener('click', fill);
})();
