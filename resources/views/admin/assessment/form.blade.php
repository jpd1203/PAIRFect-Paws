@extends('admin.layouts.app')

@section('title', "{$animal->species_display} Pet Assessment - PAIRfect Paws Admin")

@section('content')
<div class="form-content">

    <div class="assessment-header">
        <div class="heading-text">
            <h2>Pet Assessment</h2>
            <p>Assessment of {{ $animal->species_display }} Behavioral Characteristics — {{ $animal->name }} (Assessment #{{ $assessmentNumber }} of 3)</p>
        </div>
    </div>

    <div class="rating-guide">
        <strong>1</strong> = Never &nbsp; <strong>2</strong> = Seldom &nbsp; <strong>3</strong> = Sometimes &nbsp; <strong>4</strong> = Usually &nbsp; <strong>5</strong> = Always
    </div>

    <form action="{{ route('admin.assessments.store', $animal) }}" method="POST" id="assessmentForm">
        @csrf
        <div class="assessment-scroll custom-scrollbar">
            <div class="assessment-body">
                <div class="assessment-container !p-0 max-[991px]:!p-0">

                    @foreach ($categories as $catKey => $cat)
                        <div class="assessment-card">
                            <h4>{{ $cat['label'] ?? $catKey }}</h4>

                            @if ($cat['type'] === 'flat')
                                <div class="question-grid">
                                    @php $iteration = 1; @endphp
                                    @foreach ($cat['items'] as $qKey => $text)
                                        <div class="question">
                                            <label>{{ $iteration++ }}. {{ $text }}</label>
                                            <div class="slider-row">
                                                <span>1</span>
                                                <input type="range" min="1" max="5" step="1" value="1" class="rating-slider"
                                                    name="{{ $qKey }}" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                <span>5</span>
                                                <div class="value">1</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                @php
                                    $colLabels = array_keys($cat['columns']);
                                    $col0Keys = array_keys($cat['columns'][$colLabels[0]]);
                                    $col1Keys = isset($colLabels[1]) ? array_keys($cat['columns'][$colLabels[1]]) : [];
                                    $maxRows = max(count($col0Keys), count($col1Keys));
                                @endphp
                                <div class="question-grid">
                                    <label class="temp-categ">{{ $colLabels[0] }}</label>
                                    @if(isset($colLabels[1])) <label class="temp-categ">{{ $colLabels[1] }}</label> @endif
                                </div>
                                <div class="question-grid">
                                    @for ($row = 0; $row < $maxRows; $row++)
                                        {{-- Column 1 --}}
                                        @if (isset($col0Keys[$row]))
                                            @php $qKey = $col0Keys[$row]; $text = $cat['columns'][$colLabels[0]][$qKey]; @endphp
                                            <div class="question">
                                                <label>{{ $row + 1 }}. {{ $text }}</label>
                                                <div class="slider-row">
                                                    <span>1</span>
                                                    <input type="range" min="1" max="5" step="1" value="1" class="rating-slider"
                                                        name="{{ $qKey }}" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                    <span>5</span>
                                                    <div class="value">1</div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="question"></div>
                                        @endif

                                        {{-- Column 2 --}}
                                        @if(isset($colLabels[1]))
                                            @if (isset($col1Keys[$row]))
                                                @php $qKey = $col1Keys[$row]; $text = $cat['columns'][$colLabels[1]][$qKey]; @endphp
                                                <div class="question">
                                                    <label>{{ $row + 1 }}. {{ $text }}</label>
                                                    <div class="slider-row">
                                                        <span>1</span>
                                                        <input type="range" min="1" max="5" step="1" value="1" class="rating-slider"
                                                            name="{{ $qKey }}" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                        <span>5</span>
                                                        <div class="value">1</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="question"></div>
                                            @endif
                                        @endif
                                    @endfor
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="assessment-card">
                        <h4>KNN Matching Flags</h4>
                        <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem">
                            These fields power the pet-recommendation algorithm. Set them based on your behavioural observations above.
                        </p>

                        <div class="question-grid">
                            <div class="question">
                                <label>Medical Needs Level (1–5)</label>
                                <div class="slider-row">
                                    <span>1</span>
                                    <input type="range" min="1" max="5" step="1" value="{{ old('medical_needs', 1) }}"
                                        class="rating-slider" name="medical_needs"
                                        oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                    <span>5</span>
                                    <div class="value">{{ old('medical_needs', 1) }}</div>
                                </div>
                                <small style="color:var(--muted);font-size:.75rem">1 = Routine care only · 5 = Intensive ongoing medical care</small>
                            </div>

                            <div class="question" style="align-self:start">
                                <label>Safety Flags</label>
                                <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.5rem">
                                    <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;cursor:pointer">
                                        <input type="checkbox" name="is_reactive_to_pets" value="1"
                                            {{ old('is_reactive_to_pets') ? 'checked' : '' }}>
                                        Reactive or aggressive toward other animals
                                    </label>
                                    <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;cursor:pointer">
                                        <input type="checkbox" name="has_aggression_history" value="1"
                                            {{ old('has_aggression_history') ? 'checked' : '' }}>
                                        Has documented aggression or high-fear history toward people
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                </div>
            </div>
            
        </div>
        
        <div class="button-group bottom-0 pt-2">
            <a href="{{ route('admin.assessments.record') }}" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Assessment</button>
        </div>

    </form>

</div>
@endsection