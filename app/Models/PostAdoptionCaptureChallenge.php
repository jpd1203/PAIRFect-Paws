<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostAdoptionCaptureChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'post_adoption_log_id',
        'user_id',
        'token_hash',
        'session_hash',
        'photo_sha256',
        'video_sha256',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'token_hash',
        'session_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function postAdoptionLog()
    {
        return $this->belongsTo(PostAdoptionLog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
