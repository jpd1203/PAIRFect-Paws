<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Handover;
use App\Models\User;
use App\Services\HandoverNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HandoverController extends Controller
{
    public function __construct(private HandoverNotificationService $notifications) {}

    public function index(Request $request)
    {
        $query = Handover::with(['pet', 'application', 'user'])->latest('updated_at');

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('adopter_name', 'like', "%{$search}%")
                  ->orWhere('courier', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhereHas('pet', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('species', 'like', "%{$search}%"));
            });
        }

        $tab = $request->input('tab', 'all');
        if ($tab === 'needs_handover') {
            $query->whereNull('released_at');
        } elseif ($tab === 'awaiting') {
            $query->whereNotNull('released_at')->whereNull('adopter_outcome');
        } elseif ($tab === 'failed') {
            $query->where('adopter_outcome', 'not_received');
        } elseif ($tab === 'monitoring') {
            $query->where('adopter_outcome', 'received');
        }

        $records = $query->get();

        // Calculate queue metrics across all records
        $all = Handover::all();
        $stats = [
            'needs_handover' => $all->filter(fn ($h) => empty($h->released_at))->count(),
            'awaiting' => $all->filter(fn ($h) => !empty($h->released_at) && empty($h->adopter_outcome))->count(),
            'reported_failed' => $all->filter(fn ($h) => $h->adopter_outcome === 'not_received')->count(),
            'monitoring_active' => $all->filter(fn ($h) => $h->adopter_outcome === 'received')->count(),
            'open_count' => $all->filter(fn ($h) => $h->adopter_outcome !== 'received')->count(),
            'total_count' => $all->count(),
        ];

        return view('admin.handover.index', [
            'records' => $records,
            'stats' => $stats,
            'currentTab' => $tab,
            'search' => $search,
        ]);
    }

    public function show(Handover $handover)
    {
        $handover->load(['pet', 'application', 'user', 'notifications']);

        $volunteers = User::whereIn('role', ['Administrator', 'Volunteer'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('admin.handover.show', [
            'record' => $handover,
            'volunteers' => $volunteers,
            'currentStaffId' => Auth::id(),
        ]);
    }

    public function markReleased(Request $request, Handover $handover)
    {
        $deliveryProviders = config('handover.delivery_providers');
        if (is_string($request->input('tracking_url'))) {
            $request->merge(['tracking_url' => trim($request->input('tracking_url'))]);
        }

        $validated = $request->validate([
            'release_method' => 'required|in:pickup,delivery',
            'release_date' => 'required|date',
            'release_time' => 'required|string|max:20',
            'staff_id' => 'required|integer|exists:users,id',
            'courier_provider' => ['exclude_unless:release_method,delivery', 'required', 'string', Rule::in(array_keys($deliveryProviders))],
            'tracking_number' => ['exclude_unless:release_method,delivery', 'required', 'string', 'max:100'],
            'tracking_url' => ['exclude_unless:release_method,delivery', 'required', 'string', 'max:2048', 'url:https'],
            'proof' => 'nullable|image|max:5120',
        ], [
            'courier_provider.required' => 'Select an approved pet transport provider.',
            'courier_provider.in' => 'Select one of the approved pet transport providers.',
            'tracking_url.required' => 'Paste the live tracking/share link provided by the courier.',
            'tracking_url.url' => 'The live tracking link must be a valid HTTPS URL.',
            'tracking_url.max' => 'The live tracking link must not exceed 2048 characters.',
        ]);

        $staffMember = User::query()
            ->whereKey($validated['staff_id'])
            ->where('is_active', true)
            ->whereIn('role', [Role::Administrator->value, Role::Volunteer->value])
            ->first();

        if (! $staffMember) {
            throw ValidationException::withMessages([
                'staff_id' => 'Please select an active staff member or volunteer.',
            ]);
        }

        $data = [
            'release_method' => $validated['release_method'],
            'release_date' => $validated['release_date'],
            'release_time' => $validated['release_time'],
            'staff_name' => $staffMember->full_name,
            'courier' => $validated['release_method'] === 'delivery' ? $deliveryProviders[$validated['courier_provider']]['label'] : null,
            'tracking_number' => $validated['release_method'] === 'delivery' ? ($validated['tracking_number'] ?? null) : null,
            'tracking_url' => $validated['release_method'] === 'delivery' ? $validated['tracking_url'] : null,
            'released_at' => now(),
            'adopter_outcome' => null,
        ];

        DB::transaction(function () use ($request, $handover, $data): void {
            $application = \App\Models\AdoptionApplication::query()->lockForUpdate()->findOrFail($handover->application_id);
            $handover = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            if ($handover->released_at !== null || $handover->adopter_outcome !== null || $handover->received_at !== null) {
                throw ValidationException::withMessages(['release_method' => 'This handover has already been released or confirmed. Reopen an unconfirmed handover before recording another release.']);
            }

            if ($application->status !== \App\Enums\ApplicationStatus::Approved) {
                throw ValidationException::withMessages(['release_method' => 'Only an approved application can be released.']);
            }
            if ($handover->schedule_status !== 'confirmed' || ! $handover->scheduled_start_at || ! $handover->scheduled_end_at || ! $handover->schedule_confirmed_at || (int) $handover->schedule_confirmed_by_user_id !== (int) $handover->user_id) {
                throw ValidationException::withMessages(['schedule' => 'The handover schedule must be confirmed before the pet can be released.']);
            }
            if ($handover->scheduled_method !== $data['release_method']) {
                throw ValidationException::withMessages(['release_method' => 'The release method must match the confirmed schedule. Propose and confirm a new schedule to change it.']);
            }
            $identity = app(\App\Services\IdentityVerificationService::class);
            $identity->requireInterview($application);
            if ($data['release_method'] === 'pickup' && ! $identity->isVerified($application, 'pickup_handover', $handover->reopen_count)) {
                throw ValidationException::withMessages(['identity' => 'Final pickup identity verification must be completed before release.']);
            }

            if ($request->hasFile('proof')) {
                $path = $request->file('proof')->store('proofs', 'public');
                $data['proof_name'] = $request->file('proof')->getClientOriginalName();
                $data['proof_url'] = asset('storage/' . $path);
            }

            $updated = Handover::query()->whereKey($handover->id)
                ->whereNull('released_at')->whereNull('adopter_outcome')->whereNull('received_at')
                ->update($data);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['release_method' => 'This handover has already been released or confirmed.']);
            }
            $handover->refresh();

            $methodLabel = $handover->release_method === 'delivery' ? ($handover->courier ?: 'courier') : 'shelter';
            $handover->recordHistory("Marked as released via {$methodLabel}", $handover->staff_name);
            $handover->save();

            $this->notifications->create($handover, 'released');

            \App\Services\AuditLogService::log(Auth::id(), "Marked {$handover->pet?->name} ({$handover->code}) as released via {$methodLabel}", "Handover", $handover->id);
        });

        return back()->with('toast', ['type' => 'success', 'message' => "{$handover->pet?->name} marked as released. Adopter notified to confirm receipt."]);
    }

    public function sendReminder(Request $request, Handover $handover)
    {
        $validated = $request->validate([
            'channel' => 'required|in:Email',
        ]);

        if (! $handover->user?->email || ! $handover->user->hasVerifiedEmail()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'The adopter needs a verified email address before an email reminder can be sent.',
            ]);
        }

        $channel = $validated['channel'];
        $reminders = $handover->reminders ?? [];
        $reminders[] = [
            'at' => now()->toIso8601String(),
            'channel' => $channel,
        ];
        $handover->reminders = $reminders;

        $staff = Auth::user()?->full_name ?? 'Staff';
        $handover->recordHistory("Reminder queued for adopter ({$channel})", $staff);
        $handover->save();

        $this->notifications->create($handover, 'reminder', [
            'channels' => ['In-app', $channel],
        ]);

        \App\Services\AuditLogService::log(Auth::id(), "Queued {$channel} reminder to {$handover->adopter_name} for {$handover->pet?->name}", "Handover", $handover->id);

        return back()->with('toast', ['type' => 'success', 'message' => "{$channel} reminder queued for adopter."]);
    }

    public function reopen(Request $request, Handover $handover)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($handover, $validated): void {
            $handover = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            if ($handover->adopter_outcome === 'received' || $handover->received_at !== null) {
                throw ValidationException::withMessages(['reason' => 'A completed handover cannot be reopened.']);
            }

            $reason = $validated['reason'] ?? 'Handover redone';
            $staff = Auth::user()?->full_name ?? 'Staff';

            $handover->update([
                'release_date' => null,
                'release_time' => null,
                'released_at' => null,
                'tracking_url' => null,
                'scheduled_method' => null, 'scheduled_start_at' => null, 'scheduled_end_at' => null,
                'schedule_status' => 'unscheduled', 'schedule_version' => $handover->schedule_version + 1,
                'schedule_confirmed_at' => null, 'schedule_confirmed_by_user_id' => null,
                'reschedule_status' => null, 'reschedule_options' => null, 'reschedule_reason' => null,
                'reschedule_requested_at' => null, 'reschedule_reviewed_at' => null, 'reschedule_reviewed_by_user_id' => null,
                ...array_fill_keys(\App\Services\HandoverScheduleService::MARKERS, null),
                'adopter_outcome' => null,
                'adopter_confirmed_at' => null,
                'adopter_note' => null,
                'reopen_count' => $handover->reopen_count + 1,
                'reopen_reason' => $reason,
                'reminders' => [],
            ]);

            $handover->recordHistory("Handover reopened — {$reason}", $staff);
            $handover->recordHistory('Adopter notified: new handover being arranged', 'System');
            $handover->save();

            $this->notifications->create($handover, 'reopened');

            \App\Services\AuditLogService::log(Auth::id(), "Reopened handover for {$handover->pet?->name} ({$handover->code}): {$reason}", "Handover", $handover->id);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Handover reopened. Release details have been reset for a new attempt.']);
    }
}
