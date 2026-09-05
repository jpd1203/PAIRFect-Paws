<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Illuminate\Support\Collection;

class AuditLogController extends Controller
{
    /**
     * GET /admin/audit-logs — read-only full audit trail
     */
    public function index()
    {
        $logs = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);

        $this->addActionContext($logs->getCollection());

        return view('admin.audit-logs.index', compact('logs'));
    }

    /**
     * Resolve audit subjects in batches so the action column is useful without
     * introducing an N+1 query for every displayed log entry.
     *
     * @param  Collection<int, AuditLog>  $logs
     */
    private function addActionContext(Collection $logs): void
    {
        $idsFor = fn (string $entity): Collection => $logs
            ->filter(fn (AuditLog $log): bool => class_basename($log->entity_name) === $entity)
            ->pluck('entity_id')
            ->filter()
            ->unique()
            ->values();

        $petNames = Pet::withoutGlobalScopes()
            ->whereKey($idsFor('Pet'))
            ->pluck('name', 'id');

        $applications = AdoptionApplication::query()
            ->with([
                'pet' => fn ($query) => $query->withoutGlobalScopes(),
                'user',
            ])
            ->whereKey($idsFor('AdoptionApplication'))
            ->get()
            ->keyBy('id');

        $postAdoptionLogs = PostAdoptionLog::query()
            ->with([
                'adoptionApplication.pet' => fn ($query) => $query->withoutGlobalScopes(),
                'adoptionApplication.user',
            ])
            ->whereKey($idsFor('PostAdoptionLog'))
            ->get()
            ->keyBy('id');

        $users = User::query()
            ->whereKey($idsFor('User'))
            ->get()
            ->keyBy('id');

        $logs->each(function (AuditLog $log) use ($petNames, $applications, $postAdoptionLogs, $users): void {
            $entity = class_basename($log->entity_name);
            $subject = match ($entity) {
                'Pet' => $petNames->get($log->entity_id)
                    ?? $this->petNameFromNotes($log->notes)
                    ?? ($log->entity_id ? "Pet #{$log->entity_id}" : null),
                'AdoptionApplication' => $this->applicationLabel(
                    $applications->get($log->entity_id),
                    $log->entity_id,
                ),
                'PostAdoptionLog' => $this->monitoringLabel(
                    $postAdoptionLogs->get($log->entity_id),
                    $log->entity_id,
                ),
                'User' => $users->get($log->entity_id)?->full_name
                    ?? ($log->entity_id ? "Account #{$log->entity_id}" : null),
                default => filled($entity) && $log->entity_id
                    ? str($entity)->headline()." #{$log->entity_id}"
                    : null,
            };

            $log->setAttribute('subject_label', $subject);
            $log->setAttribute(
                'display_action',
                filled($subject) ? "{$log->action} - {$subject}" : $log->action,
            );
        });
    }

    private function applicationLabel(?AdoptionApplication $application, ?int $id): ?string
    {
        if ($application === null) {
            return $id ? "Application #{$id}" : null;
        }

        return collect([
            $application->pet?->name,
            $application->user?->full_name,
        ])->filter()->implode(' / ') ?: "Application #{$application->id}";
    }

    private function monitoringLabel(?PostAdoptionLog $log, ?int $id): ?string
    {
        if ($log === null) {
            return $id ? "Check-in #{$id}" : null;
        }

        $application = $log->adoptionApplication;
        $placement = collect([
            $application?->pet?->name,
            $application?->user?->full_name,
        ])->filter()->implode(' / ');
        $milestone = $log->milestone?->shortLabel();

        return collect([$placement, $milestone])->filter()->implode(' - ')
            ?: "Check-in #{$log->id}";
    }

    private function petNameFromNotes(?string $notes): ?string
    {
        if (! is_string($notes)) {
            return null;
        }

        return preg_match('/(?:animal|pet):\s*([^.;(]+)/i', $notes, $matches) === 1
            ? trim($matches[1])
            : null;
    }
}
