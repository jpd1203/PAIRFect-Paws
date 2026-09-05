<?php

namespace App\Services;

use App\Models\FundRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DonationProcessingService
{
    /**
     * Process a donation through PayMongo.
     * Returns true if successful.
     */
    public function processDonation(float $amount, string $description, ?int $userId = null): bool
    {
        $secretKey = env('PAYMONGO_SECRET_KEY');

        // Mock success if no API key is provided
        if (! $secretKey) {
            Log::info("DonationProcessingService: No PayMongo key provided, mocking success for amount $amount.");

            FundRecord::create([
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_type' => 'Donation',
                'source_or_destination' => 'PayMongo (Mock)',
                'description' => $description,
                'is_public' => true,
            ]);

            return true;
        }

        try {
            // Amount is in centavos for PayMongo
            $centavos = (int) ($amount * 100);

            // This is a simplified PayMongo Links creation request.
            // In a real app we'd redirect the user to the link checkout_url.
            $response = Http::withBasicAuth($secretKey, '')
                ->post('https://api.paymongo.com/v1/links', [
                    'data' => [
                        'attributes' => [
                            'amount' => $centavos,
                            'description' => $description,
                            'remarks' => 'Donation via PAIRfect Paws',
                        ],
                    ],
                ]);

            if ($response->successful()) {
                // To keep it simple for this prototype, we record the fund immediately.
                // In a production app, we would use a webhook to record it upon successful payment.
                FundRecord::create([
                    'user_id' => $userId,
                    'amount' => $amount,
                    'transaction_type' => 'Donation',
                    'source_or_destination' => 'PayMongo',
                    'description' => $description,
                    'is_public' => true,
                ]);

                return true;
            }

            Log::error('DonationProcessingService: PayMongo API failed.', ['response' => $response->json()]);

            return false;

        } catch (\Exception $e) {
            Log::error('DonationProcessingService Error: '.$e->getMessage());

            return false;
        }
    }
}
