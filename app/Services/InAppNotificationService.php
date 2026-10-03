<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\HandoverNotification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/** One in-app feed, including the existing handover notices. */
final class InAppNotificationService
{
    public function user(
        ?User $recipient,
        string $kind,
        string $title,
        string $message,
        string $actionUrl,
        string $dedupeKey,
        ?string $entityType = null,
        ?int $entityId = null,
    ): ?HandoverNotification {
        if (! $recipient?->is_active) {
            return null;
        }

        $attributes = [
            'kind' => $kind,
            'title' => $title,
            'body' => $message,
            'channels' => ['In-app'],
            'action_label' => 'View details',
            'action_url' => $actionUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'read' => false,
        ];

        try {
            return $recipient->inAppNotifications()->firstOrCreate(['dedupe_key' => $dedupeKey], $attributes);
        } catch (UniqueConstraintViolationException) {
            return $recipient->inAppNotifications()->where('dedupe_key', $dedupeKey)->first();
        } catch (Throwable $exception) {
            // Notification persistence must not undo a completed adoption action.
            Log::error('An in-app notification could not be saved.', [
                'recipient_user_id' => $recipient->id,
                'kind' => $kind,
                'entity_id' => $entityId,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    /** Operational notices go to administrators; volunteers receive only assigned-work notices. */
    public function administrators(
        string $kind,
        string $title,
        string $message,
        string $actionUrl,
        string $dedupeKey,
        ?string $entityType = null,
        ?int $entityId = null,
    ): void {
        try {
            User::query()->where('role', Role::Administrator->value)->where('is_active', true)
                ->chunkById(100, function ($administrators) use ($kind, $title, $message, $actionUrl, $dedupeKey, $entityType, $entityId): void {
                    foreach ($administrators as $administrator) {
                        $this->user($administrator, $kind, $title, $message, $actionUrl, $dedupeKey, $entityType, $entityId);
                    }
                });
        } catch (Throwable $exception) {
            Log::error('Administrator in-app notifications could not be saved.', [
                'kind' => $kind,
                'entity_id' => $entityId,
                'exception' => $exception::class,
            ]);
        }
    }
}
