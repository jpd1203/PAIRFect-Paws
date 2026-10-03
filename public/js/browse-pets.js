/**
 * Browse Pets — species/age filter dropdowns re-fetch the pet grid via AJAX
 * (GET, read-only, no CSRF needed) instead of a full page reload.
 */
(function () {
    const speciesFilter = document.getElementById('speciesFilter');
    const ageFilter = document.getElementById('ageFilter');
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');
    const gridContainer = document.getElementById('petGridContainer');

    if (!speciesFilter || !ageFilter || !gridContainer) return;

    function updateResetButton() {
        const filterUsed =
            speciesFilter.value !== 'All Species' ||
            ageFilter.value !== 'All Ages';

        if (resetFiltersBtn) {
            resetFiltersBtn.style.display = filterUsed ? 'inline-flex' : 'none';
        }
    }

    async function refreshGrid() {
        gridContainer.classList.add('is-loading');

        const params = new URLSearchParams({
            species: speciesFilter.value,
            age: ageFilter.value,
        });

        const endpoint = window.location.pathname.includes('pets') ? '/pets' : '/animal';

        try {
            const res = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) throw new Error('Failed to filter pets');

            gridContainer.innerHTML = await res.text();

            // Reflect the filter state in the URL without a full navigation
            window.history.replaceState({}, '', `?${params.toString()}`);
        } catch (err) {
            window.PAIRfectPaws?.showToast(
                'Could not load pets. Please try again.',
                'error'
            );
        } finally {
            gridContainer.classList.remove('is-loading');
        }

        // Show/hide Reset button after filtering
        updateResetButton();
    }

    speciesFilter.addEventListener('change', refreshGrid);
    ageFilter.addEventListener('change', refreshGrid);

    // Reset filters
    resetFiltersBtn?.addEventListener('click', function () {
        speciesFilter.value = 'All Species';
        ageFilter.value = 'All Ages';

        refreshGrid();
    });

    // Set the correct Reset button state when the page loads
    updateResetButton();
})();