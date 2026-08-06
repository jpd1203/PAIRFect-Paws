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
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>Nothing overdue</h3>
            <p>You're up to date on all your scheduled check-ins. Nice work!</p>
        </div>
    </div>
@endsection
