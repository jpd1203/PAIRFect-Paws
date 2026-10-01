<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Services\HandoverService;
use App\Services\HandoverNotificationService;
use App\Services\PostAdoptionScheduleService;
use Illuminate\Http\Request;

class HandoverConfirmationController extends Controller
{
    public function __construct(
        private readonly HandoverService $handovers,
        private readonly PostAdoptionScheduleService $postAdoptionSchedule,
        private readonly HandoverNotificationService $notifications,
    ) {}

    public function confirmView(Request $request, Handover $handover)
    {
        $this->authorizeOwnedHandover($request, $handover);
        $handover->load(['pet', 'notifications']);

        $unreadCount = $handover->notifications()->where('read', false)->count();

        return view('handover.confirm', [
            'record' => $handover,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function submitConfirmation(Request $request, Handover $handover)
    {
        $this->authorizeOwnedHandover($request, $handover);

        $validated = $request->validate([
            'outcome' => 'required|in:received,not_received',
            'note' => 'nullable|string|max:1000',
        ]);

        $outcome = $validated['outcome'];
        $note = filled($validated['note'] ?? null) ? trim($validated['note']) : null;
        $adopterName = $handover->adopter_name ?: ($request->user()?->full_name ?? 'Adopter');

        $handover->update([
            'adopter_outcome' => $outcome,
            'adopter_confirmed_at' => now(),
            'adopter_note' => $note,
        ]);

        if ($outcome === 'received') {
            $handover->recordHistory('Adopter confirmed receipt', $adopterName);
            $handover->recordHistory('Post-adoption monitoring activated', 'System');
            $handover->save();

            $this->notifications->create($handover, 'completed');

            $application = $handover->application;
            if ($application?->status === ApplicationStatus::Approved) {
                $this->postAdoptionSchedule->ensureForApplication($application);
            }

            return back()->with('toast', [
                'type' => 'success',
                'message' => "Receipt confirmed! {$handover->pet?->name}'s adoption is complete.",
            ]);
        } else {
            $handover->recordHistory('Adopter reported pet not received', $adopterName);
            $handover->save();

            $this->notifications->create($handover, 'issue_logged');

            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Report submitted. Shelter staff have been notified and will contact you promptly.',
            ]);
        }
    }

    public function statusView(Request $request, Handover $handover)
    {
        $this->authorizeOwnedHandover($request, $handover);
        $handover->load(['pet', 'notifications']);

        $unreadCount = $handover->notifications()->where('read', false)->count();
        $latestUpdates = $handover->notifications()->take(2)->get();

        return view('handover.status', [
            'record' => $handover,
            'unreadCount' => $unreadCount,
            'latestUpdates' => $latestUpdates,
        ]);
    }

    public function notificationsView(Request $request, Handover $handover)
    {
        $this->authorizeOwnedHandover($request, $handover);
        $handover->load(['pet', 'notifications']);

        $notifications = $handover->notifications()->get();
        $unreadCount = $notifications->where('read', false)->count();

        return view('handover.notifications', [
            'record' => $handover,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, HandoverNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update(['read' => true]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request, Handover $handover)
    {
        $this->authorizeOwnedHandover($request, $handover);
        $handover->notifications()->where('read', false)->update(['read' => true]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'All notifications marked as read.']);
    }

    public function userHandoverRedirect(Request $request)
    {
        $user = $request->user();
        $handover = Handover::query()
            ->where('user_id', $user->id)
            ->latest('approved_at')
            ->latest('id')
            ->first();

        if (! $handover) {
            $application = AdoptionApplication::query()
                ->where('user_id', $user->id)
                ->where('status', ApplicationStatus::Approved->value)
                ->latest('adopted_at')
                ->latest('id')
                ->first();

            if ($application) {
                $handover = $this->handovers->forApprovedApplication($application);
            }
        }

        if ($handover) {
            return redirect()->route('adopter.handover.status', $handover);
        }

        return redirect()->route('application.index')->with(
            'info',
            'Handover Status becomes available after an adoption has been approved.'
        );
    }

    private function authorizeOwnedHandover(Request $request, Handover $handover): void
    {
        abort_unless((int) $handover->user_id === (int) $request->user()->id, 403);
    }
}
