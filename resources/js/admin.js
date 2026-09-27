import '../css/admin.css';

/**
 * Global admin panel behavior: sidebar toggle, custom modals, toast
 * notifications, live search filtering, and filter-bar buttons. Ported
 * from the original admin.js so every page's inline onclick="openModal(...)"
 * style handlers keep working.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// let toastTimer;
// function showToast(message, type = 'success') {
//     if (!message) return;

//     let host = document.getElementById('toastHost');
//     if (!host) return;

//     let toast = host.querySelector('.toast-notification');
//     if (!toast) {
//         toast = document.createElement('div');
//         toast.className = 'toast-notification';
//         host.appendChild(toast);
//     }

//     toast.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i><span>${message}</span>`;
//     toast.classList.toggle('bg-status-danger-text', type === 'error');
//     toast.classList.add('show');

//     clearTimeout(toastTimer);
//     toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
// }
let toastTimer;

function showToast(message, type = 'success') {
    if (!message) return;

    let host = document.getElementById('toastHost');
    if (!host) return;

    let toast = host.querySelector('.toast-notification');

    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-notification';
        host.appendChild(toast);
    }

    toast.innerHTML = `
        <i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i>
        <span>${message}</span>
    `;

    toast.classList.remove(
        'bg-status-danger-text',
        'bg-status-success-text'
    );

    toast.classList.add(
        type === 'error'
            ? 'bg-status-danger-text'
            : 'bg-status-success-text'
    );

    toast.classList.add('show');

    clearTimeout(toastTimer);

    toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}
window.showToast = showToast;

function openModal(id) {
    document.getElementById(id)?.classList.add('active');
}

window.openModal = openModal;

function closeModal(id) {
    document.getElementById(id)?.classList.remove('active');
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

    // Auto-close sidebar on mobile/tablet when a link is clicked
    sidebar?.querySelectorAll('.menu-item').forEach((item) => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 991) close();
        });
    });

    // Reset overlay/open state when window is resized above 991px (minimizing/maximizing tab)
    window.addEventListener('resize', () => {
        if (window.innerWidth > 991) {
            sidebar?.classList.remove('open');
            overlay?.classList.remove('active');
            document.body.classList.remove('overflow-hidden', 'max-[991px]:overflow-hidden');
        }
    });
}

/** Drag-to-scroll for horizontal containers (filter-bar, tables) on tablet/desktop */
function initDragToScroll() {
    document.querySelectorAll('.filter-bar, .table-responsive').forEach((slider) => {
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

/** Backdrop click closes any open .custom-modal-backdrop */
function initModalBackdrops() {
    document.querySelectorAll('.custom-modal-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) backdrop.classList.remove('active');
        });
    });
}

/** Generic live-search: [data-search-input] filters [data-search-row] by their [data-search-text]. */
function initSearch() {
    document.querySelectorAll('[data-search-input]').forEach((input) => {
        const scope = input.dataset.searchScope ? document.getElementById(input.dataset.searchScope) : document;
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            scope.querySelectorAll('[data-search-row]').forEach((row) => {
                const text = (row.dataset.searchText || row.textContent).toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });
}

/** Generic filter-bar: [data-filter-btn] toggles [data-filter-row] by data-status. */
function initFilterButtons() {
    document.querySelectorAll('[data-filter-bar]').forEach((bar) => {
        const scopeId = bar.dataset.filterScope;
        const scope = scopeId ? document.getElementById(scopeId) : document;

        bar.querySelectorAll('[data-filter-btn]').forEach((btn) => {
            btn.addEventListener('click', () => {
                bar.querySelectorAll('[data-filter-btn]').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');

                const filter = btn.dataset.filterBtn;
                scope.querySelectorAll('[data-filter-row]').forEach((row) => {
                    const statuses = (row.dataset.status || '').split(' ');
                    row.style.display = (filter === 'all' || statuses.includes(filter)) ? '' : 'none';
                });
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initModalBackdrops();
    initSearch();
    initFilterButtons();
    initDragToScroll();

    // Global keyboard listener for Escape key to close modals and sidebar
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar?.classList.remove('open');
            overlay?.classList.remove('active');
            document.body.classList.remove('overflow-hidden', 'max-[991px]:overflow-hidden');
            document.querySelectorAll('.custom-modal-backdrop.active').forEach((backdrop) => {
                backdrop.classList.remove('active');
            });
        }
    });
});

window.PAIRfectAdmin = { showToast, csrfToken };
window.openModal = openModal;
window.closeModal = closeModal;
