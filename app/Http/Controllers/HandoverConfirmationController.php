<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\Handover;
use App\Models\HandoverNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HandoverConfirmationController extends Controller
{
    public function confirmView(Request $request, Handover $handover)
    {
        $handover->load(['pet', 'notifications']);

        $unreadCount = $handover->notifications()->where('read', false)->count();

        return view('handover.confirm', [
            'record' => $handover,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function submitConfirmation(Request $request, Handover $handover)
    {
        $validated = $request->validate([
            'outcome' => 'required|in:received,not_received',
            'note' => 'nullable|string|max:1000',
        ]);

        $outcome = $validated['outcome'];
        $note = $validated['note'] ? trim($validated['note']) : null;
        $adopterName = $handover->adopter_name ?: (Auth::user()?->full_name ?? 'Adopter');

        $handover->update([
            'adopter_outcome' => $outcome,
            'adopter_confirmed_at' => now(),
            'adopter_note' => $note,
        ]);

        if ($outcome === 'received') {
            $handover->recordHistory('Adopter confirmed receipt', $adopterName);
            $handover->recordHistory('Post-adoption monitoring activated', 'System');
            $handover->save();

            $handover->createNotification('completed');

            // Activate pet & ensure monitoring schedule is active
            if ($handover->pet) {
                $handover->pet->update(['status' => 'Adopted']);
            }

            // Create check-in schedules if they don't already exist
            if ($handover->application_id && $handover->user_id) {
                $existingCheckins = CheckIn::where('application_id', $handover->application_id)->count();
                if ($existingCheckins === 0) {
                    $adoptedOn = now();
                    foreach ([
                        ['ThreeDay', $adoptedOn->copy()->addDays(3)],
                        ['ThreeWeek', $adoptedOn->copy()->addWeeks(3)],
                        ['ThreeMonth', $adoptedOn->copy()->addMonths(3)],
                    ] as [$milestone, $dueDate]) {
                        CheckIn::create([
                            'user_id' => $handover->user_id,
                            'pet_id' => $handover->pet_id,
                            'application_id' => $handover->application_id,
                            'milestone' => $milestone,
                            'due_date' => $dueDate,
                            'status' => CheckIn::STATUS_UPCOMING,
                        ]);
                    }
                }
            }

            return back()->with('toast', [
                'type' => 'success',
                'message' => "Receipt confirmed! {$handover->pet?->name}'s adoption is complete.",
            ]);
        } else {
            $handover->recordHistory('Adopter reported pet not received', $adopterName);
            $handover->save();

            $handover->createNotification('issue_logged');

            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Report submitted. Shelter staff have been notified and will contact you promptly.',
            ]);
        }
    }

    public function statusView(Request $request, Handover $handover)
    {
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
        $notification->update(['read' => true]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request, Handover $handover)
    {
        $handover->notifications()->where('read', false)->update(['read' => true]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'All notifications marked as read.']);
    }

    public function userHandoverRedirect(Request $request)
    {
        $user = Auth::user();
        $handover = null;

        if ($user) {
            $handover = Handover::where('user_id', $user->id)->latest()->first();
        }

        if (!$handover) {
            $handover = Handover::first();
        }

        if ($handover) {
            return redirect()->route('adopter.handover.status', $handover);
        }

        return redirect()->route('application.index');
    }
}
