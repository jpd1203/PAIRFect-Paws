/** Native controls; ranges are serialized from PetSizeClassifier, never calculated here. */
(() => {
    function initRecommendationSizeFilters() {
        const form = document.getElementById('recommendationFilters');
        const species = document.getElementById('recommendationSpecies');
        const size = document.getElementById('recommendationSize');
        const help = document.getElementById('recommendationSizeHelp');
        const configuration = document.getElementById('recommendationSizeOptions');
        if (!form || !species || !size || !configuration) return;

        const optionsBySpecies = JSON.parse(configuration.textContent);

        function render(selectedSpecies, selectedSize = '', changed = false) {
            const available = Object.hasOwn(optionsBySpecies, selectedSpecies) ? optionsBySpecies[selectedSpecies] : [];
            const entries = [{ value: '', label: 'All Sizes' }, ...available];
            size.replaceChildren(...entries.map(entry => {
                const option = document.createElement('option');
                option.value = entry.value;
                option.textContent = entry.label;
                return option;
            }));
            size.disabled = available.length === 0;
            size.value = available.some(option => option.value === selectedSize) ? selectedSize : '';
            if (help) {
                help.textContent = size.disabled ? 'Select Dog or Cat before choosing a size.'
                    : selectedSpecies + ' adult weight ranges. ' + (changed ? 'Size reset to All Sizes.' : 'Choose All Sizes or a weight category.');
            }
        }

        function restoreFromLocation() {
            // Canonical GET state wins over browser-restored, unsubmitted form edits.
            const parameters = new URL(window.location.href).searchParams;
            const selectedSpecies = parameters.get('species') ?? '';
            species.value = Object.hasOwn(optionsBySpecies, selectedSpecies) ? selectedSpecies : '';
            render(species.value, parameters.get('size') ?? '');
        }

        species.addEventListener('change', () => render(species.value, '', true));
        window.addEventListener('pageshow', restoreFromLocation);
        window.addEventListener('popstate', restoreFromLocation);
        restoreFromLocation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRecommendationSizeFilters, { once: true });
    } else {
        initRecommendationSizeFilters();
    }
})();
