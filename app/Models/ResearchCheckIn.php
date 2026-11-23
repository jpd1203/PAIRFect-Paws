<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ResolutionOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResearchCheckIn extends Model
{
    protected $table = 'research_checkins';

    public $timestamps = false;

    protected $fillable = [
        'placement_id', 'milestone', 'answers', 'submitted_at', 'video_path', 'video_sha256', 'video_mime_type',
    ];

    protected $hidden = ['welfare_case', 'video_path', 'video_sha256'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'immutable_datetime',
            'is_flagged' => 'boolean',
            'resolved_at' => 'immutable_datetime',
            'resolution_outcome' => ResolutionOutcome::class,
            'welfare_evaluated_at' => 'immutable_datetime',
            'welfare_case' => 'array',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(ResearchPlacement::class, 'placement_id');
    }

    public function scopeAfterReceipt(Builder $query): Builder
    {
        return $query->whereHas('placement', fn (Builder $placement) => $placement
            ->where('status', ApplicationStatus::Approved->value)
            ->whereNotNull('approved_at')
            ->whereNotNull('received_at'));
    }

    public function scopeOpenFlags(Builder $query): Builder
    {
        return $query->where('is_flagged', true)->whereNull('resolved_at');
    }
}
