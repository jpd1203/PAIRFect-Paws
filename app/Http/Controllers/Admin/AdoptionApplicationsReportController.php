<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Support\ApplicationListFilters;
use App\Support\CsvSafeCell;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

final class AdoptionApplicationsReportController extends Controller
{
    public function export(Request $request)
    {
        $query = ApplicationListFilters::apply(
            AdoptionApplication::query()->with(['user', 'pet']),
            ApplicationListFilters::validate($request)
        );

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Application ID', 'Applicant', 'Pet', 'Compatibility Score (%)', 'Application Date (Asia/Manila)', 'Status', 'Interview Date (Asia/Manila)', 'Approval Date (Asia/Manila)']);

            foreach ($query->orderByDesc('created_at')->orderByDesc('id')->lazy(200) as $application) {
                fputcsv($output, [
                    $application->id,
                    CsvSafeCell::text(trim($application->first_name.' '.$application->last_name)),
                    CsvSafeCell::text($application->pet?->name ?? 'Unavailable pet record'),
                    $application->knn_score === null ? '' : number_format($application->knn_score, 2, '.', ''),
                    ManilaTime::format($application->created_at, 'Y-m-d H:i'),
                    $application->status_display,
                    $application->interview_date ? ManilaTime::format($application->interview_date, 'Y-m-d H:i') : '',
                    $application->status === \App\Enums\ApplicationStatus::Approved
                        ? ManilaTime::format($application->adopted_at ?? $application->queue_closed_at ?? $application->updated_at, 'Y-m-d H:i')
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
