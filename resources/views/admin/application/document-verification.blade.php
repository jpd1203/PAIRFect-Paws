@extends('admin.layouts.app')

@section('title', 'Document Verification - PAIRfect Paws Admin')

@section('content')
    @php
        $ocrText = $application->ocr_extracted_text;
        $documentVerificationValue = $application->document_verification_status?->value;
        $followUpLimitReached = $application->document_reupload_count >= config('document_verification.maximum_adopter_reuploads', 1);
        $canRetryOcr = (in_array($documentVerificationValue, ['ManualReview', 'LegacyReview'], true)
                || ($documentVerificationValue === 'NeedsResubmission' && $followUpLimitReached))
            && in_array($application->status->value, ['Pending', 'DocumentFlagged'], true)
            && !$application->is_primary_candidate;
    @endphp

    <div class="main-content-header">
        <div class="heading-text">
            <h2>Document Verification</h2>
            <p>Application #{{ $application->id }} — {{ $application->first_name }} {{ $application->last_name }}</p>
        </div>
        @include('partials.notification-bell')
    </div>

    @if(session('success'))
        <div class="mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="records-container mt-5 p-6">
        <div class="review-section">
            <h6>OCR Result</h6>
            <div class="review-row"><span>Status</span><strong>{{ match($application->document_verification_status?->value) {
                'NeedsResubmission' => 'Needs Resubmission',
                'ManualReview' => 'Manual Review',
                'LegacyReview' => 'Legacy Review',
                default => $application->document_verification_status?->value ?? 'Pending'
            } }}</strong></div>
            <div class="review-row"><span>Document Type</span><span>{{ $application->document_type ?? 'Not identified' }}</span></div>
            <div class="review-row"><span>Composite Similarity</span><span>{{ $application->document_match_score !== null ? round($application->document_match_score * 100) . '%' : 'Not available' }}</span></div>
            <div class="review-row"><span>Uploaded</span><span>{{ $application->document_uploaded_at ? \App\Support\ManilaTime::format($application->document_uploaded_at, 'F j, Y g:i A') : '—' }}</span></div>
            <p class="text-xs text-[#777] mt-2">The name, address, and document type must each pass independently. The composite percentage alone cannot approve a document.</p>
        </div>

        @if($application->document_verification_reasons)
            <div class="review-section">
                <h6>Verification Notes</h6>
                <ul class="list-disc ml-5 text-sm text-[#555]">
                    @foreach($application->document_verification_reasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="review-section">
            <h6>Extracted OCR Text</h6>
            <p class="text-xs text-[#777] mb-2">Sensitive data — visible only to authorized shelter personnel.</p>
            @if(filled($ocrText))
                <pre class="whitespace-pre-wrap break-words bg-neutral-light rounded-lg p-4 text-sm">{{ $ocrText }}</pre>
            @elseif($documentVerificationValue === 'ManualReview')
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <strong>OCR did not complete.</strong> Google did not return an extraction result. Review the provider note above, correct the configuration issue, and retry OCR.
                </div>
            @elseif($documentVerificationValue === 'LegacyReview')
                <div class="rounded-lg border border-[#ddd] bg-neutral-light p-4 text-sm">
                    This legacy document has not been processed by OCR.
                </div>
            @else
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                    Google Vision completed without returning readable text. Review the document image and request a clearer replacement if needed.
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-3 mt-5">
            <a href="{{ route('admin.applications.document', $application) }}" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-eye"></i>View Private Document</a>
            @if($canRetryOcr)
                <form method="POST" action="{{ route('admin.applications.document-ocr-retry', $application) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate-right"></i>Retry Google OCR</button>
                </form>
            @endif
            <a href="{{ route('admin.applications.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i>Back to Applications</a>
        </div>

        @if(!$application->is_primary_candidate && !in_array($application->status->value, ['Approved', 'Rejected', 'Withdrawn', 'NoShow', 'Closed'], true))
            <form method="POST" action="{{ route('admin.applications.document-decision', $application) }}" class="mt-6 border-t border-[#ddd] pt-5">
                @csrf
                <div class="form-group">
                    <label for="document_reason" class="font-semibold">Manual review reason*</label>
                    <textarea id="document_reason" name="document_reason" rows="3" required minlength="10"
                        class="border border-gray-300 rounded-md p-3 w-full"></textarea>
                </div>
                <div class="flex flex-wrap gap-3 mt-3">
                    <button class="btn btn-primary" name="document_decision" value="Verified"><i class="fa-solid fa-circle-check"></i>Mark Verified</button>
                    @if(!$followUpLimitReached)
                        <button class="btn btn-danger" name="document_decision" value="NeedsResubmission"><i class="fa-solid fa-comment-dots"></i>Request Follow-up Document</button>
                    @else
                        <span class="text-sm text-amber-800 self-center">The adopter’s one follow-up upload has already been used.</span>
                    @endif
                </div>
            </form>
        @endif
    </div>
@endsection
