<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Services\KnnRecommendationService;
use App\Support\ResearchMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ResearchScenarioController extends Controller
{
    public function index(Request $request)
    {
        $placements = DB::table('research_placements as p')->join('pets', 'pets.id', '=', 'p.pet_id')
            ->where('p.user_id', $request->user()->id)->orderByDesc('p.id')->get(['p.*', 'pets.name']);
        $submitted = DB::table('research_checkins')->whereIn('placement_id', $placements->pluck('id'))->get()->groupBy('placement_id');

        return view('research.scenarios', compact('placements', 'submitted'));
    }

    public function preview(Request $request, Pet $pet, KnnRecommendationService $matcher)
    {
        $this->catalog($pet);
        $match = $matcher->calculateMatch($request->user()->adopterProfile, $pet);
        if ($match->exclusionReason === 'ADOPTER_PROFILE_INCOMPLETE') {
            return redirect()->route('recommendation.intake', ['return_pet' => $pet->id]);
        }

        return view('research.apply', compact('pet', 'match'));
    }

    private function catalog(Pet $pet): void
    {
        abort_unless(DB::table('research_catalog')->where('pet_id', $pet->id)->exists(), 404);
    }

    public function apply(Request $request, Pet $pet, KnnRecommendationService $matcher)
    {
        DB::transaction(function () use ($request, $pet, $matcher) {
            $user = $request->user();
            abort_unless(DB::table('research_participants')->where('user_id', $user->id)->lockForUpdate()->first(), 403);
            $this->catalog($pet);
            if (DB::table('research_placements')->where(['user_id' => $user->id, 'pet_id' => $pet->id])->exists()) {
                return;
            }
            $match = $matcher->calculateMatch($user->adopterProfile, $pet);
            abort_unless($match->eligible && $match->compatibilityScore !== null, 422, 'This simulated application is not eligible for auto-approval.');
            $now = now();
            DB::table('research_placements')->insert(['user_id' => $user->id, 'pet_id' => $pet->id, 'compatibility_score' => $match->compatibilityScore,
                'algorithm_version' => $match->algorithmVersion, 'approved_at' => $now, 'released_at' => $now]);
        });

        return to_route('research.index');
    }

    public function receive(Request $request, int $placement)
    {
        $owned = DB::table('research_placements')->where(['id' => $placement, 'user_id' => $request->user()->id]);
        abort_unless((clone $owned)->whereNotNull('released_at')->exists(), 404);
        $owned->whereNull('received_at')->update(['received_at' => now()]);

        return to_route('research.index');
    }

    public function checkin(Request $request, int $placement)
    {
        $data = $request->validate(['milestone' => ['required', Rule::in(ResearchMode::MILESTONES)],
            'appetite' => ['required', Rule::in(['normal', 'reduced'])], 'adjustment' => ['required', Rule::in(['settling', 'struggling'])]]);
        DB::transaction(function () use ($request, $placement, $data) {
            $p = DB::table('research_placements')->where(['id' => $placement, 'user_id' => $request->user()->id])->lockForUpdate()->first();
            abort_unless($p, 404);
            abort_unless($p->received_at, 409, 'Confirm simulated receipt before recording a check-in.');
            $logs = DB::table('research_checkins')->where('placement_id', $placement);
            if ((clone $logs)->where('milestone', $data['milestone'])->exists()) {
                return;
            }
            abort_unless((ResearchMode::MILESTONES[$logs->count()] ?? null) === $data['milestone'], 409, 'Complete the earlier simulated check-in first.');
            $logs->insert(['placement_id' => $placement, 'milestone' => $data['milestone'], 'answers' => json_encode(array_intersect_key($data, array_flip(['appetite', 'adjustment'])), JSON_THROW_ON_ERROR), 'submitted_at' => now()]);
        });

        return to_route('research.index');
    }
}
