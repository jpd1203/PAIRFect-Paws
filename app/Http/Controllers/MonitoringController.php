<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\PostAdoptionReport;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    public function index()
    {
        $checkIns = CheckIn::with('pet')
            ->where('user_id', Auth::id())
            ->orderBy('due_date')
            ->get();

        $reports = PostAdoptionReport::with('pet')
            ->where('user_id', Auth::id())
            ->latest('report_date')
            ->get();

        return view('monitoring.index', [
            'hasActiveAdoption' => $checkIns->isNotEmpty(),
            'pet' => optional($checkIns->first())->pet,
            'schedule' => $checkIns,
            'submittedReports' => $reports,
        ]);
    }

    /**
     * Report-view modal content, fetched via AJAX.
     * Authorization: a user may only ever view their OWN reports.
     */
    public function showReport(PostAdoptionReport $report)
    {
        abort_unless($report->user_id === Auth::id(), 403);

        return view('monitoring._report-view-modal-content', ['report' => $report]);
    }
}
