(() => {
    function init() {
        const page = document.querySelector('[data-handover-page]');
        if (!page) return;
        function reveal(target) {
            for (let node = target; node && page.contains(node); node = node.parentElement) {
                if (node.tagName === 'DETAILS') node.open = true;
            }
        }
        function revealHash() {
            let id;
            try { id = decodeURIComponent(window.location.hash.slice(1)); } catch { return; }
            const target = document.getElementById(id);
            if (!target || !page.contains(target)) return;
            reveal(target);
            if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
            target.focus();
        }
        page.addEventListener('click', event => {
            const link = event.target.closest('a[href^="#"]');
            if (link) {
                const target = document.getElementById(link.getAttribute('href').slice(1));
                if (target && page.contains(target)) reveal(target);
            }
        });
        page.addEventListener('invalid', event => reveal(event.target), true);
        page.querySelectorAll('form').forEach((form, index) => {
            if (!form.id) form.id = `handover-action-${index}`;
            form.addEventListener('submit', () => {
                let marker = form.querySelector('[name="_ui_form"]');
                if (!marker) {
                    marker = document.createElement('input');
                    marker.type = 'hidden'; marker.name = '_ui_form'; form.append(marker);
                }
                marker.value = form.id; // Presentation hint only; never an authorization input.
            });
        });
        const failedForm = document.getElementById(page.dataset.failedForm || '');
        if (failedForm?.tagName === 'FORM' && page.contains(failedForm)) {
            reveal(failedForm);
            let errors = {};
            try { errors = JSON.parse(page.dataset.validationErrors || '{}'); } catch { /* Keep the server summary. */ }
            for (const [field, messages] of Object.entries(errors)) {
                for (const input of failedForm.querySelectorAll('input, select, textarea')) {
                    if (input.name !== field || input.type === 'hidden') continue;
                    input.setAttribute('aria-invalid', 'true');
                    const existing = (input.getAttribute('aria-describedby') || '').split(/\s+/)
                        .map(id => document.getElementById(id)).find(node => node?.id?.toLowerCase().endsWith('error'));
                    if (existing) {
                        existing.textContent = messages.join(' ');
                        continue;
                    }
                    const message = document.createElement('p');
                    message.id = `${failedForm.id}-error-${field.replace(/[^a-z0-9_-]/gi, '-')}`;
                    message.className = 'text-xs text-red-700';
                    message.textContent = messages.join(' ');
                    input.after(message);
                    input.setAttribute('aria-describedby', `${input.getAttribute('aria-describedby') || ''} ${message.id}`.trim());
                }
            }
        }
        page.querySelectorAll('[aria-invalid="true"]').forEach(reveal);
        window.addEventListener('hashchange', revealHash);
        window.addEventListener('pageshow', revealHash);
        if (page.querySelector('[data-handover-errors]')) page.querySelector('[data-handover-errors]').focus();
        else revealHash();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
