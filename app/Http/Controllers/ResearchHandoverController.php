<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\ResearchPlacement;
use App\Services\AuditLogService;
use App\Services\ResearchPrivateMedia;
use App\Support\ManilaTime;
use App\Support\ResearchAdminRecords;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ResearchHandoverController extends Controller
{
    public function show(Request $request, ResearchPlacement $placement, ResearchAdminRecords $records)
    {
        $this->authorizePlacement($request, $placement);

        return view('handover.status', ['record' => $records->handover($placement), 'researchHandover' => true]);
    }

    public function receipt(Request $request, ResearchPlacement $placement, ResearchAdminRecords $records)
    {
        $this->authorizePlacement($request, $placement);

        return view('handover.confirm', ['record' => $records->handover($placement), 'researchHandover' => true]);
    }

    public function selectMethod(Request $request, ResearchPlacement $placement)
    {
        $data = $request->validate(['method' => 'required|in:pickup,delivery', 'schedule_version' => 'required|integer|min:0']);
        $this->change($request, $placement, (int) $data['schedule_version'], function ($row, $schedule) use ($data) {
            abort_unless($schedule === [] && ! $row->handover_receipt, 409);
            $start = ManilaTime::now()->startOfMinute()->addHour();
            $this->saveProposal($row, $schedule, $data['method'], $start, $start->addHour());
        });

        return to_route('research.handover', $placement)->with('success', 'Handover method saved. Confirm the proposed schedule to proceed.');
    }

    public function confirmSchedule(Request $request, ResearchPlacement $placement)
    {
        $data = $request->validate(['schedule_version' => 'required|integer|min:0']);
        $this->change($request, $placement, (int) $data['schedule_version'], function ($row, $schedule) {
            abort_unless(($schedule['status'] ?? null) === 'proposed' && ! ($row->handover_receipt['outcome'] ?? null), 409);
            $now = now();
            $schedule['confirmed_at'] = $now->toISOString();
            $schedule['status'] = 'confirmed';
            $schedule['version']++;
            $row->update(['handover_schedule' => $schedule, 'released_at' => $now]);
            AuditLogService::log($row->user_id, 'Handover Schedule Confirmed', 'ResearchPlacement', $row->id);
        });

        return to_route('research.handover', $placement)->with('success', 'Handover schedule confirmed.');
    }

    public function reschedule(Request $request, ResearchPlacement $placement)
    {
        $data = $request->validate([
            'schedule_version' => 'required|integer|min:0', 'reason' => 'nullable|string|max:1000',
            'options' => 'required|array|min:1|max:3', 'options.*' => 'required|array:date,start_time,end_time',
            'options.*.date' => 'nullable|date_format:Y-m-d',
            'options.*.start_time' => 'nullable|date_format:H:i', 'options.*.end_time' => 'nullable|date_format:H:i',
        ]);
        $windows = [];
        foreach ($data['options'] as $option) {
            if (! array_filter($option)) {
                continue;
            }
            if (count(array_filter($option)) !== 3) {
                throw ValidationException::withMessages(['options' => 'Enter a date, start time, and end time for each proposed window.']);
            }
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $option['date'].' '.$option['start_time'], ManilaTime::timezone());
            $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $option['date'].' '.$option['end_time'], ManilaTime::timezone());
            if ($start->lt(ManilaTime::now()->startOfDay()) || $end->lte($start)) {
                throw ValidationException::withMessages(['options' => 'Choose today or a future date and an end time after the start.']);
            }
            $windows[] = [$start, $end];
        }
        if ($windows === []) {
            throw ValidationException::withMessages(['options' => 'Enter at least one alternative handover window.']);
        }
        $this->change($request, $placement, (int) $data['schedule_version'], function ($row, $schedule) use ($data, $windows) {
            abort_unless(in_array($schedule['status'] ?? null, ['proposed', 'confirmed'], true) && ! ($row->handover_receipt['outcome'] ?? null), 409);
            $schedule['reschedule'] = [
                'requested_at' => now()->toISOString(), 'reason' => $data['reason'] ?? null,
                'options' => array_map(fn ($window) => array_map(fn ($time) => $time->toISOString(), $window), $windows),
            ];
            $this->saveProposal($row, $schedule, $schedule['method'], ...$windows[0]);
            AuditLogService::log($row->user_id, 'Handover Reschedule Requested', 'ResearchPlacement', $row->id);
        });

        return to_route('research.handover', $placement)->with('success', 'A new window was proposed from your request. Confirm the new schedule to proceed.');
    }

    public function receive(Request $request, ResearchPlacement $placement, ResearchPrivateMedia $media)
    {
        $this->authorizePlacement($request, $placement);
        $data = $request->validate([
            'outcome' => 'required|in:received,not_received', 'note' => 'nullable|string|max:1000',
            'schedule_version' => 'required|integer|min:0',
            'receipt_proof' => 'nullable|prohibited_unless:outcome,received|file|mimetypes:image/jpeg,image/png,image/webp|max:5120',
        ]);
        $photo = $data['receipt_proof'] ?? null;
        $path = $photo ? $media->store($photo, 'receipts', $placement->id) : null;
        try {
            $this->change($request, $placement, (int) $data['schedule_version'], function ($row, $schedule) use ($data, $photo, $path) {
                abort_unless(($schedule['status'] ?? null) === 'confirmed' && $row->released_at && ! ($row->handover_receipt['outcome'] ?? null), 409, 'Confirm the current handover schedule before recording receipt.');
                $now = now();
                $receipt = ($row->handover_receipt ?? []) + ['previous_not_received' => null];
                $receipt = array_replace($receipt, [
                    'outcome' => $data['outcome'], 'at' => $now->toISOString(), 'note' => $data['note'] ?? null,
                    'path' => $path, 'mime' => $photo?->getMimeType(),
                ]);
                if ($data['outcome'] === 'not_received') {
                    $receipt['previous_not_received'] = ['at' => $receipt['at'], 'note' => $receipt['note']];
                }
                $row->forceFill(['handover_receipt' => $receipt, 'received_at' => $data['outcome'] === 'received' ? $now : null])->save();
                AuditLogService::log($row->user_id, $data['outcome'] === 'received' ? 'Handover Receipt Confirmed' : 'Handover Reported Not Received', 'ResearchPlacement', $row->id);
            });
        } catch (Throwable $exception) {
            $media->forget($path);
            throw $exception;
        }

        return to_route('research.receipt', $placement);
    }

    public function retry(Request $request, ResearchPlacement $placement)
    {
        $data = $request->validate(['schedule_version' => 'required|integer|min:0']);
        $this->change($request, $placement, (int) $data['schedule_version'], function ($row, $schedule) {
            abort_unless(($row->handover_receipt['outcome'] ?? null) === 'not_received', 409);
            $start = ManilaTime::now()->startOfMinute()->addHour();
            $row->update(['handover_receipt' => ['previous_not_received' => $row->handover_receipt['previous_not_received']]]);
            $this->saveProposal($row, $schedule, $schedule['method'], $start, $start->addHour());
            AuditLogService::log($row->user_id, 'New Handover Attempt Started', 'ResearchPlacement', $row->id);
        });

        return to_route('research.handover', $placement)->with('success', 'Confirm the new schedule to continue.');
    }

    private function saveProposal(ResearchPlacement $row, array $schedule, string $method, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $row->update(['handover_schedule' => array_replace($schedule, [
            'version' => ($schedule['version'] ?? 0) + 1, 'method' => $method, 'status' => 'proposed',
            'proposed_at' => now()->toISOString(), 'start_at' => $start->toISOString(),
            'end_at' => $end->toISOString(), 'confirmed_at' => null,
        ])]);
    }

    private function change(Request $request, ResearchPlacement $placement, int $version, Closure $transition): void
    {
        $this->authorizePlacement($request, $placement);
        DB::transaction(function () use ($request, $placement, $version, $transition): void {
            $locked = ResearchPlacement::lockForUpdate()->findOrFail($placement->id);
            $this->authorizePlacement($request, $locked);
            $schedule = $locked->handover_schedule ?? [];
            abort_if($locked->received_at || ($schedule['version'] ?? 0) !== $version, 409, 'This handover changed. Refresh and try again.');
            $transition($locked, $schedule);
        }, 3);
    }

    private function authorizePlacement(Request $request, ResearchPlacement $placement): void
    {
        abort_unless((int) $placement->user_id === (int) $request->user()->id && $placement->status === ApplicationStatus::Approved, 404);
    }
}
