<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Handover;
use App\Services\HandoverDeliveryClearanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $returnTo = route('admin.handover.show', $handover).'#deliveryReadiness';
        try {
            $data = $request->validate($this->revisionRules() + [
                'recipient' => 'required|in:adopter', 'available' => 'required|boolean',
                'contact_method' => ['required', Rule::in(array_keys(HandoverDeliveryClearanceService::CONTACT_METHODS))],
                'notes' => 'nullable|required_if:contact_method,other|string|max:1000',
            ], [
                'contact_method.required' => 'Select how you directly contacted the adopter.',
                'available.required' => 'Select whether the adopter explicitly confirmed availability.',
                'notes.required_if' => 'Describe the documented direct contact when selecting Other.',
            ]);
            $service->recordReadiness($handover, $request->user(), $data);
        } catch (ValidationException $exception) {
            // Keep the standard error bag/old input and JSON 422 response, but
            // bring web submissions back to the affected section, not the page top.
            throw $exception->redirectTo($returnTo);
        }

        $message = (bool) $data['available']
            ? 'Adopter availability confirmed. A pre-dispatch in-app notice and durable email intent were created; this does not prove email delivery or record courier release.'
            : 'Adopter reported unavailable. Contact was recorded, but delivery release remains blocked until availability is confirmed for the current agreement.';

        return redirect()->to($returnTo)->with('success', $message)->with('delivery_readiness_recorded', true);
    }
}
