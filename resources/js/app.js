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
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    const open = () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('active');
        document.body.classList.add('overflow-hidden', 'max-[991px]:overflow-hidden');
    };

    const close = () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('active');
        document.body.classList.remove('overflow-hidden', 'max-[991px]:overflow-hidden');
    };

    toggle?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    overlay?.addEventListener('click', close);

    sidebar?.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 991) close();
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 991) {
            close();
        }
    });
}

function initMobileNav() {
    const nav = document.getElementById('mobile-nav');
    const toggle = document.getElementById('mobileNavToggle');
    if (!nav) return;

    toggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        nav.classList.toggle('hidden');
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!nav.classList.contains('hidden') && !nav.contains(e.target) && e.target !== toggle && !toggle?.contains(e.target)) {
            nav.classList.add('hidden');
        }
    });

    // Close when a link inside is clicked
    nav.querySelectorAll('a, button').forEach(el => {
        el.addEventListener('click', () => {
            nav.classList.add('hidden');
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            nav.classList.add('hidden');
        }
    });
}

function initDragToScroll() {
    document.querySelectorAll('.filter-section, .table-responsive, [data-drag-scroll]').forEach((slider) => {
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            if (e.target.closest('button, a, input, select, form')) return;
            isDown = true;
            slider.classList.add('cursor-grabbing');
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.classList.remove('cursor-grabbing');
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.classList.remove('cursor-grabbing');
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 1.5;
            slider.scrollLeft = scrollLeft - walk;
        });
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

function openPetPhotoLightbox(imageUrl, petName) {
    let lb = document.getElementById('petPhotoLightbox');
    if (!lb) {
        lb = document.createElement('div');
        lb.id = 'petPhotoLightbox';
        lb.className = 'fixed inset-0 z-[10000] bg-black/85 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-200';
        lb.innerHTML = `
            <div class="relative max-w-xl w-full flex flex-col items-center">
                <button type="button" onclick="closePetPhotoLightbox()" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl font-bold p-2 focus:outline-none">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <img id="lightboxImg" src="" alt="" class="max-w-full max-h-[75vh] object-contain rounded-2xl shadow-2xl border border-white/20">
                <div class="mt-3 text-center">
                    <p id="lightboxCaption" class="text-white text-base font-semibold m-0"></p>
                    <p class="text-gray-300 text-xs mt-0.5">Click anywhere to close</p>
                </div>
            </div>
        `;
        lb.addEventListener('click', (e) => {
            if (e.target === lb || e.target.closest('button')) closePetPhotoLightbox();
        });
        document.body.appendChild(lb);
    }
    const img = document.getElementById('lightboxImg');
    const cap = document.getElementById('lightboxCaption');
    if (img) img.src = imageUrl;
    if (cap) cap.textContent = petName || '';
    lb.classList.remove('opacity-0', 'pointer-events-none');
    lb.classList.add('opacity-100', 'pointer-events-auto');
}

function closePetPhotoLightbox() {
    const lb = document.getElementById('petPhotoLightbox');
    if (lb) {
        lb.classList.remove('opacity-100', 'pointer-events-auto');
        lb.classList.add('opacity-0', 'pointer-events-none');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initMobileNav();
    initDragToScroll();

    document.getElementById('petModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'petModal') closePetModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePetPhotoLightbox();
            closePetModal();
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar?.classList.remove('open');
            overlay?.classList.remove('active');
            document.body.classList.remove('overflow-hidden', 'max-[991px]:overflow-hidden');
            document.getElementById('mobile-nav')?.classList.add('hidden');
        }
    });
});

window.PAIRfectPaws = { showToast, csrfToken };
window.openPetModal = openPetModal;
window.closePetModal = closePetModal;
window.openPetPhotoLightbox = openPetPhotoLightbox;
window.closePetPhotoLightbox = closePetPhotoLightbox;
