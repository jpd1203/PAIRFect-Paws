<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    /**
     * Create an immutable audit log entry.
     *
     * @param  int|null  $userId
     * @param  string    $action       e.g. 'Adoption Application Submitted'
     * @param  string    $entityName   e.g. 'AdoptionApplication'
     * @param  int|null  $entityId
     * @param  string|null $notes
     */
    public static function log(?int $userId, string $action, string $entityName = '', ?int $entityId = null, ?string $notes = null): AuditLog
    {
        return AuditLog::create([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_name' => $entityName,
            'entity_id'   => $entityId,
            'notes'       => $notes,
        ]);
    }
}

