<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ResearchWelfareCaseService;
use App\Support\ResearchMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ResearchWelfareController extends Controller
{
    public function flag(Request $request, int $report, ResearchWelfareCaseService $cases): RedirectResponse
    {
        abort_unless(ResearchMode::ready(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $flagged = $cases->flag($report, $request->user(), $data['reason']);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $flagged ? 'The case was flagged for staff review.' : 'This flag reason is already recorded.',
        ]);
    }

    public function resolve(Request $request, int $report, ResearchWelfareCaseService $cases): RedirectResponse
    {
        abort_unless(ResearchMode::ready(), 404);
        $data = $request->validate($cases->resolutionRules());
        $changed = $cases->resolve($report, $request->user(), $data);

        return back()->with('toast', [
            'type' => $changed ? 'success' : 'error',
            'message' => ! $changed ? 'This monitoring case does not have an unresolved change to record.'
                : ($data['resolution_outcome'] === 'follow_up_required'
                    ? 'Follow-up recorded. The case remains open for staff action.'
                    : 'The welfare outcome was recorded successfully.'),
        ]);
    }
}
