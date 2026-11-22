<?php

namespace App\Models;

final class ResearchCaptureChallenge extends PostAdoptionCaptureChallenge
{
    protected $table = 'research_capture_challenges';

    protected $fillable = [
        'id', 'placement_id', 'user_id', 'milestone', 'token_hash', 'session_hash',
        'expires_at', 'consumed_at', 'video_sha256',
    ];
}
