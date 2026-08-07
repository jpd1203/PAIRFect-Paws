/**
 * My Check-ins — "View" on a submitted report opens the report modal via AJAX.
 * Authorization (own reports only) is enforced server-side in MonitoringController.
 */
async function openReportViewModal(reportId) {
    const overlay = document.getElementById('reportViewModal');
    const content = document.getElementById('reportViewModalContent');
    if (!overlay || !content) return;

    try {
        const res = await fetch(`/post-adoption/reports/${reportId}/modal`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('Failed to load report');
        content.innerHTML = await res.text();
        overlay.classList.add('show');
    } catch (err) {
        window.PAIRfectPaws?.showToast('Could not load this report. Please try again.', 'error');
    }
}

function closeReportViewModal() {
    document.getElementById('reportViewModal')?.classList.remove('show');
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('reportViewModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'reportViewModal') closeReportViewModal();
    });
});
