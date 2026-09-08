import '../css/app.css';
import './philippine-address';
import './post-adoption-camera';

/**
 * Global site behavior: mobile sidebar toggle, profile dropdown, toast
 * notifications, and the shared "pet profile" modal (used on Browse Pets
 * and Pet Recommendation results). Exposed on window.PAIRfectPaws so
 * inline onclick handlers in Blade views (openPetModal(id), etc.) work
 * without a bundler-aware event system.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function showToast(message, type = 'success') {
    const host = document.getElementById('toastHost');
    if (!host || !message) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type === 'error' ? 'toast-error' : ''}`;
    toast.textContent = message;
    host.appendChild(toast);

    setTimeout(() => toast.remove(), 4500);
}

function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    const open = () => { sidebar?.classList.add('open'); overlay?.classList.add('active'); };
    const close = () => { sidebar?.classList.remove('open'); overlay?.classList.remove('active'); };

    toggle?.addEventListener('click', open);
    overlay?.addEventListener('click', close);

    sidebar?.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', close);
    });
}

async function openPetModal(petId) {
    const overlay = document.getElementById('petModal');
    const content = document.getElementById('petModalContent');
    if (!overlay || !content) return;

    try {
        const res = await fetch(`/pets/${petId}/modal`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('Failed to load pet');
        content.innerHTML = await res.text();
        overlay.classList.add('show');
    } catch (err) {
        showToast('Could not load this pet\u2019s profile. Please try again.', 'error');
    }
}

function closePetModal() {
    document.getElementById('petModal')?.classList.remove('show');
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();

    document.getElementById('petModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'petModal') closePetModal();
    });
});

window.PAIRfectPaws = { showToast, csrfToken };
window.openPetModal = openPetModal;
window.closePetModal = closePetModal;
