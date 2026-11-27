@extends('admin.layouts.app')

@section('title', 'Document Verification | PAIRfect Paws')

@section('content')
    <div class="main-content-header">
        <div class="heading-text">
            <h2>Document Verification</h2>
            <p>Application #{{ $placement->id }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('admin.applications.index') }}">Back to Applications</a>
    </div>

    <div class="records-container mt-5 p-6">
        <section class="review-section">
            <h6>Document Review Status</h6>
            <div class="review-row"><span>Status</span><strong>{{ match ($placement->document_verification_status?->value) {
                'NeedsResubmission' => 'Needs Resubmission',
                'ManualReview' => 'Manual Review',
                'LegacyReview' => 'Legacy Review',
                default => $placement->document_verification_status?->value ?? 'Pending',
            } }}</strong></div>
            <div class="review-row"><span>Document Type</span><span>{{ $placement->document_type ?? 'Not available' }}</span></div>
            <div class="review-row"><span>Match Score</span><span>{{ $placement->document_match_score !== null ? round($placement->document_match_score * 100) . '%' : 'Not available' }}</span></div>
            @if ($placement->document_verification_reasons)
                <ul class="list-disc ml-5 mt-2">
                    @foreach ($placement->document_verification_reasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="review-section">
            <h6>Extracted OCR Text</h6>
            <p class="text-sm mb-2">Restricted to administrators. OCR reads text; it does not prove identity or document authenticity. Use only synthetic ID data in screenshots.</p>
            @if (filled($text))
                <pre class="whitespace-pre-wrap break-words bg-neutral-light rounded-lg p-4 text-sm">{{ $text }}</pre>
            @elseif (! $retentionConsent)
                <p>No OCR text was retained for this upload.</p>
            @else
                <p>No extracted text is available. The document may have contained no readable text, or automatic checking may not have completed.</p>
            @endif
        </section>
    </div>
@endsection
