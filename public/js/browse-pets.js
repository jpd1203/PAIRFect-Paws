/**
 * Browse Pets — species/age filter dropdowns re-fetch the pet grid via AJAX
 * (GET, read-only, no CSRF needed) instead of a full page reload.
 */
(function () {
    const speciesFilter = document.getElementById('speciesFilter');
    const ageFilter = document.getElementById('ageFilter');
    const gridContainer = document.getElementById('petGridContainer');

    if (!speciesFilter || !ageFilter || !gridContainer) return;

    async function refreshGrid() {
        gridContainer.classList.add('pet-grid', 'is-loading');

        const params = new URLSearchParams({
            species: speciesFilter.value,
            age: ageFilter.value,
        });

        try {
            const res = await fetch(`/pets?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('Failed to filter pets');
            gridContainer.innerHTML = await res.text();

            // reflect the filter state in the URL without a full navigation
            window.history.replaceState({}, '', `?${params.toString()}`);
        } catch (err) {
            window.PAIRfectPaws?.showToast('Could not load pets. Please try again.', 'error');
        } finally {
            gridContainer.classList.remove('is-loading');
        }
    }

    speciesFilter.addEventListener('change', refreshGrid);
    ageFilter.addEventListener('change', refreshGrid);
})();
