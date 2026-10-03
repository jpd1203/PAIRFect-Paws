<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HandoverNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'handover_id',
        'user_id',
        'kind',
        'title',
        'body',
        'channels',
        'action_label',
        'action_url',
        'read',
        'read_at',
        'entity_type',
        'entity_id',
        'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function handover()
    {
        return $this->belongsTo(Handover::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getMetaAttribute(): array
    {
        return match ($this->kind) {
            'prepared' => [
                'accent' => 'bg-text-dark',
                'chip' => 'bg-neutral-light',
                'chipText' => 'text-text-muted',
                'label' => 'Handover set',
                'icon' => 'fa-solid fa-calendar-days',
            ],
            'released' => [
                'accent' => 'bg-primary',
                'chip' => 'bg-primary-muted',
                'chipText' => 'text-primary',
                'label' => 'Action needed',
                'icon' => 'fa-solid fa-truck-fast',
            ],
            'reminder' => [
                'accent' => 'bg-[#d97706]',
                'chip' => 'bg-status-processing-bg',
                'chipText' => 'text-status-processing-text',
                'label' => 'Reminder',
                'icon' => 'fa-solid fa-clock',
            ],
            'completed' => [
                'accent' => 'bg-status-success-text',
                'chip' => 'bg-status-success-bg',
                'chipText' => 'text-status-success-text',
                'label' => 'Completed',
                'icon' => 'fa-solid fa-circle-check',
            ],
            'issue_logged' => [
                'accent' => 'bg-status-danger-text',
                'chip' => 'bg-status-danger-bg',
                'chipText' => 'text-status-danger-text',
                'label' => 'Issue reported',
                'icon' => 'fa-solid fa-triangle-exclamation',
            ],
            'reopened' => [
                'accent' => 'bg-text-dark',
                'chip' => 'bg-neutral-light',
                'chipText' => 'text-text-muted',
                'label' => 'New handover',
                'icon' => 'fa-solid fa-rotate-left',
            ],
            default => [
                'accent' => 'bg-[#777]',
                'chip' => 'bg-neutral-light',
                'chipText' => 'text-text-muted',
                'label' => 'Update',
                'icon' => 'fa-solid fa-bell',
            ],
        };
    }
}
