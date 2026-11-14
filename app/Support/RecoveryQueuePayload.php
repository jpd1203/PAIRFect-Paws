<?php

namespace App\Support;

use Illuminate\Queue\SyncQueue;

/** Use Laravel's own encrypted job format without connecting to a broken queue. */
final class RecoveryQueuePayload extends SyncQueue
{
    public function forJob(object $job): string
    {
        return $this->createPayload($job, 'emails');
    }
}
