<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Support\ApplicationListFilters;
use App\Support\CsvSafeCell;
use App\Support\ManilaTime;
use App\Support\ResearchAdminRecords;
use App\Support\ResearchMode;
use Illuminate\Http\Request;

final class AdoptionApplicationsReportController extends Controller
{
    public function export(Request $request, ResearchAdminRecords $research)
    {
        $workflowReadOnly = ResearchMode::ready();
        $query = ApplicationListFilters::apply(
            $workflowReadOnly ? $research->applications() : AdoptionApplication::query()->with(['user', 'pet']),
            ApplicationListFilters::validate($request)
        );

        return response()->streamDownload(function () use ($query, $workflowReadOnly): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Application ID', 'Applicant', 'Pet', 'Compatibility Score (%)', 'Application Date (Asia/Manila)', 'Status', 'Interview Date (Asia/Manila)', 'Approval Date (Asia/Manila)']);

            foreach ($query->orderByDesc('created_at')->orderByDesc('id')->lazy(200) as $application) {
                $approvalAt = $workflowReadOnly ? $application->approved_at : ($application->adopted_at ?? $application->queue_closed_at ?? $application->updated_at);
                fputcsv($output, [
                    $application->id,
                    CsvSafeCell::text(trim($application->first_name.' '.$application->last_name)),
                    CsvSafeCell::text($application->pet?->name ?? 'Unavailable pet record'),
                    $application->knn_score === null ? '' : number_format($application->knn_score, 2, '.', ''),
                    ManilaTime::format($application->created_at, 'Y-m-d H:i'),
                    $application->status_display,
                    $application->interview_date ? ManilaTime::format($application->interview_date, 'Y-m-d H:i') : '',
                    $application->status === ApplicationStatus::Approved && $approvalAt
                        ? ManilaTime::format($approvalAt, 'Y-m-d H:i')
                        : '',
                ]);
            }

            fclose($output);
        }, 'adoption-applications-'.ManilaTime::now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
