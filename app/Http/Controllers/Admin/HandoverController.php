<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HandoverController extends Controller
{
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
        $validated = $request->validate([
            'release_method' => 'required|in:pickup,delivery',
            'release_date' => 'required|date',
            'release_time' => 'required|string|max:20',
            'staff_id' => 'required|exists:users,id',
            'courier' => 'nullable|required_if:release_method,delivery|string|max:100',
            'tracking_number' => 'nullable|required_if:release_method,delivery|string|max:100',
            'proof' => 'nullable|image|max:5120',
        ]);

        $data = [
            'release_method' => $validated['release_method'],
            'release_date' => $validated['release_date'],
            'release_time' => $validated['release_time'],
            'staff_name' => $staffMember->full_name,
            'courier' => $validated['release_method'] === 'delivery' ? ($validated['courier'] ?? null) : null,
            'tracking_number' => $validated['release_method'] === 'delivery' ? ($validated['tracking_number'] ?? null) : null,
            'released_at' => now(),
            'adopter_outcome' => null,
        ];

        if ($request->hasFile('proof')) {
            $path = $request->file('proof')->store('proofs', 'public');
            $data['proof_path'] = $path;
            $data['proof_name'] = $request->file('proof')->getClientOriginalName();
            $data['proof_url'] = asset('storage/' . $path);
        }

        $handover->update($data);

        $methodLabel = $handover->release_method === 'delivery' ? ($handover->courier ?: 'courier') : 'shelter';
        $handover->recordHistory("Marked as released via {$methodLabel}", $handover->staff_name);
        $handover->save();

        $handover->createNotification('released');

        \App\Services\AuditLogService::log(Auth::id(), "Marked {$handover->pet?->name} ({$handover->code}) as released via {$methodLabel}", "Handover", $handover->id);

        return back()->with('toast', ['type' => 'success', 'message' => "{$handover->pet?->name} marked as released. Adopter notified to confirm receipt."]);
    }

    public function sendReminder(Request $request, Handover $handover, \App\Services\EmailNotificationService $emailNotifications)
    {
        $validated = $request->validate([
            'channel' => 'required|in:Email',
        ]);

        $channel = $validated['channel'];
        $reminders = $handover->reminders ?? [];
        $reminders[] = [
            'at' => now()->toIso8601String(),
            'channel' => $channel,
        ];
        $handover->reminders = $reminders;

        $staff = Auth::user()?->full_name ?? 'Staff';
        $handover->recordHistory("Reminder sent to adopter ({$channel})", $staff);
        $handover->save();

        $handover->createNotification('reminder', [
            'channels' => ['In-app', $channel],
        ]);

        \App\Services\AuditLogService::log(Auth::id(), "Sent {$channel} reminder to {$handover->adopter_name} for {$handover->pet?->name}", "Handover", $handover->id);

        $petName = $handover->pet?->name ?? 'your pet';
        $emailNotifications->user(
            $handover->user,
            "Reminder: Please confirm receipt of {$petName}",
            "Confirm {$petName}'s arrival",
            [
                "It has been several days since {$petName} was released.",
                "Please confirm receipt so your post-adoption check-ins can begin."
            ],
            'Confirm receipt',
            route('adopter.confirm', $handover),
            'handover_reminder',
            $handover->id
        );

        return back()->with('toast', ['type' => 'success', 'message' => "{$channel} reminder sent to adopter."]);
    }

    public function reopen(Request $request, Handover $handover)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = $validated['reason'] ?? 'Handover redone';
        $staff = Auth::user()?->full_name ?? 'Staff';

        $handover->update([
            'release_date' => null,
            'release_time' => null,
            'released_at' => null,
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

        $handover->createNotification('reopened');

        \App\Services\AuditLogService::log(Auth::id(), "Reopened handover for {$handover->pet?->name} ({$handover->code}): {$reason}", "Handover", $handover->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Handover reopened. Release details have been reset for a new attempt.']);
    }
}
