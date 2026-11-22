<?php

namespace Tests\Concerns;

use App\Models\ResearchPlacement;
use App\Services\VideoDurationProbe;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

trait SubmitsResearchCheckIns
{
    protected function researchVideo(string $unique): UploadedFile
    {
        // Container signature fixture; real executable/recording checked separately in browser QA.
        $header = hex2bin('1a45dfa39f4286810142f7810142f2810442f381084282847765626d4287810242858102');

        return UploadedFile::fake()->createWithContent('practice.webm', $header.$unique)->mimeType('video/webm');
    }

    protected function probeResearchVideo(float $seconds = 3.0): void
    {
        config()->set('post_adoption.capture.ffprobe_path', PHP_BINARY);
        $this->app->instance(VideoDurationProbe::class, new VideoDurationProbe(
            fn () => new Process([PHP_BINARY, '-r', 'echo '.var_export(json_encode([
                'streams' => [['codec_type' => 'video']], 'format' => ['duration' => $seconds],
            ], JSON_THROW_ON_ERROR), true).';']),
        ));
    }

    protected function researchCapture(ResearchPlacement $placement, string $milestone, string $videoKey): array
    {
        $challenge = $this->postJson(route('research.capture-challenge', [$placement, $milestone]))
            ->assertCreated()->assertJsonPath('expires_in_seconds', 300)->json();

        return [
            'capture_challenge_id' => $challenge['challenge_id'], 'capture_challenge_token' => $challenge['challenge_token'],
            'camera_captured_at' => now()->toISOString(), 'recording_duration_ms' => 3000,
            'video' => $this->researchVideo($videoKey),
        ];
    }
}
