<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Services\HandoverService;
use App\Services\HandoverNotificationService;
use App\Services\PostAdoptionScheduleService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
            'receipt_proof' => 'required_if:outcome,received|nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $outcome = $validated['outcome'];
        $note = filled($validated['note'] ?? null) ? trim($validated['note']) : null;
        $disk = (string) config('filesystems.handover_receipts_disk', 'local');
        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $handover, $outcome, $note, $disk, &$storedPath): void {
                $current = Handover::query()->lockForUpdate()->findOrFail($handover->id);
                $this->authorizeOwnedHandover($request, $current);

                if ($current->adopter_outcome !== null) {
                    throw ValidationException::withMessages(['outcome' => 'This handover has already been confirmed.']);
                }

                $adopterName = $current->adopter_name ?: ($request->user()?->full_name ?? 'Adopter');
                $confirmedAt = now();

                if ($outcome === 'received') {
                    if ($current->released_at === null) {
                        throw ValidationException::withMessages(['outcome' => 'The pet must be marked as released before receipt can be confirmed.']);
                    }

                    $file = $request->file('receipt_proof');
                    $storedPath = $file->store('handover-receipts', $disk);
                    if (! $storedPath || ! Storage::disk($disk)->exists($storedPath)) {
                        throw new RuntimeException('The receipt photo could not be stored.');
                    }
                    $hash = hash_file('sha256', $file->getRealPath());
                    if ($hash === false) {
                        throw new RuntimeException('The receipt photo could not be verified.');
                    }

                    // The conditional update also guards against duplicate requests on databases
                    // where SELECT ... FOR UPDATE does not provide row-level locking (e.g. SQLite).
                    $updated = Handover::query()->whereKey($current->id)
                        ->whereNotNull('released_at')->whereNull('adopter_outcome')
                        ->update([
                            'adopter_outcome' => 'received',
                            'adopter_confirmed_at' => $confirmedAt,
                            'received_at' => $confirmedAt,
                            'adopter_note' => $note,
                            'receipt_proof_disk' => $disk,
                            'receipt_proof_path' => $storedPath,
                            'receipt_proof_hash' => $hash,
                        ]);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['outcome' => 'This handover has already been confirmed.']);
                    }

                    $current->refresh();
                    $current->recordHistory('Adopter confirmed receipt with supporting photo', $adopterName);
                    $current->recordHistory('Post-adoption monitoring activated', 'System');
                    $current->save();

                    $application = $current->application;
                    if ($application?->status === ApplicationStatus::Approved) {
                        $this->postAdoptionSchedule->ensureForApplication($application);
                    }
                    AuditLogService::log($request->user()->id, 'handover.received', 'Handover', $current->id,
                        "Application {$current->application_id}; pet {$current->pet_id}; adopter {$current->user_id}.");
                    $this->notifications->create($current, 'completed');
                } else {
                    $current->update([
                        'adopter_outcome' => 'not_received',
                        'adopter_confirmed_at' => $confirmedAt,
                        'adopter_note' => $note,
                    ]);
                    $current->recordHistory('Adopter reported pet not received', $adopterName);
                    $current->save();
                    $this->notifications->create($current, 'issue_logged');
                }
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk($disk)->delete($storedPath);
            }
            throw $exception;
        }

        return back()->with('toast', $outcome === 'received'
            ? ['type' => 'success', 'message' => "Receipt confirmed! {$handover->pet?->name}'s adoption is complete."]
            : ['type' => 'warning', 'message' => 'Report submitted. Shelter staff have been notified and will contact you promptly.']);
    }

    public function receiptProof(Request $request, Handover $handover)
    {
        abort_unless($request->user()->is_active && ($request->user()->isStaff() || (int) $handover->user_id === (int) $request->user()->id), 403);
        abort_unless($handover->receipt_proof_path && $handover->receipt_proof_disk, 404);

        $disk = Storage::disk($handover->receipt_proof_disk);
        abort_unless($disk->exists($handover->receipt_proof_path), 404);

        return $disk->response($handover->receipt_proof_path, null, [
            'Content-Type' => $disk->mimeType($handover->receipt_proof_path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

        $notification->update(['read' => true, 'read_at' => $notification->read_at ?? now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request, Handover $handover)
    {
        $this->authorizeOwnedHandover($request, $handover);
        $handover->notifications()->where('read', false)->update(['read' => true, 'read_at' => now()]);

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
