@php
    $getLabel = function ($value, $type) {
        if ($value === null) return 'Not enough observations';
        $labels = [
            'energy' => [1 => 'Calm', 2 => 'Low', 3 => 'Moderate', 4 => 'High', 5 => 'Very High'],
            'trainability' => [1 => 'Stubborn', 2 => 'Independent', 3 => 'Average', 4 => 'Eager', 5 => 'Highly Trainable'],
            'independence' => [1 => 'Clingy', 2 => 'Affectionate', 3 => 'Balanced', 4 => 'Independent', 5 => 'Solitary'],
            'temperament' => [1 => 'Calm', 2 => 'Mostly calm', 3 => 'Mixed', 4 => 'Reactive', 5 => 'Highly reactive'],
        ];
        return $labels[$type][round($value)] ?? 'Unknown';
    };

    $rows = [
        ['label' => 'Energy Level', 'value' => $summary['features']['energy'], 'tag' => $getLabel($summary['features']['energy'], 'energy'), 'icon' => 'fa-bolt'],
        ['label' => 'Trainability', 'value' => $summary['features']['trainability'], 'tag' => $getLabel($summary['features']['trainability'], 'trainability'), 'icon' => 'fa-graduation-cap'],
        ['label' => 'Independence', 'value' => $summary['features']['independence'], 'tag' => $getLabel($summary['features']['independence'], 'independence'), 'icon' => 'fa-compass'],
        ['label' => 'Fearfulness / Reactivity', 'value' => $summary['features']['temperament'], 'tag' => $getLabel($summary['features']['temperament'], 'temperament'), 'icon' => 'fa-shield-heart'],
    ];
@endphp

<!-- Header -->
<div class="custom-modal-header">
    <div class="flex items-center gap-3">
        @if ($animal->photo_path)
            <div class="w-11 h-11 rounded-xl overflow-hidden shrink-0 border border-gray-200 bg-gray-100">
                <img src="{{ $animal->image_url }}" alt="{{ $animal->name }}" class="w-full h-full object-cover">
            </div>
        @else
            <div class="w-11 h-11 rounded-xl shrink-0 flex items-center justify-center bg-maroon-50 text-maroon-600 border border-maroon-100 text-base">
                <i class="fa-solid fa-{{ strtolower($animal->species?->value ?? $animal->species) === 'cat' ? 'cat' : 'dog' }}"></i>
            </div>
        @endif
        <div>
            <h3 class="font-bold text-gray-900 text-lg">Assessment Summary — {{ $animal->name }}</h3>
            <small class="text-gray-500 text-xs sm:text-sm">
                {{ $animal->species_display }} &middot; {{ $animal->breed ?? 'Mix' }} &middot; {{ $animal->age_display }} &middot; {{ $summary['observer_count'] }}/{{ config('matching.min_observers') }} distinct observers
            </small>
        </div>
    </div>
</div>

<!-- Body -->
<div class="custom-modal-body space-y-6">
    @if (! $summary['complete'])
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">The behavioral assessment needs {{ config('matching.min_observers') }} distinct observers and enough answers in each behavior group.</p>
    @endif
    @if ($missingInformation !== [])
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Matching is incomplete until these details are verified: {{ implode(', ', $missingInformation) }}.</p>
    @endif

    <div>
        <h4 class="font-bold text-sm text-gray-900 uppercase tracking-wide mb-3 flex items-center gap-2">
            <i class="fa-solid fa-chart-simple text-maroon-600"></i>
            <span>Behavioral Trait Scores</span>
        </h4>

        <div class="flex flex-col gap-4 bg-gray-50/70 p-4 rounded-xl border border-gray-200">
            @foreach ($rows as $row)
                <div>
                    <div class="flex justify-between items-baseline mb-1.5">
                        <span class="font-semibold text-sm text-gray-800 flex items-center gap-1.5">
                            <i class="fa-solid {{ $row['icon'] }} text-xs text-gray-400"></i>
                            {{ $row['label'] }}
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-white border border-gray-200 text-gray-700">{{ $row['tag'] }}</span>
                            <span class="font-bold text-sm text-gray-900">{{ $row['value'] === null ? '—' : number_format($row['value'], 1) }}<span class="text-xs text-gray-400 font-normal">/5</span></span>
                        </div>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full rounded-full bg-maroon-600 transition-all duration-300" style="width: {{ min(100, max(0, (($row['value'] ?? 0) / 5) * 100)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Veterinary-assessed matching attributes -->
    <div>
        <h4 class="font-bold text-sm text-gray-900 uppercase tracking-wide mb-2 flex items-center gap-2">
            <i class="fa-solid fa-stethoscope text-maroon-600"></i>
            <span>Veterinary Assessment</span>
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Physical Size</span>
                <span class="text-sm font-semibold text-gray-800">{{ $animal->physical_size ?: 'Not recorded' }}</span>
            </div>
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Medical Needs Level</span>
                <span class="text-sm font-semibold text-gray-800">{{ $animal->medical_needs === null ? 'Not recorded' : number_format($animal->medical_needs, 1).' / 5' }}</span>
            </div>
        </div>
    </div>

    <!-- Shelter profile and safety information -->
    <div>
        <h4 class="font-bold text-sm text-gray-900 uppercase tracking-wide mb-2 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
            <span>Pet Profile and Safety Information</span>
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Life Stage</span>
                <span class="text-sm font-semibold {{ $animal->life_stage === null ? 'text-gray-600' : 'text-gray-800' }}">{{ $animal->life_stage === null ? 'Not verified' : ucfirst($animal->life_stage) }}</span>
            </div>
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Vocalization</span>
                <span class="text-sm font-semibold {{ $animal->high_vocalization === null ? 'text-gray-600' : ($animal->high_vocalization ? 'text-amber-700' : 'text-emerald-700') }}">
                    <i class="fa-solid {{ $animal->high_vocalization === null ? 'fa-circle-question mr-1' : ($animal->high_vocalization ? 'fa-triangle-exclamation mr-1' : 'fa-check mr-1') }}"></i>
                    {{ $animal->high_vocalization === null ? 'Not recorded' : ($animal->high_vocalization ? 'High vocalization' : 'Not high vocalization') }}
                </span>
            </div>
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Aggression History</span>
                <span class="text-sm font-semibold {{ $animal->aggression_history_verified_at === null || $animal->has_aggression_history === null ? 'text-gray-600' : ($animal->has_aggression_history ? 'text-red-700' : 'text-emerald-700') }}">
                    <i class="fa-solid {{ $animal->aggression_history_verified_at === null || $animal->has_aggression_history === null ? 'fa-circle-question mr-1' : ($animal->has_aggression_history ? 'fa-triangle-exclamation mr-1' : 'fa-shield mr-1') }}"></i>
                    {{ $animal->aggression_history_verified_at === null || $animal->has_aggression_history === null ? 'Not verified' : ($animal->has_aggression_history ? 'Reported incident' : 'No history of aggression') }}
                </span>
            </div>
        </div>
    </div>

</div>

<!-- Footer -->
<div class="custom-modal-footer">
    <button type="button" class="btn btn-primary w-full sm:w-auto bg-[#A61D24] text-white hover:bg-[#8D171E] cursor-pointer" onclick="closeModal('assessmentSummaryModal')">Close</button>
</div>
