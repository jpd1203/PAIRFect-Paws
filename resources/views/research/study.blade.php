@extends('layouts.app')
@section('title', 'Study Information')
@section('content')
    <div class="nonsticky-header custom-scrollbar">
        <div class="main-content-header"><div class="heading-text"><h2>Study Information</h2></div></div>
        <div class="content-area">
            <section class="form-section">
                <h3>About this usability study</h3>
                <p>You are evaluating PAIRfect Paws using fictional shelter animals. Applications, staff decisions, handovers and check-ins in this study do not place a real animal or arrange an actual delivery. Participation is voluntary.</p>
                <p>Use the study team's example details. The study records your account, profile and answers, and progress through the tasks. Read the <a class="underline" href="{{ route('legal.privacy') }}">privacy notice</a> and contact the study organizer for retention details or to stop participating.</p>
                <h3>Optional document check</h3>
                <p>You can complete the whole study without uploading an ID. Use the study team's synthetic ID sample rather than a genuine government ID. With your separate permission, and only when enabled, Google Cloud Vision receives the file as a third-party processor and extracts text to compare with the name and address on your application. A text match does not prove identity.</p>
                <p>The app uses private temporary storage during processing, deletes the file afterward, and saves the outcome rather than the document or extracted text. The study team can explain the processor's policies before you decide whether to upload.</p>
                @if (! \App\Support\ResearchMode::hasConsent(auth()->user()))
                    <form method="POST" action="{{ route('study.consent') }}">
                        @csrf
                        <div class="checkbox-group">
                            <input id="study_consent" name="study_consent" type="checkbox" value="1" required>
                            <label for="study_consent">I have read this information and freely agree to participate.</label>
                        </div>
                        @error('study_consent') <p role="alert">{{ $message }}</p> @enderror
                        <button class="btn btn-primary" type="submit">Continue</button>
                    </form>
                @else
                    <a class="btn btn-secondary" href="{{ route('animal.index') }}">Back to Browse Pets</a>
                @endif
            </section>
        </div>
    </div>
@endsection
