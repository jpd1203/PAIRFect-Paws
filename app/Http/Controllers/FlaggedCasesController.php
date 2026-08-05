<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\CheckIn;
use App\Models\PostAdoptionReport;
use App\Support\ReportOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class FlaggedCasesController extends Controller
{
    /** GET /post-adoption/submit-report */
    public function submitReport()
    {
        $checkIn = CheckIn::with('pet')
            ->where('user_id', Auth::id())
            ->whereIn('status', [CheckIn::STATUS_PENDING, CheckIn::STATUS_OVERDUE])
            ->orderBy('due_date')
            ->first();

        if (!$checkIn) {
            return view('flagged-cases.no-report-due');
        }

        return view('flagged-cases.submit-report', [
            'checkIn' => $checkIn,
            'pet' => $checkIn->pet,
            'adopter' => Auth::user(),
            'options' => ReportOptions::class,
        ]);
    }

    /**
     * Validates the report and returns the confirm-modal partial via AJAX,
     * WITHOUT persisting anything yet (matches the .cshtml preview -> confirm flow).
     * Validated data is round-tripped as hidden fields, not trusted blindly on confirm.
     */
    public function previewReport(StoreReportRequest $request)
    {
        $data = $request->validated();
        $checkIn = CheckIn::with('pet')->where('user_id', Auth::id())->findOrFail($data['check_in_id']);

        $report = new PostAdoptionReport([
            ...$data,
            'user_id' => Auth::id(),
            'report_date' => now(),
        ]);
        $report->setRelation('pet', $checkIn->pet);

        return view('flagged-cases._confirm-report-modal-content', ['report' => $report]);
    }

    /** Persists the report after the user confirms the preview. Re-validates server-side. */
    public function confirmSubmit(StoreReportRequest $request)
    {
        $data = $request->validated();
        $checkIn = CheckIn::where('user_id', Auth::id())->findOrFail($data['check_in_id']);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('report-photos', 'public')
            : null;

        PostAdoptionReport::create([
            'check_in_id' => $checkIn->id,
            'user_id' => Auth::id(),
            'pet_id' => $checkIn->pet_id,
            'milestone' => $checkIn->milestone,
            'health_status' => $data['health_status'],
            'eating_and_drinking' => $data['eating_and_drinking'],
            'behavior' => $data['behavior'],
            'living_conditions' => $data['living_conditions'],
            'vet_visit' => $data['vet_visit'],
            'concerns' => $data['concerns'] ?? null,
            'photo_path' => $photoPath,
            'report_date' => now(),
        ]);

        $checkIn->update(['status' => CheckIn::STATUS_SUBMITTED]);

        return redirect()
            ->route('monitoring.index')
            ->with('toast', ['type' => 'success', 'message' => 'Report submitted. Thank you for the update!']);
    }

    public function overdueNotice()
    {
        $checkIn = CheckIn::with('pet')
            ->where('user_id', Auth::id())
            ->where('status', CheckIn::STATUS_OVERDUE)
            ->orderBy('due_date')
            ->first();

        if (!$checkIn) {
            return view('flagged-cases.no-overdue-notice');
        }

        return view('flagged-cases.overdue-notice', ['checkIn' => $checkIn]);
    }

    public function flaggedNotice()
    {
        $report = PostAdoptionReport::with('pet')
            ->where('user_id', Auth::id())
            ->where('flagged', true)
            ->latest('report_date')
            ->first();

        if (!$report) {
            return view('flagged-cases.no-flagged-notice');
        }

        return view('flagged-cases.flagged-notice', ['report' => $report]);
    }
}
