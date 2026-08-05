<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlaggedCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_in_id', 'description', 'is_escalated', 'escalation_reason',
        'marked_for_intervention', 'intervention_type', 'intervention_notes',
        'resolved', 'resolution_notes', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'is_escalated' => 'boolean',
            'marked_for_intervention' => 'boolean',
            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function checkIn()
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function report()
    {
        return $this->hasOneThrough(
            PostAdoptionReport::class,
            CheckIn::class,
            'id',        // check_ins.id
            'check_in_id', // post_adoption_reports.check_in_id
            'check_in_id', // flagged_cases.check_in_id
            'id'          // check_ins.id
        );
    }
}
