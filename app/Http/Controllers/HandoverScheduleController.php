<?php

namespace App\Http\Controllers;

use App\Models\Handover;
use App\Services\HandoverScheduleService;
use Illuminate\Http\Request;

class HandoverScheduleController extends Controller
{
    public function propose(Request $request, Handover $handover, HandoverScheduleService $service)
    {
        $data = $request->validate([
            'method' => 'required|in:pickup,delivery', 'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i',
            'schedule_version' => 'required|integer|min:0',
        ]);
        $service->propose($handover, $request->user(), $data);

        return back()->with('success', 'Handover schedule proposed. The adopter must confirm it before release.');
    }

    public function confirm(Request $request, Handover $handover, HandoverScheduleService $service)
    {
        abort_unless((int) $handover->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['schedule_version' => 'required|integer|min:0']);
        $service->confirm($handover, $request->user(), (int) $data['schedule_version']);

        return back()->with('success', 'Handover schedule confirmed.');
    }

    public function requestReschedule(Request $request, Handover $handover, HandoverScheduleService $service)
    {
        abort_unless((int) $handover->user_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'schedule_version' => 'required|integer|min:0', 'reason' => 'nullable|string|max:1000',
            'options' => 'required|array|min:1|max:3', 'options.*' => 'required|array:date,start_time,end_time',
            'options.*.date' => 'nullable|date_format:Y-m-d',
            'options.*.start_time' => 'nullable|date_format:H:i', 'options.*.end_time' => 'nullable|date_format:H:i',
        ]);
        $service->requestReschedule($handover, $request->user(), $data);

        return back()->with('success', 'Reschedule request sent to shelter staff.');
    }

    public function review(Request $request, Handover $handover, HandoverScheduleService $service)
    {
        $data = $request->validate([
            'schedule_version' => 'required|integer|min:0', 'decision' => 'required|in:approved,declined',
            'option_index' => 'nullable|required_if:decision,approved|integer|min:0|max:2',
        ]);
        $service->review($handover, $request->user(), $data);

        return back()->with('success', 'Handover reschedule request reviewed.');
    }
}
