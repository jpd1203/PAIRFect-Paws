<?php

namespace App\Services;

use App\Models\PostAdoptionCaptureChallenge;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PostAdoptionCaptureChallengeService
{
    /**
     * @return array{record: PostAdoptionCaptureChallenge, token: string, expires_in_seconds: int}
     */
    public function issue(PostAdoptionLog $log, User $user, string $sessionBinding): array
    {
        $ttlSeconds = $this->ttlSeconds();
        $now = CarbonImmutable::now('UTC');
        $token = bin2hex(random_bytes(32));

        $record = DB::transaction(function () use ($log, $user, $sessionBinding, $now, $ttlSeconds, $token) {
            $lockedLog = PostAdoptionLog::query()->lockForUpdate()->findOrFail($log->id);

            if ($lockedLog->submitted_date !== null) {
                throw ValidationException::withMessages([
                    'report' => 'This check-in has already been submitted.',
                ]);
            }

            PostAdoptionCaptureChallenge::query()
                ->where('post_adoption_log_id', $log->id)
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->delete();

            return PostAdoptionCaptureChallenge::create([
                'id' => (string) Str::uuid(),
                'post_adoption_log_id' => $log->id,
                'user_id' => $user->id,
                'token_hash' => $this->tokenHash($token),
                'session_hash' => $this->sessionHash($sessionBinding),
                'expires_at' => $now->addSeconds($ttlSeconds),
            ]);
        }, 3);

        return [
            'record' => $record,
            'token' => $token,
            'expires_in_seconds' => $ttlSeconds,
        ];
    }

    public function validate(
        string $challengeId,
        string $token,
        PostAdoptionLog $log,
        User $user,
        string $sessionBinding,
        CarbonImmutable $capturedAt,
    ): PostAdoptionCaptureChallenge {
        $challenge = PostAdoptionCaptureChallenge::query()->find($challengeId);
        $this->assertUsable($challenge, $token, $log, $user, $sessionBinding, $capturedAt);

        return $challenge;
    }

    /**
     * Consume a challenge inside the caller's transaction. The log should be
     * locked first so concurrent report submissions use one consistent order.
     */
    public function consumeLocked(
        string $challengeId,
        string $token,
        PostAdoptionLog $log,
        User $user,
        string $sessionBinding,
        CarbonImmutable $capturedAt,
        string $videoSha256,
    ): PostAdoptionCaptureChallenge {
        $challenge = PostAdoptionCaptureChallenge::query()
            ->lockForUpdate()
            ->find($challengeId);

        $this->assertUsable($challenge, $token, $log, $user, $sessionBinding, $capturedAt);

        $challenge->forceFill([
            'consumed_at' => CarbonImmutable::now('UTC'),
            'video_sha256' => $videoSha256,
        ])->save();

        return $challenge->refresh();
    }

    private function assertUsable(
        ?PostAdoptionCaptureChallenge $challenge,
        string $token,
        PostAdoptionLog $log,
        User $user,
        string $sessionBinding,
        CarbonImmutable $capturedAt,
    ): void {
        $now = CarbonImmutable::now('UTC');
        $clockSkewSeconds = max(
            0,
            (int) config('post_adoption.capture.client_clock_skew_seconds', 120),
        );

        $reason = match (true) {
            $challenge === null => 'not_found',
            $challenge->post_adoption_log_id !== $log->id => 'wrong_log',
            $challenge->user_id !== $user->id => 'wrong_user',
            ! hash_equals($challenge->token_hash, $this->tokenHash($token)) => 'wrong_token',
            ! hash_equals($challenge->session_hash, $this->sessionHash($sessionBinding)) => 'wrong_session',
            $challenge->consumed_at !== null => 'already_consumed',
            $challenge->expires_at->lt($now) => 'expired',
            $capturedAt->lt($challenge->created_at->toImmutable()->utc()->subSeconds($clockSkewSeconds)) => 'captured_before_issue',
            $capturedAt->gt($now->addSeconds($clockSkewSeconds)) => 'captured_in_future',
            default => null,
        };

        if ($reason !== null) {
            Log::notice('A post-adoption capture challenge was rejected.', [
                'post_adoption_log_id' => $log->id,
                'challenge_id' => $challenge?->id,
                'reason_code' => $reason,
            ]);

            throw ValidationException::withMessages([
                'capture_challenge' => 'Your secure camera session expired or was already used. Start the camera and record a new video.',
            ]);
        }
    }

    private function ttlSeconds(): int
    {
        return 300;
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', 'capture-token|'.$token, $this->hashKey());
    }

    private function sessionHash(string $sessionBinding): string
    {
        return hash_hmac('sha256', 'capture-session|'.$sessionBinding, $this->hashKey());
    }

    private function hashKey(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for capture challenge hashing.');
        }

        return $key;
    }
}
