@php
    use App\Models\PetAssessment;
    $rows = [
        ['label' => 'Energy Level', 'value' => $summary['energy_level'], 'tag' => PetAssessment::energyLabel($summary['energy_level'])],
        ['label' => 'Trainability', 'value' => $summary['trainability'], 'tag' => PetAssessment::trainabilityLabel($summary['trainability'])],
        ['label' => 'Independence', 'value' => $summary['independence'], 'tag' => PetAssessment::independenceLabel($summary['independence'])],
        ['label' => 'Temperament (Fearfulness)', 'value' => $summary['temperament'], 'tag' => PetAssessment::temperamentLabel($summary['temperament'])],
    ];
@endphp

<div class="custom-modal-header flex items-start justify-between">
    <div>
        <h2>Assessment Summary — {{ $animal->name }}</h2>
        <small>{{ $animal->breed }} &middot; {{ $animal->species }} &middot; {{ $animal->age_display }} &middot;</small>
    </div>
    <button type="button" class="text-[#999] text-xl leading-none hover:text-text-dark" onclick="closeModal('assessmentSummaryModal')">&times;</button>
</div>

<div class="custom-modal-body">

    <h3 class="font-bold text-[1.05rem] mb-4">Behavioral Profile</h3>

    <div class="flex flex-col gap-5">
        @foreach ($rows as $row)
            <div>
                <div class="flex justify-between items-baseline mb-1.5">
                    <span class="font-semibold text-[.95rem]">{{ $row['label'] }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-2.5 rounded-full bg-[#e6e2da] overflow-hidden">
                        <div class="h-full rounded-full bg-primary" style="width: {{ ($row['value'] / 4) * 100 }}%"></div>
                    </div>
                    <span class="text-[#777] text-[.85rem] w-[110px] shrink-0">{{ $row['tag'] }}</span>
                    <span class="font-bold text-[1.05rem] shrink-0">{{ number_format($row['value'], 1) }}<span class="text-[#999] font-normal text-[.85rem]">/4</span></span>
                </div>
            </div>
        @endforeach
    </div>

</div>
