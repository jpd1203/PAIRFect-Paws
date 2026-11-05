<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Handover;
use App\Models\HandoverTransportIncident;
use App\Services\HandoverTransportIncidentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class HandoverTransportIncidentController extends Controller
{
    public function store(Request $request, Handover $handover, HandoverTransportIncidentService $service)
    {
        $service->staff($request->user());
        $data = $request->validate([
            'release_attempt' => 'required|integer|min:0',
            'category' => ['required', Rule::in(array_keys(HandoverTransportIncident::CATEGORIES))],
            'description' => ['required', 'string', 'max:3000', 'regex:/\S/'],
            'evidence' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $evidence = $this->evidence($request);
        try {
            $case = $service->open($handover, $request->user(), $data, $evidence);
            if ($evidence && $case->evidence_path !== $evidence['path']) {
                Storage::disk('local')->delete($evidence['path']);
            }
        } catch (Throwable $exception) {
            if ($evidence) {
                Storage::disk('local')->delete($evidence['path']);
            }
            throw $exception;
        }

        return back()->with('success', "Transport investigation #{$case->id} is recorded. A report is not a verified finding; an administrator must assign follow-up responsibility.");
    }

    public function update(Request $request, Handover $handover, HandoverTransportIncident $incident, HandoverTransportIncidentService $service)
    {
        abort_unless((int) $incident->handover_id === (int) $handover->id, 404);
        $staff = $service->staff($request->user());
        abort_unless($service->canManage($staff, $incident), 403);
        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'status' => ['required', Rule::in(array_keys(HandoverTransportIncident::STATUSES))],
            'assigned_to_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'next_follow_up_at' => 'nullable|date_format:Y-m-d\TH:i',
            'findings' => ['required', 'string', 'max:3000', 'regex:/\S/'],
            'resolution_outcome' => ['nullable', 'required_if:status,resolved', Rule::in(array_keys(HandoverTransportIncident::OUTCOMES))],
            'resolution_notes' => ['nullable', 'required_if:status,resolved', 'string', 'max:3000', 'regex:/\S/'],
            'evidence' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $evidence = $this->evidence($request);
        try {
            $service->update($incident, $request->user(), $data, $evidence);
        } catch (Throwable $exception) {
            if ($evidence) {
                Storage::disk('local')->delete($evidence['path']);
            }
            throw $exception;
        }

        return back()->with('success', 'Transport investigation updated. Case resolution does not change receipt, adoption, pet availability, or monitoring.');
    }

    public function evidenceView(Request $request, Handover $handover, HandoverTransportIncident $incident, HandoverTransportIncidentService $service)
    {
        $service->staff($request->user());
        abort_unless((int) $incident->handover_id === (int) $handover->id, 404);
        $release = $request->query('kind') === 'release';
        $path = $release ? ($incident->release_context['release_proof_path'] ?? null) : $incident->evidence_path;
        $prefix = $release ? 'handover-proofs' : 'handover-transport-evidence';
        abort_unless(is_string($path) && preg_match('~^'.$prefix.'/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$~i', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $release ? 'release-evidence' : 'transport-evidence', [
            'Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function evidence(Request $request): ?array
    {
        if (! $request->hasFile('evidence')) {
            return null;
        }
        $file = $request->file('evidence');
        $hash = hash_file('sha256', $file->getRealPath());
        if ($hash === false) {
            throw new RuntimeException('Supporting evidence could not be verified.');
        }
        $path = $file->store('handover-transport-evidence', 'local');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Supporting evidence could not be stored.');
        }

        return ['path' => $path, 'hash' => $hash];
    }
}
