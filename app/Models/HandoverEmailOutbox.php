<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandoverEmailOutbox extends Model
{
    protected $table = 'handover_email_outbox';

    protected $guarded = ['id'];

    protected $hidden = ['payload', 'enqueue_token'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'schedule_version' => 'integer', 'release_attempt' => 'integer',
            'queue_attempts' => 'integer', 'delivery_attempts' => 'integer',
            'dispatch_lease_expires_at' => 'datetime', 'queued_at' => 'datetime',
            'transport_accepted_at' => 'datetime', 'suppressed_at' => 'datetime',
        ];
    }
}
