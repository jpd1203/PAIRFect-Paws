@extends('layouts.app')

@section('title', 'Flagged Report Notice - PAIRfect Paws')

@section('content')
    <div class="sticky-header">
        <div class="heading-text">
            <h2>Flagged Report Notice</h2>
            <p>One or more of your submitted welfare reports have been flagged for shelter review.</p>
        </div>
    </div>

    <div class="content-area">
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>No flagged reports</h3>
            <p>None of your welfare reports are currently flagged. Keep up the great care!</p>
        </div>
    </div>
@endsection
