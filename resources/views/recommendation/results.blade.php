@extends('layouts.app')

@section('title', 'Pet Recommendation - PAIRfect Paws')

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Pet Recommendation</h2>
            <p>Find your most compatible pet based on your lifestyle.</p>
        </div>
    </div>

    <div class="content-area">

        <div class="reco-banner mb-6">
            Fill in your preferred Pet Characteristics. Compatibility scores update live. All pairings are reviewed and decided manually
            by shelter staff — scores are a guide only.
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-6 items-start">

            <!-- LEFT: Pet Characteristics sliders -->
            <div class="reco-panel" id="recoPanel">
                <h3 class="font-primary text-xl mb-5">Pet Characteristics</h3>

                @php
                    $sliderDefs = [
                        'energy' => [
                            'label' => 'Energy Level',
                            'desc' => [
                                1 => 'Very low energy; prefers resting, minimal exercise needed',
                                2 => 'Low energy; short walks and light play are enough',
                                3 => 'Moderate energy; daily walks and some playtime needed',
                                4 => 'High energy; needs regular exercise and stimulation',
                                5 => 'Very high energy; needs vigorous daily exercise and activity',
                            ],
                        ],
                        'trainability' => [
                            'label' => 'Trainability',
                            'desc' => [
                                1 => 'Needs significant patience; slow to pick up commands',
                                2 => 'Below-average trainability; consistent repetition needed',
                                3 => 'Average trainability; learns with regular practice',
                                4 => 'Above average trainability; responds well with consistent effort',
                                5 => 'Highly trainable; picks up new commands quickly',
                            ],
                        ],
                        'medical' => [
                            'label' => 'Medical needs',
                            'desc' => [
                                1 => 'No known medical needs; routine care only',
                                2 => 'Minor needs; occasional vet visits',
                                3 => 'Moderate needs; managed condition requiring regular affordable medication',
                                4 => 'Significant needs; frequent vet visits and monitoring',
                                5 => 'Extensive needs; intensive ongoing veterinary care required',
                            ],
                        ],
                        'independence' => [
                            'label' => 'Independence level',
                            'desc' => [
                                1 => 'Very dependent; wants company at all times',
                                2 => 'Somewhat dependent; prefers company most of the day',
                                3 => 'Prefers company; mild discomfort when alone for extended periods',
                                4 => 'Independent; comfortable alone for extended periods',
                                5 => 'Very independent; content spending most of the day alone',
                            ],
                        ],
                        'temperament' => [
                            'label' => 'Temperament',
                            'desc' => [
                                1 => 'May show aggression or reactivity in certain situations',
                                2 => 'Can be reserved or wary; needs a calm environment',
                                3 => 'Generally friendly; adjusts well with patience',
                                4 => 'Very gentle and adaptable; good with most households',
                                5 => 'Exceptionally gentle, calm, and adaptable; no aggression history whatsoever',
                            ],
                        ],
                    ];
                @endphp

                @foreach ($sliderDefs as $key => $def)
                    <div class="mb-6" data-slider-group="{{ $key }}">
                        <div class="reco-slider-label">
                            <span>{{ $def['label'] }}</span>
                            <span class="text-primary font-bold" data-slider-value>{{ $sliders[$key] }}</span>
                        </div>
                        <input
                            type="range"
                            min="1"
                            max="5"
                            step="1"
                            value="{{ $sliders[$key] }}"
                            class="reco-range"
                            data-slider-input="{{ $key }}"
                        >
                        <p class="reco-slider-hint" data-slider-desc>{{ $def['desc'][$sliders[$key]] }}</p>
                    </div>
                @endforeach

                <script id="sliderDescriptions" type="application/json">
                    {!! json_encode(array_map(fn ($d) => $d['desc'], $sliderDefs)) !!}
                </script>
            </div>

            <!-- RIGHT: Compatibility results -->
            <div class="reco-panel" id="recoPanel">
                <h3 class="font-primary text-xl mb-5">Compatibility results</h3>

                <div id="matchResults" class="flex flex-col gap-4">
                    @foreach ($matches as $match)
                        @include('recommendation._match-card', ['pet' => $match['pet'], 'result' => $match['result'], 'matcher' => $matcher])
                    @endforeach
                </div>
            </div>

        </div>

    </div>

    <!-- Modal Overlay (shared "View" pet profile modal) -->
    <div class="modal-overlay" id="petModal">
        <div class="pet-modal" id="petModalContent">
            <!-- Filled in dynamically via fetch() -->
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.RECO_RECOMPUTE_URL = @json(route('recommendation.recompute'));
    </script>
    <script src="{{ asset('js/recommendation.js') }}" defer></script>
@endpush
