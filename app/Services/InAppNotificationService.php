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
            'action_url' => $this->normalizeActionUrl($actionUrl),
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

    private function normalizeActionUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || str_starts_with($url, '//')) {
            return $url;
        }

        $path = $parts['path'] ?? (isset($parts['host']) ? '/' : '');
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return $url;
        }
        if (! isset($parts['host']) && ! isset($parts['scheme'])) {
            return $url;
        }

        $configuredHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $baseHost = preg_replace('/^www\./', '', $configuredHost);
        if (! $baseHost || ! in_array(strtolower($parts['host'] ?? ''), [$baseHost, 'www.'.$baseHost], true)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            return $url;
        }

        return $path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
