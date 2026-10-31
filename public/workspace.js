(() => {
    const groups = [...document.querySelectorAll('.nav-group')];
    groups.forEach(group => group.addEventListener('toggle', () => {
        if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
    }));
    document.querySelectorAll('[data-copy-embed]').forEach(button => {
        button.addEventListener('click', async () => {
            const code = document.getElementById(button.dataset.copyEmbed);
            const status = document.getElementById(button.dataset.status);
            let copied = false;
            if (window.isSecureContext && navigator.clipboard?.writeText) {
                try { await navigator.clipboard.writeText(code.value); copied = true; } catch (_) { /* Fall back to selection on restricted browsers. */ }
            }
            if (!copied) {
                code.focus();
                code.select();
                try { copied = document.execCommand('copy'); } catch (_) { copied = false; }
            }
            status.textContent = copied ? 'Embed code copied.' : 'Code selected. Press Ctrl+C or Command+C to copy.';
            if (copied) button.focus();
        });
    });
})();
