@extends('layouts.app')

@section('title', 'Overdue Check-in Notice - PAIRfect Paws')

@section('content')

    <div class="sticky-header">
        <div class="heading-text">
            <h2>Overdue Check-in Notice</h2>
            <p>Your post-adoption welfare report is past its due date.</p>
        </div>
    </div>

    <div class="content-area">

        <!-- OVERDUE ALERT -->
        <div class="alert-card">

            <h3>Your {{ $checkIn->milestone_short }} Check-In is Overdue</h3>

            <p>
                Your welfare check-in for <strong>{{ $checkIn->pet?->name }}</strong> was due on
                <strong>{{ $checkIn->due_date->format('F j, Y') }}</strong>. You have not submitted your
                report yet.
            </p>

            <p>
                Two automated reminder notifications have already been sent
                to your registered email. If you continue to not respond,
                the shelter will be required to flag your case for intervention
                and may contact you directly.
            </p>

        </div>

        <!-- WHAT TO DO -->
        <div class="info-card">

            <h3>What You Need to Do</h3>

            <ul class="action-list">

                <li>
                    <span class="dot red"></span>
                    Submit your {{ $checkIn->milestone_short }} Welfare Report for {{ $checkIn->pet?->name }} as soon as possible.
                </li>

                <li>
                    <span class="dot yellow"></span>
                    Include accurate details about {{ $checkIn->pet?->name }}'s health, behavior,
                    living conditions, and any concerns.
                </li>

                <li>
                    <span class="dot green"></span>
                    Once submitted, your check-in status will be updated and
                    the shelter will review your report.
                </li>

            </ul>

            <a class="btn btn-submitOverdue" href="{{ route('flagged.submitReport') }}">
                Submit Overdue Report Now
            </a>

        </div>

        <!-- CONSEQUENCES -->
        <div class="info-card">

            <h3>What Happens If You Don't Submit</h3>

            <ul class="warning-list">

                <li>
                    <i class="fa-solid fa-flag"></i>
                    Your case will be automatically flagged as a
                    Missed Submission and escalated to the shelter administrator.
                </li>

                <li>
                    <i class="fa-solid fa-phone"></i>
                    A shelter volunteer or administrator may contact
                    you by phone or email to follow up on {{ $checkIn->pet?->name }}'s welfare.
                </li>

                <li>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Repeated missed check-ins may result in an
                    in-person home visit to verify the animal's condition.
                </li>

            </ul>

        </div>

        <!-- CONTACT -->
        <div class="contact-card">

            <p>
                If you are experiencing difficulty submitting your report
                or have concerns about {{ $checkIn->pet?->name }}'s health, please contact
                the shelter directly at
                <strong>redcubspetpatrol@gmail.com</strong>
                or call
                <strong>+63 918 985 2149</strong>.
            </p>

        </div>

    </div>

@endsection
