<?php

namespace Database\Seeders;

use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HandoverSeeder extends Seeder
{
    public function run(): void
    {
        $jessa = User::where('email', 'jessa@example.com')->first();
        if (!$jessa) {
            $jessa = User::create([
                'first_name' => 'Jessa',
                'last_name' => 'Dela Cruz',
                'email' => 'jessa@example.com',
                'password' => Hash::make('ChangeMe123!'),
                'role' => \App\Enums\Role::Adopter->value,
                'is_active' => true,
            ]);
        }

        $branch = \App\Models\Branch::first();
        $branchId = $branch ? $branch->id : 1;

        // Ensure Pet Icy exists
        $icy = Pet::firstOrCreate(['name' => 'Icy'], [
            'species' => \App\Enums\Species::Dog,
            'breed' => 'Samoyed Mix',
            'age' => 1,
            'sex' => 'Female',
            'intake_date' => now()->subMonths(3),
            'health_status' => 'Healthy',
            'availability_status' => \App\Enums\AvailabilityStatus::Available,
            'branch_id' => $branchId,
            'is_archived' => false,
            'version' => 1,
        ]);

        $mimi = Pet::firstOrCreate(['name' => 'Mimi'], [
            'species' => \App\Enums\Species::Cat,
            'breed' => 'Persian Mix',
            'age' => 2,
            'sex' => 'Female',
            'health_status' => 'Healthy',
            'availability_status' => \App\Enums\AvailabilityStatus::SoftReserved,
            'branch_id' => $branchId,
            'intake_date' => now()->subMonths(2),
            'is_archived' => false,
            'version' => 1,
        ]);

        $bruno = Pet::firstOrCreate(['name' => 'Bruno'], [
            'species' => \App\Enums\Species::Dog,
            'breed' => 'Mixed Breed',
            'age' => 3,
            'sex' => 'Male',
            'health_status' => 'Healthy',
            'availability_status' => \App\Enums\AvailabilityStatus::SoftReserved,
            'branch_id' => $branchId,
            'intake_date' => now()->subMonths(4),
            'is_archived' => false,
            'version' => 1,
        ]);

        $mochi = Pet::where('name', 'Mochi')->first();
        if (!$mochi) {
            $mochi = Pet::create([
                'name' => 'Mochi',
                'species' => \App\Enums\Species::Cat,
                'breed' => 'Persian',
                'age' => 1,
                'sex' => 'Female',
                'health_status' => 'Healthy',
                'availability_status' => \App\Enums\AvailabilityStatus::Adopted,
                'branch_id' => $branchId,
                'intake_date' => now()->subMonths(5),
                'is_archived' => false,
                'version' => 1,
            ]);
        }

        // 1. Icy - Needs Handover
        $h1 = Handover::updateOrCreate(['code' => 'hv-2041'], [
            'pet_id' => $icy->id,
            'user_id' => null,
            'adopter_name' => 'Diego Cruzado',
            'adopter_phone' => '+63 917 442 8810',
            'adopter_email' => 'diego.cruzado@email.com',
            'adopter_address' => '14 Sumulong Hwy, Marikina',
            'adopter_distance' => '18 km from shelter',
            'approved_at' => Carbon::parse('2026-09-03 09:20:00'),
            'reopen_count' => 0,
            'history' => [
                ['at' => '2026-09-03T09:20:00', 'label' => 'Application approved', 'actor' => 'Admin Name'],
            ],
            'reminders' => [],
        ]);

        // 2. Mimi - Awaiting Confirmation (assigned to Jessa for live user testing!)
        $proofUrl = 'https://cdn.magicpatterns.com/patterns/generated-images/bc46794c-1ed6-4045-820b-d15d0c947856.jpg';
        $h2 = Handover::updateOrCreate(['code' => 'hv-2036'], [
            'pet_id' => $mimi ? $mimi->id : $icy->id,
            'user_id' => $jessa->id,
            'adopter_name' => 'Josh Oliver',
            'adopter_phone' => '+63 918 220 1174',
            'adopter_email' => 'jessa@example.com', // use Jessa's email so she can view it logged in
            'adopter_address' => 'Unit 7B Katipunan Ave, Quezon City',
            'adopter_distance' => '9 km from shelter',
            'approved_at' => Carbon::parse('2026-08-28 14:05:00'),
            'release_method' => 'delivery',
            'release_date' => '2026-09-01',
            'release_time' => '10:30',
            'staff_name' => 'Rina Sarmiento',
            'courier' => 'Lalamove',
            'tracking_number' => 'LLM-77341902',
            'proof_name' => 'mimi-handover.jpg',
            'proof_url' => $proofUrl,
            'released_at' => Carbon::parse('2026-09-01 10:34:00'),
            'adopter_outcome' => null,
            'reopen_count' => 0,
            'reminders' => [
                ['at' => '2026-09-03T09:00:00', 'channel' => 'SMS'],
            ],
            'history' => [
                ['at' => '2026-08-28T14:05:00', 'label' => 'Application approved', 'actor' => 'Admin Name'],
                ['at' => '2026-09-01T10:34:00', 'label' => 'Marked as released via Lalamove', 'actor' => 'Rina Sarmiento'],
                ['at' => '2026-09-03T09:00:00', 'label' => 'Reminder sent to adopter (SMS)', 'actor' => 'System'],
            ],
        ]);

        // Notifications for Mimi
        HandoverNotification::where('handover_id', $h2->id)->delete();
        HandoverNotification::create([
            'handover_id' => $h2->id,
            'user_id' => $jessa->id,
            'kind' => 'prepared',
            'title' => 'Your adoption is being prepared for release',
            'body' => "Shelter staff have scheduled Mimi's Lalamove delivery for Sep 1, 10:30 AM. We'll message you the moment Mimi is on the way.",
            'channels' => ['In-app', 'Email'],
            'action_label' => 'View handover details',
            'action_url' => "/adopter/{$h2->id}",
            'read' => true,
            'created_at' => Carbon::parse('2026-09-01 10:05:00'),
        ]);
        HandoverNotification::create([
            'handover_id' => $h2->id,
            'user_id' => $jessa->id,
            'kind' => 'released',
            'title' => 'Mimi has been released via Lalamove delivery',
            'body' => 'Please confirm once Mimi is with you — your adoption is not complete until you confirm receipt.',
            'channels' => ['In-app', 'Email', 'SMS'],
            'action_label' => 'Confirm receipt',
            'action_url' => "/confirm/{$h2->id}",
            'read' => false,
            'created_at' => Carbon::parse('2026-09-01 10:34:00'),
        ]);
        HandoverNotification::create([
            'handover_id' => $h2->id,
            'user_id' => $jessa->id,
            'kind' => 'reminder',
            'title' => 'Reminder: Please confirm receipt of Mimi',
            'body' => 'It has been 2 days since Mimi was released. Confirm receipt so your adoption can be completed.',
            'channels' => ['In-app', 'SMS'],
            'action_label' => 'Confirm receipt',
            'action_url' => "/confirm/{$h2->id}",
            'read' => false,
            'created_at' => Carbon::parse('2026-09-03 09:00:00'),
        ]);

        // 3. Bruno - Delivery Failed / Issue Logged
        $h3 = Handover::updateOrCreate(['code' => 'hv-2029'], [
            'pet_id' => $bruno ? $bruno->id : $icy->id,
            'user_id' => null,
            'adopter_name' => 'Stephanie Rasonado',
            'adopter_phone' => '+63 906 553 9021',
            'adopter_email' => 'steph.rasonado@email.com',
            'adopter_address' => '221 Jupiter St, Makati',
            'adopter_distance' => '12 km from shelter',
            'approved_at' => Carbon::parse('2026-08-24 11:40:00'),
            'release_method' => 'delivery',
            'release_date' => '2026-08-30',
            'release_time' => '15:00',
            'staff_name' => 'Marco Uy',
            'courier' => 'Grab Pet Transport',
            'tracking_number' => 'GRB-4410-88',
            'proof_name' => 'bruno-crate-loaded.jpg',
            'proof_url' => $proofUrl,
            'released_at' => Carbon::parse('2026-08-30 15:12:00'),
            'adopter_outcome' => 'not_received',
            'adopter_confirmed_at' => Carbon::parse('2026-08-31 08:15:00'),
            'adopter_note' => 'Courier could not reach the building. Nothing was delivered to me.',
            'reopen_count' => 0,
            'reminders' => [],
            'history' => [
                ['at' => '2026-08-24T11:40:00', 'label' => 'Application approved', 'actor' => 'Admin Name'],
                ['at' => '2026-08-30T15:12:00', 'label' => 'Marked as released via Grab Pet Transport', 'actor' => 'Marco Uy'],
                ['at' => '2026-08-31T08:15:00', 'label' => 'Adopter reported pet not received', 'actor' => 'Stephanie Rasonado'],
            ],
        ]);

        HandoverNotification::where('handover_id', $h3->id)->delete();
        HandoverNotification::create([
            'handover_id' => $h3->id,
            'user_id' => null,
            'kind' => 'issue_logged',
            'title' => "We're sorting out Bruno's handover",
            'body' => 'Your report was sent to the shelter. A staff member will contact you at +63 906 553 9021 to arrange a new handover.',
            'channels' => ['In-app', 'SMS'],
            'action_label' => 'View handover status',
            'action_url' => "/adopter/{$h3->id}",
            'read' => true,
            'created_at' => Carbon::parse('2026-08-31 08:15:00'),
        ]);

        // 4. Mochi - Monitoring Active
        $h4 = Handover::updateOrCreate(['code' => 'hv-2018'], [
            'pet_id' => $mochi ? $mochi->id : $icy->id,
            'user_id' => null,
            'adopter_name' => 'Juan Dela Cruz',
            'adopter_phone' => '+63 915 771 3320',
            'adopter_email' => 'juan.delacruz@email.com',
            'adopter_address' => '38 Ortigas Ave, Pasig',
            'adopter_distance' => '6 km from shelter',
            'approved_at' => Carbon::parse('2026-08-18 10:00:00'),
            'release_method' => 'pickup',
            'release_date' => '2026-08-21',
            'release_time' => '09:15',
            'staff_name' => 'Rina Sarmiento',
            'courier' => null,
            'tracking_number' => null,
            'proof_name' => 'mochi-pickup.jpg',
            'proof_url' => $proofUrl,
            'released_at' => Carbon::parse('2026-08-21 09:18:00'),
            'adopter_outcome' => 'received',
            'adopter_confirmed_at' => Carbon::parse('2026-08-21 12:02:00'),
            'adopter_note' => 'Picked her up this morning, she is settling in well.',
            'reopen_count' => 0,
            'reminders' => [],
            'history' => [
                ['at' => '2026-08-18T10:00:00', 'label' => 'Application approved', 'actor' => 'Admin Name'],
                ['at' => '2026-08-21T09:18:00', 'label' => 'Marked as released at shelter', 'actor' => 'Rina Sarmiento'],
                ['at' => '2026-08-21T12:02:00', 'label' => 'Adopter confirmed receipt', 'actor' => 'Juan Dela Cruz'],
                ['at' => '2026-08-21T12:02:00', 'label' => 'Post-adoption monitoring activated', 'actor' => 'System'],
            ],
        ]);

        HandoverNotification::where('handover_id', $h4->id)->delete();
        HandoverNotification::create([
            'handover_id' => $h4->id,
            'user_id' => null,
            'kind' => 'completed',
            'title' => 'Welcome home, Mochi!',
            'body' => 'Thank you for confirming receipt. Your adoption is complete and your first post-adoption check-in has been scheduled.',
            'channels' => ['In-app', 'Email'],
            'action_label' => 'View my check-ins',
            'action_url' => '/post-adoption/check-ins',
            'read' => true,
            'created_at' => Carbon::parse('2026-08-21 12:02:00'),
        ]);
    }
}
