<?php

namespace App\Models;

/**
 * Research application and simulated handover state. Real application queries,
 * staff tools, and background jobs continue to use the production table.
 */
final class ResearchPlacement extends AdoptionApplication
{
    protected $table = 'research_placements';

    protected $fillable = ['user_id', 'pet_id', 'status', 'is_primary_candidate', 'compatibility_result', 'compatibility_score', 'algorithm_version', 'queue_promoted_at', 'queue_closed_at', 'approved_at', 'released_at'];
}
