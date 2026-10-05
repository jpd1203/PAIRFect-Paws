<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Handover;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\HandoverNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HandoverController extends Controller
{
    public function __construct(private HandoverNotificationService $notifications) {}

    public function index(Request $request)
    {
        $query = Handover::with(['pet', 'application', 'user'])
            ->orderByDesc('approved_at')
            ->orderByDesc('id');

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
        $handover->load(['pet', 'application', 'user', 'notifications', 'releaseRecordedBy', 'releaseHandledBy']);

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

    public function releaseProof(Request $request, Handover $handover)
    {
        abort_unless($request->user()->is_active && $request->user()->isStaff() && $request->user()->hasVerifiedEmail(), 403);
        $disk = $handover->proof_path ? 'local' : 'public';
        $path = $handover->proof_path ?: $handover->legacyPublicProofPath();
        if ($handover->proof_path) {
            abort_unless((bool) preg_match('~^handover-proofs/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp|gif)$~i', $path), 404);
        }
        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markReleased(Request $request, Handover $handover)
    {
        $validated = $request->validate([
            'release_method' => 'required|in:pickup,delivery',
            'release_date' => 'required|date',
            'release_time' => 'required|string|max:20',
            'staff_id' => 'required|integer|exists:users,id',
            'courier' => 'nullable|required_if:release_method,delivery|string|max:100',
            'tracking_number' => 'nullable|required_if:release_method,delivery|string|max:100',
            'proof' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
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

        $storedPath = null;
        try {
            $handover = DB::transaction(function () use ($request, $handover, $validated, $staffMember, &$storedPath): Handover {
                $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
                $current->ensureCanRelease();

                if ($request->hasFile('proof')) {
                    $storedPath = $request->file('proof')->store('handover-proofs', 'local');
                    if (! $storedPath || ! Storage::disk('local')->exists($storedPath)) {
                        throw ValidationException::withMessages(['proof' => 'The handover proof could not be stored. Please try again.']);
                    }
                }

                $updated = Handover::query()->whereKey($current->id)
                    ->whereNull('released_at')->whereNull('adopter_outcome')
                    ->update([
                        'release_method' => $validated['release_method'],
                        'release_date' => $validated['release_date'],
                        'release_time' => $validated['release_time'],
                        'staff_name' => $staffMember->full_name,
                        'release_recorded_by_user_id' => Auth::id(),
                        'release_handled_by_user_id' => $staffMember->id,
                        'courier' => $validated['release_method'] === 'delivery' ? ($validated['courier'] ?? null) : null,
                        'tracking_number' => $validated['release_method'] === 'delivery' ? ($validated['tracking_number'] ?? null) : null,
                        'proof_path' => $storedPath,
                        'proof_name' => $storedPath ? $request->file('proof')->getClientOriginalName() : null,
                        'proof_url' => null,
                        'released_at' => now(),
                    ]);
                if ($updated !== 1) {
                    throw ValidationException::withMessages(['release_method' => 'This handover has already been released.']);
                }
                $current->refresh();

                $methodLabel = $current->release_method === 'delivery' ? ($current->courier ?: 'courier') : 'shelter';
                $current->recordHistory("Marked as released via {$methodLabel}; handled by {$staffMember->full_name}", Auth::user()->full_name);
                $current->save();
                AuditLogService::log(Auth::id(), 'handover.released', 'Handover', $current->id,
                    "Prepared to Released; recorded by user ".Auth::id()."; handled by user {$staffMember->id}; method {$current->release_method}.");

                return $current;
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        $this->notifications->create($handover, 'released');

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

        $reason = $validated['reason'] ?? 'Handover redone';
        $handover = DB::transaction(function () use ($handover, $reason): Handover {
            $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
            $current->ensureCanReopen();
            $previousState = $current->adopter_outcome === 'not_received' ? 'Not Received' : 'Released';
            $previousHandler = $current->release_handled_by_user_id;
            $previousActor = $current->release_recorded_by_user_id;
            $previousHandlerName = $current->staff_name;

            $updated = Handover::query()->whereKey($current->id)
                ->whereNotNull('released_at')
                ->where(function ($query): void {
                    $query->whereNull('adopter_outcome')->orWhere('adopter_outcome', 'not_received');
                })
                ->update([
                    'release_date' => null,
                    'release_time' => null,
                    'released_at' => null,
                    'adopter_outcome' => null,
                    'adopter_confirmed_at' => null,
                    'adopter_note' => null,
                    'proof_path' => null,
                    'proof_name' => null,
                    'proof_url' => null,
                    'staff_name' => null,
                    'release_recorded_by_user_id' => null,
                    'release_handled_by_user_id' => null,
                    'reopen_count' => $current->reopen_count + 1,
                    'reopen_reason' => $reason,
                ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['reason' => 'This handover is no longer eligible to be reopened.']);
            }
            $current->refresh();
            $current->reminders = [];

            $current->recordHistory("Handover reopened — {$reason}", Auth::user()->full_name);
            $current->recordHistory('Adopter notified: new handover being arranged', 'System');
            $current->save();
            AuditLogService::log(Auth::id(), 'handover.reopened', 'Handover', $current->id,
                "{$previousState} to Prepared; reason: {$reason}; previous recorded by user {$previousActor}; previous handler user {$previousHandler} ({$previousHandlerName}).");

            return $current;
        });

        $this->notifications->create($handover, 'reopened');

        return back()->with('toast', ['type' => 'success', 'message' => 'Handover reopened. Release details have been reset for a new attempt.']);
    }
}
