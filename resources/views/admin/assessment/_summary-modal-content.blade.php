@php
    function getLabel($value, $type) {
        if (empty($value) || $value <= 0) return 'Unknown';
        $labels = [
            'energy' => [1 => 'Calm', 2 => 'Low', 3 => 'Moderate', 4 => 'High', 5 => 'Very High'],
            'trainability' => [1 => 'Stubborn', 2 => 'Independent', 3 => 'Average', 4 => 'Eager', 5 => 'Highly Trainable'],
            'independence' => [1 => 'Clingy', 2 => 'Affectionate', 3 => 'Balanced', 4 => 'Independent', 5 => 'Solitary'],
            'temperament' => [1 => 'Fearful', 2 => 'Cautious', 3 => 'Stable', 4 => 'Confident', 5 => 'Fearless'],
        ];
        return $labels[$type][round($value)] ?? 'Unknown';
    }

    $rows = [
        ['label' => 'Energy Level', 'value' => $summary['energy_level'], 'tag' => getLabel($summary['energy_level'], 'energy'), 'icon' => 'fa-bolt'],
        ['label' => 'Trainability', 'value' => $summary['trainability'], 'tag' => getLabel($summary['trainability'], 'trainability'), 'icon' => 'fa-graduation-cap'],
        ['label' => 'Independence', 'value' => $summary['independence'], 'tag' => getLabel($summary['independence'], 'independence'), 'icon' => 'fa-compass'],
        ['label' => 'Temperament (Calmness / Safety)', 'value' => $summary['temperament'], 'tag' => getLabel($summary['temperament'], 'temperament'), 'icon' => 'fa-shield-heart'],
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
                {{ $animal->species_display }} &middot; {{ $animal->breed ?? 'Mix' }} &middot; {{ $animal->age_display }} &middot; {{ $animal->assessment_records_count }}/3 assessments
            </small>
        </div>
    </div>
</div>

<!-- Body -->
<div class="custom-modal-body space-y-6">

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
                            <span class="font-bold text-sm text-gray-900">{{ number_format($row['value'], 1) }}<span class="text-xs text-gray-400 font-normal">/5</span></span>
                        </div>
                    </div>
                    <div class="w-full h-2.5 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full rounded-full bg-maroon-600 transition-all duration-300" style="width: {{ min(100, max(0, ($row['value'] / 5) * 100)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Safety & Behavioral Flags -->
    <div>
        <h4 class="font-bold text-sm text-gray-900 uppercase tracking-wide mb-2 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
            <span>Safety & Health Observations</span>
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Dog / Cat Reactivity</span>
                <span class="text-sm font-semibold {{ $animal->is_reactive_to_pets ? 'text-amber-700' : 'text-emerald-700' }}">
                    <i class="fa-solid {{ $animal->is_reactive_to_pets ? 'fa-triangle-exclamation mr-1' : 'fa-check mr-1' }}"></i>
                    {{ $animal->is_reactive_to_pets ? 'Reactive to other animals' : 'Non-reactive / Calm' }}
                </span>
            </div>
            <div class="p-3 rounded-xl border border-gray-200 bg-white">
                <span class="text-xs text-gray-500 block">Aggression History</span>
                <span class="text-sm font-semibold {{ $animal->has_aggression_history ? 'text-red-700' : 'text-emerald-700' }}">
                    <i class="fa-solid {{ $animal->has_aggression_history ? 'fa-triangle-exclamation mr-1' : 'fa-shield mr-1' }}"></i>
                    {{ $animal->has_aggression_history ? 'Reported incident' : 'No history of aggression' }}
                </span>
            </div>
        </div>
    </div>

</div>

<!-- Footer -->
<div class="custom-modal-footer">
    <button type="button" class="btn btn-primary w-full sm:w-auto bg-[#A61D24] text-white hover:bg-[#8D171E] cursor-pointer" onclick="closeModal('assessmentSummaryModal')">Close</button>
</div>

