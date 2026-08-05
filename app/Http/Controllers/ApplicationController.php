<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Support\ApplicationOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function index()
    {
        // Adopters may only ever see their own most recent application
        $application = AdoptionApplication::with('pet')
            ->where('user_id', Auth::id())
            ->latest()
            ->first();

        return view('application.index', ['application' => $application]);
    }

    public function apply(Pet $pet)
    {
        abort_if($pet->status !== 'Available', 404);

        return view('application.apply', [
            'pet' => $pet,
            'options' => ApplicationOptions::class,
        ]);
    }

    public function submit(StoreApplicationRequest $request)
    {
        $data = $request->validated();
        $pet = Pet::findOrFail($data['pet_id']);
        abort_if($pet->status !== 'Available', 404);

        // Store the upload OUTSIDE the public webroot's guessable path;
        // served later only to staff/owner via a signed/authorized route.
        $path = $request->file('document')->store('applications', 'private');

        AdoptionApplication::create([
            'user_id' => Auth::id(),
            'pet_id' => $pet->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'address' => $data['address'],
            'housing_type' => $data['housing_type'],
            'household_composition' => $data['other_pets'] ?? $data['housing_type'],
            'monthly_income_range' => $data['monthly_income_range'],
            'prior_pet_experience' => $data['prior_pet_experience'],
            'document_path' => $path,
            'agreed_to_animal_welfare_act' => true,
            'status' => AdoptionApplication::STATUS_PENDING,
        ]);

        return redirect()
            ->route('application.index')
            ->with('toast', ['type' => 'success', 'message' => 'Application submitted! We will review it shortly.']);
    }
}
