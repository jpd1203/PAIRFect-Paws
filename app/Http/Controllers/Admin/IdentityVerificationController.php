<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\IdentityVerification;
use App\Services\IdentityVerificationService;
use Illuminate\Http\Request;

class IdentityVerificationController extends Controller
{
    public function show(AdoptionApplication $application)
    {
        return view('admin.application.identity-verification', [
            'application' => $application,
            'events' => $application->identityVerifications()->with('verifier')->latest('id')->get(),
        ]);
    }

    public function store(Request $request, AdoptionApplication $application, IdentityVerificationService $service)
    {
        $data = $request->validate([
            'stage' => 'required|in:interview,pickup_handover',
            'verification_method' => 'required|in:in_person,video_interview',
            'status' => 'required|in:verified,needs_review,failed',
            'discrepancy_note' => 'nullable|string|max:2000',
            ...array_fill_keys(IdentityVerification::CHECKS, 'sometimes|boolean'),
        ]);
        $service->record($application, $request->user(), $data);

        return back()->with('success', 'Staff-assisted identity verification recorded.');
    }
}
