<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AdoptionApplicationsReportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::cases()))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (isset($validated['from'], $validated['to']) && $validated['from'] > $validated['to']) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        $query = AdoptionApplication::query()->with(['user', 'pet']);
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $validated['from'], ManilaTime::timezone())->utc());
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $validated['to'], ManilaTime::timezone())->addDay()->utc());
        }

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Application ID', 'Applicant', 'Pet', 'Compatibility Score (%)', 'Application Date (Asia/Manila)', 'Status', 'Interview Date (Asia/Manila)', 'Approval Date (Asia/Manila)']);

            foreach ($query->orderByDesc('created_at')->orderByDesc('id')->lazy(200) as $application) {
                fputcsv($output, [
                    $application->id,
                    $this->csvText(trim($application->first_name.' '.$application->last_name)),
                    $this->csvText($application->pet?->name ?? 'Unavailable pet record'),
                    $application->knn_score === null ? '' : number_format($application->knn_score, 2, '.', ''),
                    ManilaTime::format($application->created_at, 'Y-m-d H:i'),
                    $application->status_display,
                    $application->interview_date ? ManilaTime::format($application->interview_date, 'Y-m-d H:i') : '',
                    $application->status === ApplicationStatus::Approved
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

    private function csvText(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
    }
}
