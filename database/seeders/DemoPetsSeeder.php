<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pet;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoPetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo pets may only be seeded in local or testing environments.');
        }

        Storage::disk('public')->makeDirectory('pets');
        
        // Delete previously added demo pets that started with "Demo Pet"
        Pet::where('name', 'like', 'Demo Pet%')->delete();
        
        $speciesArr = ['Cat', 'Dog'];
        $sexes = ['Male', 'Female'];
        $statuses = ['Available', 'Available', 'Available', 'Soft-Reserved', 'Adopted'];
        $breeds = [
            'Cat' => ['Persian', 'Siamese', 'Maine Coon', 'Domestic Shorthair', 'Ragdoll'],
            'Dog' => ['Golden Retriever', 'Labrador Retriever', 'German Shepherd', 'Bulldog', 'Beagle', 'Mixed Breed']
        ];
        
        $catNames = ['Luna', 'Milo', 'Oliver', 'Leo', 'Bella', 'Charlie', 'Max', 'Chloe', 'Lucy', 'Lily', 'Nala', 'Simba'];
        $dogNames = ['Bella', 'Max', 'Charlie', 'Daisy', 'Lucy', 'Buddy', 'Milo', 'Rocky', 'Buster', 'Cooper', 'Duke', 'Bear'];
        
        for ($i = 1; $i <= 12; $i++) {
            $species = $speciesArr[array_rand($speciesArr)];
            $breed = $breeds[$species][array_rand($breeds[$species])];
            $sex = $sexes[array_rand($sexes)];
            
            $name = $species === 'Cat' 
                ? $catNames[array_rand($catNames)] 
                : $dogNames[array_rand($dogNames)];
            
            // Randomly append a letter if name already exists to avoid confusion
            $name .= ' ' . chr(rand(65, 90));
            
            // Grab a real animal image
            $imageUrl = $species === 'Cat'
                ? 'https://cataas.com/cat?width=500&height=500&random=' . rand(1, 10000)
                : 'https://place.dog/500/500?random=' . rand(1, 10000);
            
            $imageContent = null;
            try {
                $context = stream_context_create([
                    'http' => [
                        'ignore_errors' => true,
                        'user_agent' => 'Mozilla/5.0'
                    ]
                ]);
                $imageContent = @file_get_contents($imageUrl, false, $context);
                // Fallback to picsum if cataas/place.dog fails
                if (!$imageContent) {
                    $imageContent = @file_get_contents('https://picsum.photos/500/500?random=' . rand(1, 10000), false, $context);
                }
            } catch (\Exception $e) {
                $imageContent = null;
            }
            
            $filename = 'pets/' . Str::random(10) . '.jpg';
            if ($imageContent) {
                Storage::disk('public')->put($filename, $imageContent);
            } else {
                $filename = null;
            }

            Pet::create([
                'name' => $name,
                'species' => $species,
                'breed' => $breed,
                'age' => rand(2, 60),
                'sex' => $sex,
                'health_status' => 'Healthy',
                'description' => "Meet $name! A friendly $breed who was brought into our care and has been winning the hearts of all shelter volunteers. Friendly, playful, and ready for a warm forever home.",
                'behavioral_notes' => 'A sweet and loving companion looking for a forever home.',
                'availability_status' => $statuses[array_rand($statuses)],
                'intake_date' => now()->subDays(rand(1, 30)),
                'photo_path' => $filename,
                'version' => 1,
                'is_archived' => false,
            ]);
            
            $this->command->info("Added $name ($species)");
        }
    }
}
