@extends('layouts.app')

@section('title', 'Pet Recommendation - PAIRfect Paws')

@section('content')

    <div class="nonsticky-header">
        <div class="heading-text">
            <h2>{{ $isOnboarding ? 'Your Adoption Profile' : 'Pet Recommendation' }}</h2>
            <p>{{ $isOnboarding ? 'Get started with the 20-question personality assessment, or skip it for now and browse pets.' : 'Complete your personality questionnaire and household information to find compatible pets.' }}</p>
        </div>
    
        <div class="content-area-nonsticky custom-scrollbar">

            <div class="reco-banner my-3">
                @if ($returnPet)
                    Personality Assessment Required for {{ $returnPet->name }}. Complete your reusable assessment, then continue your application. Shelter staff make the final decision.
                @elseif ($isOnboarding)
                    Your account is ready. This assessment is optional today, but you must complete it before personalized recommendations or a formal adoption application. You can still browse pets if you skip.
                @else
                    Your reusable personality and household profile calculates pet compatibility. Shelter staff make the final adoption decision.
                @endif
            </div>

            @if (auth()->check() && ! auth()->user()->hasVerifiedEmail())
                <div role="alert" class="mb-5 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    You can complete your profile and view pet recommendations now. Verify your email address before you can apply to adopt a pet.
                    <a href="{{ route('verification.notice') }}" class="font-bold underline">Verify email</a>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-status-danger-text bg-status-danger-bg px-4 py-3 text-status-danger-text text-sm">
                    <strong>Please fix the following:</strong>
                    <ul class="list-disc ml-5 mt-1">
                        @foreach ($errors->getMessages() as $field => $messages)
                            @foreach ($messages as $error)
                                <li>
                                    @if (str_starts_with($field, 'bfi_responses.'))
                                        <a href="#bfi_{{ substr($field, strlen('bfi_responses.')) }}" class="underline">{{ $error }}</a>
                                    @else
                                        {{ $error }}
                                    @endif
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('recommendation.start') }}" method="POST">
                @csrf

                <div class="form-section mt-0">

                <div class="info-card shadow-card">
                    <h3>Your Adopter Profile</h3>
                    <p class="mb-4">{{ auth()->check() ? 'Your answers are saved to your account and reused for future pets.' : 'Your answers are saved for this browser session and can be added to your account when you sign up.' }} Housing, children, existing pets, and care budget determine eligibility.</p>

                        <div class="form-grid">




                            <div class="form-group">
                                <label for="housing_type">Housing Type*</label>
                                <div class="select-wrapper">
                                    <select id="housing_type" name="housing_type" required>
                                        <option value="">Select Housing Type</option>
                                        @foreach ($options::HOUSING_TYPES as $option)
                                            <option value="{{ $option }}" @selected(old('housing_type', $profile?->housing_type) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                                </div>
                            </div>


                            <div class="form-group">
                                <label for="monthly_income_range">Monthly Income Range*</label>
                                <div class="select-wrapper">
                                    <select id="monthly_income_range" name="monthly_income_range" required>
                                        <option value="">Select Income Range</option>
                                        @foreach ($options::INCOME_RANGES as $option)
                                            <option value="{{ $option }}" @selected(old('monthly_income_range', $profile?->monthly_income_range) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                                </div>
                            </div>
                        </div>
                        @include('partials.matching-household-fields')
                        @include('partials.bfi-questionnaire')
                        <div class="submit-container">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-heart"></i>
                            Start Matching
                        </button>
                    </div>
                    </div>
                </div>

            </form>

            @if ($isOnboarding)
                <form action="{{ route('recommendation.onboarding.skip') }}" method="POST" class="mt-4 text-center">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Skip for now</button>
                    <p class="mt-2 text-sm text-ink-muted">You can complete the assessment later from Pet Recommendation or when you apply.</p>
                </form>
            @endif

        </div>
    </div>

@endsection
