<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FundRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ManageFundsController extends Controller
{
    public function index()
    {
        $records = FundRecord::orderByDesc('recorded_date')->orderByDesc('id')->get();

        $totalDonations = $records->sum('donation_added');
        $totalSpent = $records->sum('shelter_spent');

        return view('admin.funds.index', compact('records', 'totalDonations', 'totalSpent'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'recorded_date' => ['required', 'date'],
            'entry_type' => ['required', Rule::in(['donation', 'expense'])],
            'activity' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        FundRecord::create([
            'recorded_date' => $data['recorded_date'],
            'activity' => $data['activity'],
            'donation_added' => $data['entry_type'] === 'donation' ? $data['amount'] : null,
            'shelter_spent' => $data['entry_type'] === 'expense' ? $data['amount'] : null,
            'recorded_by' => Auth::user()->full_name,
        ]);

        AuditLog::record(Auth::user(), "recorded a fund entry: {$data['activity']} (₱{$data['amount']})");

        return back()->with('toast', ['type' => 'success', 'message' => 'Fund record added.']);
    }
}
