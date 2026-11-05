<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Handover;
use App\Services\HandoverDeliveryClearanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HandoverDeliveryController extends Controller
{
    private function revisionRules(): array
    {
        return [
            'schedule_version' => 'required|integer|min:0',
            'clearance_version' => 'required|integer|min:0',
            'destination_fingerprint' => 'required|string|size:64|regex:/\A[a-f0-9]{64}\z/',
        ];
    }

    public function verifyAddress(Request $request, Handover $handover, HandoverDeliveryClearanceService $service)
    {
        $data = $request->validate($this->revisionRules() + ['status' => 'required|in:verified,needs_review']);
        $service->verifyAddress($handover, $request->user(), $data);

        return back()->with('success', 'Delivery destination review recorded. Explicit recipient readiness is still required before delivery release.');
    }

    public function readiness(Request $request, Handover $handover, HandoverDeliveryClearanceService $service)
    {
        $data = $request->validate($this->revisionRules() + [
            'recipient' => 'required|in:adopter', 'available' => 'required|boolean',
            'contact_method' => ['required', Rule::in(array_keys(HandoverDeliveryClearanceService::CONTACT_METHODS))],
            'notes' => 'nullable|required_if:contact_method,other|string|max:1000',
        ]);
        $service->recordReadiness($handover, $request->user(), $data);

        return back()->with('success', 'Recipient contact recorded. Availability confirmations create a pre-dispatch in-app notice and durable email intent; this does not prove email delivery.');
    }
}
