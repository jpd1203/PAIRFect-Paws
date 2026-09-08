<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Handover extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'application_id',
        'pet_id',
        'user_id',
        'adopter_name',
        'adopter_phone',
        'adopter_email',
        'adopter_address',
        'adopter_distance',
        'approved_at',
        'release_method',
        'release_date',
        'release_time',
        'staff_name',
        'courier',
        'tracking_number',
        'proof_name',
        'proof_url',
        'released_at',
        'adopter_outcome',
        'adopter_confirmed_at',
        'adopter_note',
        'reopen_count',
        'reopen_reason',
        'reminders',
        'history',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'release_date' => 'date',
            'released_at' => 'datetime',
            'adopter_confirmed_at' => 'datetime',
            'reminders' => 'array',
            'history' => 'array',
            'reopen_count' => 'integer',
        ];
    }

    public function application()
    {
        return $this->belongsTo(AdoptionApplication::class, 'application_id');
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifications()
    {
        return $this->hasMany(HandoverNotification::class)->latest();
    }

    /**
     * Determine current pipeline step:
     * 'approved' | 'awaiting' | 'monitoring'
     */
    public function getCurrentStepAttribute(): string
    {
        if ($this->adopter_outcome === 'received') {
            return 'monitoring';
        }
        if ($this->released_at) {
            return 'awaiting';
        }
        return 'approved';
    }

    /**
     * Map completed state of each pipeline step
     */
    public function getCompletedStepsAttribute(): array
    {
        $hasReleased = !empty($this->released_at);
        $isReceived = ($this->adopter_outcome === 'received');

        return [
            'approved' => true,
            'handover_set' => $hasReleased,
            'awaiting' => $isReceived,
            'monitoring' => $isReceived,
        ];
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->pet && $this->pet->image_path) {
            return asset('storage/' . $this->pet->image_path);
        }
        return match (strtolower($this->pet?->name ?? '')) {
            'icy' => 'https://cdn.magicpatterns.com/patterns/generated-images/46ad49ab-b279-4bcd-a56f-09b9638a8b92.jpg',
            'mimi' => 'https://cdn.magicpatterns.com/patterns/generated-images/e28600dc-a1e7-46e6-86bf-d7d3abb8357c.jpg',
            'bruno' => 'https://cdn.magicpatterns.com/patterns/generated-images/4bc5c9b0-5f94-4a1e-9bf9-67d3a4349fb3.jpg',
            'mochi' => 'https://cdn.magicpatterns.com/patterns/generated-images/cc73ddf7-0cf0-46af-91f5-3ea9a1efa62d.jpg',
            default => asset('images/service-adoption.jpg'),
        };
    }

    public function getMonitoringLockedAttribute(): bool
    {
        return $this->adopter_outcome !== 'received';
    }

    public function getDaysWaitingAttribute(): ?int
    {
        if ($this->released_at && !$this->adopter_outcome) {
            return (int) Carbon::now()->diffInDays($this->released_at);
        }
        return null;
    }

    /**
     * Status badge representation
     */
    public function getStatusBadgeAttribute(): array
    {
        if ($this->adopter_outcome === 'not_received') {
            return [
                'label' => 'Delivery failed',
                'class' => 'badge-rejected',
                'border' => 'border-status-danger-text',
                'icon' => 'fa-solid fa-triangle-exclamation',
            ];
        }

        return match ($this->current_step) {
            'monitoring' => [
                'label' => 'Monitoring active',
                'class' => 'badge-completed',
                'border' => 'border-status-success-text',
                'icon' => 'fa-solid fa-circle-check',
            ],
            'awaiting' => [
                'label' => 'Awaiting adopter',
                'class' => 'badge-pending',
                'border' => 'border-status-processing-text',
                'icon' => 'fa-solid fa-clock',
            ],
            default => [
                'label' => 'Needs handover',
                'class' => 'badge-upcoming',
                'border' => 'border-[#b0b0a8]',
                'icon' => 'fa-solid fa-box-archive',
            ],
        };
    }

    public function getReleaseMethodLabelAttribute(): string
    {
        if ($this->release_method === 'delivery') {
            return ($this->courier ?: 'courier') . ' delivery';
        }
        if ($this->release_method === 'pickup') {
            return 'pickup at the shelter';
        }
        return 'handover';
    }

    public function recordHistory(string $label, string $actor): void
    {
        $history = $this->history ?? [];
        $history[] = [
            'at' => now()->toIso8601String(),
            'label' => $label,
            'actor' => $actor,
        ];
        $this->history = $history;
    }

    public function createNotification(string $kind, array $attributes = []): HandoverNotification
    {
        $petName = $this->pet?->name ?? 'your pet';
        $methodLabel = $this->release_method_label;

        $defaults = match ($kind) {
            'prepared' => [
                'title' => 'Your adoption is being prepared for release',
                'body' => "Shelter staff have scheduled {$petName}'s {$methodLabel}. We'll message you the moment {$petName} is on the way.",
                'channels' => ['In-app', 'Email'],
                'action_label' => 'View handover details',
                'action_url' => route('adopter.handover.status', $this),
            ],
            'released' => [
                'title' => "{$petName} has been released via {$methodLabel}",
                'body' => "Please confirm once {$petName} is with you — your adoption is not complete until you confirm receipt.",
                'channels' => ['In-app', 'Email', 'SMS'],
                'action_label' => 'Confirm receipt',
                'action_url' => route('adopter.confirm', $this),
            ],
            'reminder' => [
                'title' => "Reminder: Please confirm receipt of {$petName}",
                'body' => "It has been several days since {$petName} was released. Please confirm receipt so post-adoption check-ins can begin.",
                'channels' => ['In-app', 'SMS'],
                'action_label' => 'Confirm receipt',
                'action_url' => route('adopter.confirm', $this),
            ],
            'completed' => [
                'title' => "Welcome home, {$petName}!",
                'body' => "Thank you for confirming receipt. Your adoption is now complete and your first post-adoption check-in has been scheduled.",
                'channels' => ['In-app', 'Email'],
                'action_label' => 'View my check-ins',
                'action_url' => route('monitoring.index'),
            ],
            'issue_logged' => [
                'title' => "We're sorting out {$petName}'s handover",
                'body' => "Your report was sent to the shelter. A staff member will contact you at {$this->adopter_phone} to arrange a new handover.",
                'channels' => ['In-app', 'SMS'],
                'action_label' => 'View handover status',
                'action_url' => route('adopter.handover.status', $this),
            ],
            'reopened' => [
                'title' => "A new handover is being arranged for {$petName}",
                'body' => "The previous release was cancelled. We'll notify you again once {$petName}'s new pickup or delivery is scheduled.",
                'channels' => ['In-app', 'SMS', 'Email'],
                'action_label' => 'View handover status',
                'action_url' => route('adopter.handover.status', $this),
            ],
            default => [
                'title' => "Update regarding {$petName}'s handover",
                'body' => "A new update has been recorded for {$petName}'s adoption.",
                'channels' => ['In-app'],
                'action_label' => 'View handover status',
                'action_url' => route('adopter.handover.status', $this),
            ],
        };

        return HandoverNotification::create([
            'handover_id' => $this->id,
            'user_id' => $this->user_id,
            'kind' => $kind,
            'title' => $attributes['title'] ?? $defaults['title'],
            'body' => $attributes['body'] ?? $defaults['body'],
            'channels' => $attributes['channels'] ?? $defaults['channels'],
            'action_label' => $attributes['action_label'] ?? $defaults['action_label'],
            'action_url' => $attributes['action_url'] ?? $defaults['action_url'],
            'read' => false,
        ]);
    }
}
