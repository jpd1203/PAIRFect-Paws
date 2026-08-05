/**
 * Submit Report — preview -> confirm flow.
 * 1) Form submits to /post-adoption/submit-report/preview via fetch (AJAX),
 *    server re-validates everything and returns the confirm-modal HTML.
 * 2) Confirm modal's own <form> does the real POST to /confirm, which
 *    persists the report. Nothing is trusted from step 1 alone — the
 *    server re-validates on confirm too (see StoreReportRequest).
 */
(function () {
    const form = document.getElementById('submitReportForm');
    if (!form) return;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });

            if (res.status === 422) {
                const data = await res.json();
                const firstError = Object.values(data.errors ?? {})[0]?.[0];
                window.PAIRfectPaws?.showToast(firstError ?? 'Please check the form for errors.', 'error');
                return;
            }

            if (!res.ok) throw new Error('Preview failed');

            document.getElementById('confirmReportModalContent').innerHTML = await res.text();
            document.getElementById('confirmReportModal').classList.add('show');
        } catch (err) {
            window.PAIRfectPaws?.showToast('Something went wrong. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
        }
    });

    document.getElementById('confirmReportModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'confirmReportModal') closeConfirmReportModal();
    });
})();

function closeConfirmReportModal() {
    document.getElementById('confirmReportModal')?.classList.remove('show');
}
