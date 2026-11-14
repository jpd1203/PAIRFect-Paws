// Preview only: policy comes from the backend and life_stage is never submitted.
(() => {
    function initialize() {
        document.querySelectorAll('[data-life-stage-output]').forEach(output => {
            if (output.dataset.lifeStageBound) return;
            const form = output.closest('form');
            if (!form) return;
            let policy;
            try { policy = JSON.parse(output.dataset.lifeStagePolicy); } catch { return; }
            const species = form.querySelector('[name="species"]');
            const years = form.querySelector('[name="age_years"]');
            const months = form.querySelector('[name="age_months"]');
            if (!species || !years || !months) return;
            function update() {
                output.value = 'Unknown';
                const y = years.value.trim(), m = months.value.trim();
                if ((!y && !m) || !/^\d*$/.test(y) || !/^\d*$/.test(m)) return;
                const yr = Number(y || 0), mo = Number(m || 0), age = yr * 12 + mo;
                const band = Object.hasOwn(policy, species.value) ? policy[species.value] : null;
                if (!band || !Number.isSafeInteger(age) || mo > 11 || age > 2147483647) return;
                output.value = age < band.adult ? 'Young' : age < band.senior ? 'Adult' : 'Senior';
            }
            output.dataset.lifeStageBound = '1';
            for (const input of [species, years, months]) {
                input.addEventListener('input', update);
                input.addEventListener('change', update);
            }
            form.addEventListener('pairfect:pet-fields-loaded', update);
            form.addEventListener('reset', () => queueMicrotask(update));
            window.addEventListener('pageshow', update);
            update();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
