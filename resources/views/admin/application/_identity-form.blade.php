@php
    $identityStage = $identityStage ?? 'interview';
    $identityApplication = $identityApplication ?? $application;
@endphp
<section class="rounded-card border border-[#e2ddd7] bg-white p-5 shadow-card">
    <h3 class="text-base font-bold">{{ $identityStage === 'pickup_handover' ? 'Final Pickup Identity Confirmation' : 'Interview Identity Verification' }}</h3>
    <p class="text-sm text-text-muted">Staff-assisted identity assurance only. OCR does not verify identity; no biometrics or facial recognition are used.</p>
    <form method="POST" action="{{ route('admin.applications.identity.store', $identityApplication) }}" class="space-y-3">
        @csrf
        <input type="hidden" name="stage" value="{{ $identityStage }}">
        <label class="form-label" for="identity-method-{{ $identityStage }}">Verification method</label>
        <select id="identity-method-{{ $identityStage }}" class="form-select" name="verification_method" required>
            <option value="in_person">In Person</option>
            @if($identityStage === 'interview')<option value="video_interview">Video Interview</option>@endif
        </select>
        @foreach([
            'government_id_presented' => 'Government ID presented',
            'applicant_matches_id_photo' => 'Applicant reasonably corresponds to the ID photograph',
            'name_matches_application' => 'Name corresponds to the approved application',
            'submitted_document_consistent' => 'Presented ID is consistent with the submitted document',
            'no_material_discrepancy' => 'No material identity discrepancy observed',
        ] as $field => $label)
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="{{ $field }}" value="1" class="mt-1"> {{ $label }}</label>
        @endforeach
        <label class="form-label" for="identity-result-{{ $identityStage }}">Verification result</label>
        <select id="identity-result-{{ $identityStage }}" name="status" class="form-select" required>
            <option value="verified">Verified</option><option value="needs_review">Needs Review</option><option value="failed">Failed</option>
        </select>
        <label class="form-label" for="identity-notes-{{ $identityStage }}">Discrepancy / Staff Notes</label>
        <textarea id="identity-notes-{{ $identityStage }}" class="form-control" name="discrepancy_note" maxlength="2000" rows="2"></textarea>
        <button class="btn btn-primary" type="submit">Save Identity Verification</button>
    </form>
</section>
