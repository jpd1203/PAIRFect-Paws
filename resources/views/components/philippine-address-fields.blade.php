@props([
    'address' => [],
    'idPrefix' => 'address',
    'compact' => false,
])

@php
    $address = \App\Support\PhilippineAddress::normalize(is_array($address) ? $address : []);
    $selected = [
        'region_code' => old('region_code', ''),
        'province_code' => old('province_code', ''),
        'city_municipality_code' => old('city_municipality_code', ''),
        'barangay_code' => old('barangay_code', ''),
    ];
@endphp

<fieldset
    class="psgc-address {{ $compact ? 'psgc-address--compact' : '' }}"
    data-psgc-address
    data-regions-url="{{ route('locations.regions') }}"
    data-provinces-url="{{ route('locations.provinces') }}"
    data-localities-url="{{ route('locations.localities') }}"
    data-barangays-url="{{ route('locations.barangays') }}"
    data-selected-region-code="{{ $selected['region_code'] }}"
    data-selected-province-code="{{ $selected['province_code'] }}"
    data-selected-locality-code="{{ $selected['city_municipality_code'] }}"
    data-selected-barangay-code="{{ $selected['barangay_code'] }}"
    data-selected-region-name="{{ $address['region'] }}"
    data-selected-province-name="{{ $address['province'] }}"
    data-selected-locality-name="{{ $address['city_municipality'] }}"
    data-selected-barangay-name="{{ $address['barangay'] }}"
>
    <legend>Philippine Address</legend>

    <div class="psgc-address-grid">
        <div class="psgc-field">
            <label for="{{ $idPrefix }}_region">Region*</label>
            <div class="select-wrapper">
                <select id="{{ $idPrefix }}_region" name="region_code" data-address-region required disabled>
                    <option value="">Loading regions...</option>
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>
            @error('region_code') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="psgc-field">
            <label for="{{ $idPrefix }}_province">Province</label>
            <div class="select-wrapper">
                <select id="{{ $idPrefix }}_province" name="province_code" data-address-province disabled>
                    <option value="">Select a region first</option>
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>
            @error('province_code') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="psgc-field">
            <label for="{{ $idPrefix }}_locality">City / Municipality*</label>
            <div class="select-wrapper">
                <select id="{{ $idPrefix }}_locality" name="city_municipality_code" data-address-locality required disabled>
                    <option value="">Select a province first</option>
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
            </div>
            @error('city_municipality_code') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="psgc-field">
            <label for="{{ $idPrefix }}_barangay">Barangay*</label>
            <div class="select-wrapper">
                <select id="{{ $idPrefix }}_barangay" name="barangay_code" data-address-barangay required disabled>
                    <option value="">Select a city or municipality first</option>
                </select>
                <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
            @error('barangay_code') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="psgc-field psgc-field--wide">
            <label for="{{ $idPrefix }}_street">House No., Street, Building, or Subdivision*</label>
            <input
                id="{{ $idPrefix }}_street"
                name="street_address"
                type="text"
                value="{{ old('street_address', $address['street_address']) }}"
                maxlength="1000"
                autocomplete="street-address"
                required
            >
            @error('street_address') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="psgc-field">
            <label for="{{ $idPrefix }}_zip">ZIP Code</label>
            <input
                id="{{ $idPrefix }}_zip"
                name="zip_code"
                type="text"
                value="{{ old('zip_code', $address['zip_code']) }}"
                inputmode="numeric"
                pattern="[0-9]{4}"
                maxlength="4"
                autocomplete="postal-code"
                placeholder="e.g. 1008"
                
            >
            @error('zip_code') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <p class="psgc-address-status" data-address-status role="status" aria-live="polite"></p>
    <p class="psgc-address-source">
        Location names use the
        <a href="https://psa.gov.ph/classification/psgc" target="_blank" rel="noopener noreferrer">Philippine Statistics Authority PSGC 2Q 2026</a>
        local dataset.
    </p>
</fieldset>
