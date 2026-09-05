<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundRecord;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundController extends Controller
{
    public function index()
    {
        $records = FundRecord::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $totalDonations = FundRecord::where('transaction_type', 'Donation')->sum('amount');
        $totalSpent = FundRecord::where('transaction_type', 'Expense')->sum('amount');
        $availableFunds = (float) $totalDonations - (float) $totalSpent;

        return view('admin.funds.index', compact(
            'records',
            'totalDonations',
            'totalSpent',
            'availableFunds',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recorded_date' => 'required|date',
            'entry_type' => 'required|in:donation,expense',
            'activity' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $fund = new FundRecord([
            'user_id' => Auth::id(),
            'transaction_type' => $validated['entry_type'] === 'donation' ? 'Donation' : 'Expense',
            'amount' => $validated['amount'],
            'source_or_destination' => $validated['activity'],
            'description' => null,
            'is_public' => true,
        ]);

        $fund->created_at = $validated['recorded_date'].' '.now()->format('H:i:s');
        $fund->save();

        return back()->with('success', 'Fund record saved.');
    }

    public function update(Request $request, FundRecord $fund): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_public' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'fund' => 'Provide an amount, description, or public visibility value to update.',
            ]);
        }

        DB::transaction(function () use ($fund, $validated): void {
            $fund->update($validated);

            AuditLogService::log(
                Auth::id(),
                'Fund Record Updated',
                'FundRecord',
                $fund->id,
                'Updated fields: '.implode(', ', array_keys($validated)).'.',
            );
        });

        return back()->with('success', 'Fund record updated.');
    }

    public function destroy(FundRecord $fund): RedirectResponse
    {
        $fundId = $fund->id;

        DB::transaction(function () use ($fund, $fundId): void {
            AuditLogService::log(
                Auth::id(),
                'Fund Record Deleted',
                'FundRecord',
                $fundId,
                'A mistaken financial ledger record was deleted.',
            );

            $fund->delete();
        });

        return back()->with('success', 'Fund record deleted.');
    }
}
