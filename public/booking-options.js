(() => {
    document.querySelectorAll('.booking-combobox').forEach((field) => {
        const input = field.querySelector('.booking-search');
        const value = field.querySelector('.booking-value');
        const list = field.querySelector('.booking-results');
        const hint = field.querySelector('.booking-hint');
        let timer, controller, active = -1, sequence = 0;
        let choices = [];
        const close = () => {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        };
        const select = (name) => {
            value.value = name;
            input.value = name;
            input.setCustomValidity('');
            close();
            hint.textContent = 'Selected: ' + name;
            input.focus();
        };
        const load = async () => {
            const current = ++sequence;
            controller?.abort();
            controller = new AbortController();
            list.replaceChildren();
            choices = [];
            active = -1;
            input.removeAttribute('aria-activedescendant');
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            hint.textContent = 'Searching…';
            const url = new URL(field.dataset.searchUrl, window.location.href);
            url.searchParams.set('type', field.dataset.type);
            url.searchParams.set('q', input.value);
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Search failed');
                const options = await response.json();
                if (current !== sequence || document.activeElement !== input) return;
                options.forEach((option, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'booking-choice';
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', 'false');
                    button.tabIndex = -1;
                    button.id = input.id + '-option-' + index;
                    button.textContent = option.name;
                    button.addEventListener('mousedown', (event) => event.preventDefault());
                    button.addEventListener('click', () => select(option.name));
                    list.appendChild(button);
                });
                choices = [...list.children];
                if (!options.length) {
                    const message = document.createElement('div');
                    message.className = 'booking-message';
                    message.textContent = 'No matching options. Ask your admin to add or enable one.';
                    list.appendChild(message);
                }
                hint.textContent = options.length === 20 ? 'Showing 20 matches. Type more to narrow your search.' : options.length + ' matching options. Use arrow keys and Enter to select.';
            } catch (error) {
                if (error.name !== 'AbortError' && current === sequence) {
                    close();
                    hint.textContent = 'Could not load options. Click the field to retry.';
                }
            }
        };
        input.addEventListener('input', () => {
            value.value = '';
            input.setCustomValidity('Please select an available option from the search results.');
            controller?.abort();
            sequence++;
            close();
            clearTimeout(timer);
            timer = setTimeout(load, 200);
        });
        input.addEventListener('focus', load);
        input.addEventListener('click', () => { if (list.hidden) load(); });
        input.addEventListener('blur', () => {
            clearTimeout(timer);
            controller?.abort();
            sequence++;
            close();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' || event.key === 'Tab') {
                clearTimeout(timer);
                controller?.abort();
                sequence++;
                close();
                return;
            }
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                if (list.hidden) { load(); return; }
                if (!choices.length) return;
                active = active === -1 ? (event.key === 'ArrowDown' ? 0 : choices.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length;
                choices.forEach((choice, index) => choice.setAttribute('aria-selected', String(index === active)));
                input.setAttribute('aria-activedescendant', choices[active].id);
                choices[active].scrollIntoView({ block: 'nearest' });
            }
            if (event.key === 'Enter' && !list.hidden) {
                event.preventDefault();
                if (active >= 0) choices[active].click();
            }
        });
    });
})();
