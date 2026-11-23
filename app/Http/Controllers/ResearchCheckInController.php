<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\ResearchCheckIn;
use App\Models\ResearchPlacement;
use App\Models\User;
use App\Services\PostAdoptionCaptureChallengeService;
use App\Services\ResearchPrivateMedia;
use App\Services\ResearchWelfareCaseService;
use App\Services\VideoDurationProbe;
use App\Support\ManilaTime;
use App\Support\ResearchAdminRecords;
use App\Support\ResearchMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ResearchCheckInController extends Controller
{
    public function show(Request $request, ResearchPlacement $placement)
    {
        $this->authorizeReport($placement, $request->user());
        $milestone = $this->nextMilestone($placement);
        if (! $milestone) {
            return to_route('monitoring.my-checkins');
        }

        return view('monitoring.create', ['researchPlacement' => $placement, 'researchMilestone' => $milestone]);
    }

    public function modal(Request $request, int $report, ResearchAdminRecords $records)
    {
        $record = ResearchCheckIn::query()->afterReceipt()
            ->whereHas('placement', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['placement.pet' => fn ($query) => $query->withoutGlobalScope('notArchived')])
            ->findOrFail($report);

        return view('monitoring._report-view-modal-content', ['report' => $records->adopterReport($record)]);
    }

    public function followUp(Request $request, int $report, ResearchWelfareCaseService $cases)
    {
        $data = $request->validate(['follow_up_notes' => ['required', 'string', 'max:2000']]);
        $record = $cases->followUp($report, $request->user(), $data['follow_up_notes']);
        $case = $record->welfare_case;
        $message = 'Follow-up update submitted successfully.';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true, 'message' => $message, 'follow_up_notes' => $case['follow_up_notes'],
                'follow_up_submitted_at' => ManilaTime::parseAndFormat($case['follow_up_submitted_at'], 'M j, Y g:i A'),
            ]);
        }

        return back()->with('success', $message);
    }

    public function challenge(Request $request, ResearchPlacement $placement, string $milestone, PostAdoptionCaptureChallengeService $challenges)
    {
        $this->authorizeReport($placement, $request->user());
        $binding = $challenges->sessionBinding($request);
        $issued = DB::transaction(function () use ($placement, $request, $milestone, $challenges, $binding) {
            $locked = ResearchPlacement::lockForUpdate()->findOrFail($placement->id);
            $this->authorizeReport($locked, $request->user());
            $this->requireNext($locked, $milestone);

            return $challenges->issueResearchLocked($locked, $request->user(), $milestone, $binding);
        }, 3);

        return response()->json([
            'challenge_id' => $issued['record']->id, 'challenge_token' => $issued['token'],
            'expires_at' => $issued['record']->expires_at->toIso8601String(),
            'expires_in_seconds' => $issued['expires_in_seconds'],
            'required_duration_seconds' => (float) config('post_adoption.capture.required_video_duration_seconds', 3),
            'duration_tolerance_seconds' => (float) config('post_adoption.capture.video_duration_tolerance_seconds', .75),
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, ResearchPlacement $placement, PostAdoptionCaptureChallengeService $challenges, VideoDurationProbe $probe, ResearchPrivateMedia $media, ResearchWelfareCaseService $cases)
    {
        $this->authorizeReport($placement, $request->user());
        $maximumVideoKilobytes = max(1, (int) config('post_adoption.maximum_video_kilobytes', 12288));
        $required = max(1, (int) round(1000 * config('post_adoption.capture.required_video_duration_seconds', 3)));
        $tolerance = max(0, (int) round(1000 * config('post_adoption.capture.video_duration_tolerance_seconds', .75)));
        $minimum = max(1, $required - $tolerance);
        $maximum = $required + $tolerance;
        $data = $request->validate([
            'milestone' => ['required', Rule::in(ResearchMode::MILESTONES)],
            'pet_current_status' => ['required', Rule::in(['Excellent', 'Good', 'Fair', 'Poor'])],
            'living_conditions' => ['required', Rule::in(['Indoor Only', 'Outdoor Only', 'Indoor and Outdoor'])],
            'eating_habits' => ['required', Rule::in(['Normal', 'Reduced Appetite', 'Not Eating'])],
            'behavioral_observations' => ['required', Rule::in(['Well-adjusted', 'Still adjusting', 'Anxious/Stressed', 'Aggressive'])],
            'vet_visit_details' => ['nullable', 'string', 'max:2000'],
            'concerns' => ['nullable', 'string', 'max:2000'],
            'camera_captured_at' => ['required', 'date'],
            'recording_duration_ms' => ['required', 'integer', "min:{$minimum}", "max:{$maximum}"],
            'capture_challenge_id' => ['required', 'uuid'],
            'capture_challenge_token' => ['required', 'string', 'size:64'],
            'video' => ['required', 'file', 'mimetypes:video/webm,video/mp4', "max:{$maximumVideoKilobytes}"],
        ]);
        $this->requireNext($placement, $data['milestone']);
        $captured = CarbonImmutable::parse($data['camera_captured_at'])->utc();
        $now = CarbonImmutable::now('UTC');
        $age = max(1, (int) config('post_adoption.capture.maximum_capture_age_seconds', 300));
        $skew = max(0, (int) config('post_adoption.capture.client_clock_skew_seconds', 120));
        if ($captured->lt($now->subSeconds($age)) || $captured->gt($now->addSeconds($skew))) {
            throw ValidationException::withMessages(['camera_captured_at' => 'The camera capture expired. Record a new live video.']);
        }
        $binding = $challenges->sessionBinding($request);
        $user = $request->user();
        $args = [$data['capture_challenge_id'], $data['capture_challenge_token'], $placement, $user, $data['milestone'], $binding, $captured];
        $challenges->validateResearch(...$args);
        $video = $data['video'];
        $temporary = $video->getRealPath();
        abort_unless($temporary, 422, 'The recorded video could not be read.');
        $mime = $video->getMimeType();
        $inspection = $probe->inspect($temporary, $mime);
        abort_if($inspection['status'] === 'probe_unavailable', 503, 'Video verification is temporarily unavailable. Please try again later.');
        $duration = $inspection['duration_ms'];
        if ($inspection['status'] !== 'verified' || $duration === null || $duration < $minimum || $duration > $maximum) {
            throw ValidationException::withMessages(['video' => 'The recording could not be verified as a valid three-second video. Please record it again.']);
        }
        $hash = hash_file('sha256', $temporary);
        abort_unless($hash, 503, 'Video verification is temporarily unavailable.');
        if (DB::table('research_checkins')->where('video_sha256', $hash)->exists()) {
            $this->replayed();
        }

        $path = $media->store($video, 'checkins', $placement->id);
        try {
            DB::transaction(function () use ($placement, $user, $challenges, $args, $hash, $path, $mime, $duration, $data, $now, $captured, $cases): void {
                $locked = ResearchPlacement::lockForUpdate()->findOrFail($placement->id);
                $this->authorizeReport($locked, $user);
                $this->requireNext($locked, $data['milestone']);
                $challenge = $challenges->validateResearch(...[...$args, $hash]);
                $answers = Arr::only($data, ['pet_current_status', 'living_conditions', 'eating_habits', 'behavioral_observations', 'vet_visit_details', 'concerns']);
                $answers['_verification'] = [
                    'method' => 'Practice camera capture — not welfare evidence',
                    'capture_challenge_id' => $challenge->id,
                    'challenge_issued_at' => $challenge->created_at->toIso8601String(),
                    'challenge_consumed_at' => $challenge->consumed_at->toIso8601String(),
                    'camera_captured_at' => $captured->toIso8601String(), 'server_received_at' => $now->toIso8601String(),
                    'video_sha256' => $hash, 'video_mime_type' => $mime,
                    'declared_duration_ms' => (int) $data['recording_duration_ms'],
                    'verified_duration_ms' => $duration, 'duration_verification_status' => 'verified',
                ];
                $report = ResearchCheckIn::create([
                    'placement_id' => $locked->id, 'milestone' => $data['milestone'],
                    'answers' => $answers, 'submitted_at' => now(),
                    'video_path' => $path, 'video_sha256' => $hash, 'video_mime_type' => $mime,
                ]);
                $cases->evaluateLocked($report);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $media->forget($path);
            $this->replayed();
        } catch (Throwable $exception) {
            $media->forget($path);
            throw $exception;
        }
        $redirect = route('monitoring.my-checkins');

        return $request->expectsJson() ? response()->json(['redirect' => $redirect]) : redirect($redirect);
    }

    private function authorizeReport(ResearchPlacement $placement, User $user): void
    {
        abort_unless((int) $placement->user_id === (int) $user->id, 404);
        abort_unless($placement->status === ApplicationStatus::Approved && $placement->received_at, 409, 'Confirm receipt before recording a check-in.');
    }

    private function nextMilestone(ResearchPlacement $placement): ?string
    {
        if ($placement->hasScenarioReturn()) {
            return null;
        }
        $submitted = DB::table('research_checkins')->where('placement_id', $placement->id)->pluck('milestone')->all();

        return collect(ResearchMode::MILESTONES)->first(fn ($milestone) => ! in_array($milestone, $submitted, true));
    }

    private function requireNext(ResearchPlacement $placement, string $milestone): void
    {
        abort_unless($this->nextMilestone($placement) === $milestone, 409, 'Complete the next available check-in.');
    }

    private function replayed(): never
    {
        throw ValidationException::withMessages(['video' => 'This video has already been used for a check-in. Record a new live video.']);
    }
}
