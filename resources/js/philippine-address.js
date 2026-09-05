const DIRECT_REGION_VALUE = '__direct__';

function optionFor(items, code, name) {
    if (code) {
        const byCode = items.find(item => String(item.code) === String(code));
        if (byCode) return byCode;
    }

    const normalizedName = String(name ?? '').trim().toLocaleLowerCase();
    return normalizedName
        ? items.find(item => String(item.name ?? '').trim().toLocaleLowerCase() === normalizedName)
        : null;
}

function populate(select, items, placeholder, selectedItem = null) {
    select.replaceChildren(new Option(placeholder, ''));
    items.forEach(item => {
        const option = new Option(item.name, item.code);
        if (item.type) option.dataset.type = item.type;
        if (item.zip_code) option.dataset.zipCode = item.zip_code;
        select.add(option);
    });

    if (selectedItem) select.value = selectedItem.code;
    select.disabled = false;
}

function reset(select, message) {
    select.replaceChildren(new Option(message, ''));
    select.disabled = true;
}

async function getJson(url, params = {}) {
    const requestUrl = new URL(url, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            requestUrl.searchParams.set(key, value);
        }
    });

    const response = await fetch(requestUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error('Location data could not be loaded.');
    return response.json();
}

function initializeAddress(block) {
    const region = block.querySelector('[data-address-region]');
    const province = block.querySelector('[data-address-province]');
    const locality = block.querySelector('[data-address-locality]');
    const barangay = block.querySelector('[data-address-barangay]');
    const status = block.querySelector('[data-address-status]');
    if (!region || !province || !locality || !barangay) return;

    const selected = {
        regionCode: block.dataset.selectedRegionCode,
        provinceCode: block.dataset.selectedProvinceCode,
        localityCode: block.dataset.selectedLocalityCode,
        barangayCode: block.dataset.selectedBarangayCode,
        regionName: block.dataset.selectedRegionName,
        provinceName: block.dataset.selectedProvinceName,
        localityName: block.dataset.selectedLocalityName,
        barangayName: block.dataset.selectedBarangayName,
    };
    let requestVersion = 0;

    const reportError = () => {
        status.textContent = 'Location choices could not be loaded. Refresh the page and try again.';
        status.classList.add('is-error');
    };
    const clearStatus = () => {
        status.textContent = '';
        status.classList.remove('is-error');
    };

    async function loadBarangays(preselected = {}) {
        const version = ++requestVersion;
        reset(barangay, 'Loading barangays...');
        if (!locality.value) {
            reset(barangay, 'Select a city or municipality first');
            return;
        }

        try {
            const payload = await getJson(block.dataset.barangaysUrl, {
                city_municipality_code: locality.value,
            });
            if (version !== requestVersion) return;
            const items = payload.data ?? [];
            populate(
                barangay,
                items,
                'Select barangay',
                optionFor(items, preselected.code, preselected.name),
            );
            clearStatus();
        } catch (_) {
            if (version === requestVersion) reportError();
        }
    }

    async function loadLocalities(preselected = {}, barangayPreselected = {}) {
        const version = ++requestVersion;
        reset(locality, 'Loading cities and municipalities...');
        reset(barangay, 'Select a city or municipality first');
        if (!region.value || !province.value) {
            reset(locality, 'Select a province first');
            return;
        }

        try {
            const payload = await getJson(block.dataset.localitiesUrl, {
                region_code: region.value,
                province_code: province.value,
            });
            if (version !== requestVersion) return;
            const items = payload.data ?? [];
            const selectedItem = optionFor(items, preselected.code, preselected.name);
            populate(locality, items, 'Select city or municipality', selectedItem);
            clearStatus();
            if (selectedItem) await loadBarangays(barangayPreselected);
        } catch (_) {
            if (version === requestVersion) reportError();
        }
    }

    async function loadProvinces(preselected = {}, localityPreselected = {}, barangayPreselected = {}) {
        const version = ++requestVersion;
        reset(province, 'Loading provinces...');
        reset(locality, 'Select a province first');
        reset(barangay, 'Select a city or municipality first');
        if (!region.value) return;

        try {
            const payload = await getJson(block.dataset.provincesUrl, { region_code: region.value });
            if (version !== requestVersion) return;
            const items = payload.data ?? [];
            if (payload.has_direct_localities) {
                items.push({
                    code: DIRECT_REGION_VALUE,
                    name: items.length === 0
                        ? 'No province (directly administered)'
                        : 'Independent city / no province',
                });
            }

            let selectedItem = optionFor(items, preselected.code, preselected.name);
            if (!selectedItem && !preselected.name && localityPreselected.name && payload.has_direct_localities) {
                selectedItem = items.find(item => item.code === DIRECT_REGION_VALUE);
            }
            if (!selectedItem && items.length === 1 && items[0].code === DIRECT_REGION_VALUE) {
                selectedItem = items[0];
            }

            populate(province, items, 'Select province or direct city', selectedItem);
            clearStatus();
            if (selectedItem) await loadLocalities(localityPreselected, barangayPreselected);
        } catch (_) {
            if (version === requestVersion) reportError();
        }
    }

    region.addEventListener('change', () => loadProvinces());
    province.addEventListener('change', () => loadLocalities());
    locality.addEventListener('change', () => loadBarangays());

    getJson(block.dataset.regionsUrl)
        .then(async payload => {
            const items = payload.data ?? [];
            const selectedRegion = optionFor(items, selected.regionCode, selected.regionName);
            populate(region, items, 'Select region', selectedRegion);
            clearStatus();
            if (selectedRegion) {
                await loadProvinces(
                    { code: selected.provinceCode, name: selected.provinceName },
                    { code: selected.localityCode, name: selected.localityName },
                    { code: selected.barangayCode, name: selected.barangayName },
                );
            }
        })
        .catch(reportError);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-psgc-address]').forEach(initializeAddress);
});
