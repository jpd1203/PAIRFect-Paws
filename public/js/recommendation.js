/**
 * Pet Recommendation results screen — dragging a "Pet Characteristics"
 * slider live-recomputes compatibility for every pet via AJAX and updates
 * each match card's bars, overall score, and ranking order in place.
 */
(function () {
    const panel = document.getElementById('recoPanel');
    const resultsContainer = document.getElementById('matchResults');
    if (!panel || !resultsContainer || !window.RECO_RECOMPUTE_URL) return;

    const descriptions = JSON.parse(document.getElementById('sliderDescriptions')?.textContent || '{}');
    const sliders = panel.querySelectorAll('[data-slider-input]');

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    function currentValues() {
        const values = {};
        sliders.forEach((el) => { values[el.dataset.sliderInput] = Number(el.value); });
        return values;
    }

    function updateSliderUi(el) {
        const key = el.dataset.sliderInput;
        const group = el.closest('[data-slider-group]');
        const value = Number(el.value);

        group.querySelector('[data-slider-value]').textContent = value;
        group.querySelector('[data-slider-desc]').textContent = descriptions[key]?.[value] ?? '';
    }

    function barColor(percent) {
        if (percent >= 60) return '#295F51';
        if (percent >= 35) return '#614E34';
        return '#773E47';
    }

    function updateCard(card, match) {
        const rowEls = card.querySelectorAll('[data-rows] > div');
        match.rows.forEach((row, i) => {
            const rowEl = rowEls[i];
            if (!rowEl) return;
            const fill = rowEl.querySelector('.compat-bar-fill');
            const pct = rowEl.querySelector('span:last-child');
            fill.style.width = `${row.percent}%`;
            fill.style.background = barColor(row.percent);
            pct.textContent = `${row.percent}%`;
        });

        card.querySelector('[data-overall-score]').textContent = `${match.overall}/100`;
        card.querySelector('[data-match-label]').textContent = match.match_label;
    }

    let debounceTimer;
    async function recompute() {
        try {
            const res = await fetch(window.RECO_RECOMPUTE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    ...currentValues(),
                    profile: window.RECO_PROFILE || {}
                }),
            });

            if (!res.ok) throw new Error('Recompute failed');
            const { matches } = await res.json();

            matches.forEach((match) => {
                const card = resultsContainer.querySelector(`[data-pet-card="${match.id}"]`);
                if (card) updateCard(card, match);
            });

            // Re-order cards to match the new ranking, highest score first
            matches.forEach((match) => {
                const card = resultsContainer.querySelector(`[data-pet-card="${match.id}"]`);
                if (card) resultsContainer.appendChild(card);
            });
        } catch (err) {
            window.PAIRfectPaws?.showToast('Could not refresh compatibility scores.', 'error');
        }
    }

    sliders.forEach((el) => {
        el.addEventListener('input', () => {
            updateSliderUi(el);
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(recompute, 200);
        });
    });
})();
