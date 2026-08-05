@extends('layouts.app')

@section('title', 'Submit Post-Adoption Report - PAIRfect Paws')

@section('content')
    <div class="sticky-header">
        <div class="heading-text">
            <h2>Submit Post-Adoption Report</h2>
            <p>Complete your scheduled welfare check-in.</p>
        </div>
    </div>

    <div class="content-area">
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>No report due right now</h3>
            <p>You're all caught up. Check "My Check-ins" to see your next scheduled milestone.</p>
        </div>
    </div>
@endsection
