<?php

namespace App\Http\Controllers;

use App\Services\DonationProcessingService;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function store(Request $request, DonationProcessingService $donationService)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:50',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $description = 'Donation from '.($validated['name'] ?? 'Anonymous');

        $success = $donationService->processDonation($validated['amount'], $description, auth()->id());

        if ($success) {
            return back()->with('success', 'Thank you for your donation!');
        }

        return back()->withErrors(['amount' => 'There was an issue processing your donation. Please try again.']);
    }
}
