@extends('admin.layouts.app')

@section('title', "{$animal->species} Pet Assessment - PAIRfect Paws Admin")

@section('content')
<div class="form-content">

    <div class="assessment-header">
        <div class="heading-text">
            <h2>Pet Assessment</h2>
            <p>Assessment of {{ $animal->species }} Behavioral Characteristics — {{ $animal->name }} (Assessment #{{ $assessmentNumber }} of 3)</p>
        </div>
    </div>

    <div class="rating-guide">
        <strong>0</strong> = Never &nbsp; <strong>1</strong> = Seldom &nbsp; <strong>2</strong> = Sometimes &nbsp; <strong>3</strong> = Usually &nbsp; <strong>4</strong> = Always
    </div>

    <form action="{{ route('admin.assessments.store', $animal) }}" method="POST" id="assessmentForm" class="flex-1 flex flex-col min-h-0">
        @csrf
        <div class="assessment-scroll">
            <div class="assessment-body">
                <div class="assessment-container !p-0 max-[991px]:!p-0">

                    @foreach ($categories as $catKey => $cat)
                        <div class="assessment-card">
                            <h4>{{ $cat['label'] }}</h4>

                            @if ($cat['type'] === 'flat')
                                <div class="question-grid">
                                    @foreach ($cat['items'] as $i => $text)
                                        <div class="question">
                                            <label>{{ $i + 1 }}. {{ $text }}</label>
                                            <div class="slider-row">
                                                <span>0</span>
                                                <input type="range" min="0" max="4" step="1" value="0" class="rating-slider"
                                                    name="{{ $catKey }}_{{ $i }}" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                <span>4</span>
                                                <div class="value">0</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                @php $colLabels = array_keys($cat['columns']); $colLists = array_values($cat['columns']); @endphp
                                <div class="question-grid">
                                    <label class="temp-categ">{{ $colLabels[0] }}</label>
                                    <label class="temp-categ">{{ $colLabels[1] }}</label>
                                </div>
                                <div class="question-grid">
                                    @php $maxRows = max(count($colLists[0]), count($colLists[1])); @endphp
                                    @for ($row = 0; $row < $maxRows; $row++)
                                        @for ($col = 0; $col < 2; $col++)
                                            @if (isset($colLists[$col][$row]))
                                                <div class="question">
                                                    <label>{{ $row + 1 }}. {{ $colLists[$col][$row] }}</label>
                                                    <div class="slider-row">
                                                        <span>0</span>
                                                        <input type="range" min="0" max="4" step="1" value="0" class="rating-slider"
                                                            name="{{ $catKey }}_{{ $col }}_{{ $row }}" oninput="this.nextElementSibling.nextElementSibling.textContent=this.value">
                                                        <span>4</span>
                                                        <div class="value">0</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="question"></div>
                                            @endif
                                        @endfor
                                    @endfor
                                </div>
                            @endif
                        </div>
                    @endforeach

                </div>
            </div>
        </div>

        <div class="button-group sticky bottom-0 pt-2">
            <a href="{{ route('admin.assessments.record') }}" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary">Save Assessment</button>
        </div>

    </form>

</div>
@endsection
