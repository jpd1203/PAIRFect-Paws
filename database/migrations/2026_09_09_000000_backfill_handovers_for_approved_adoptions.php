<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give approved adoptions that predate the handover module the same linked
     * handover records created by the current approval workflow.
     */
    public function up(): void
    {
        $applications = DB::table('adoption_applications as applications')
            ->leftJoin('users as users', 'users.id', '=', 'applications.user_id')
            ->leftJoin('handovers as handovers', 'handovers.application_id', '=', 'applications.id')
            ->where('applications.status', 'Approved')
            ->whereNull('handovers.id')
            ->select([
                'applications.id',
                'applications.pet_id',
                'applications.user_id',
                'applications.applicant_first_name',
                'applications.applicant_last_name',
                'applications.applicant_email',
                'applications.applicant_phone',
                'applications.applicant_street_address',
                'applications.applicant_barangay',
                'applications.applicant_city_municipality',
                'applications.applicant_province',
                'applications.applicant_region',
                'applications.applicant_zip_code',
                'applications.adopted_at',
                'applications.queue_closed_at',
                'applications.created_at',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name',
                'users.email as user_email',
            ])
            ->orderBy('applications.id')
            ->get();

        foreach ($applications as $application) {
            $approvedAt = $application->adopted_at
                ?? $application->queue_closed_at
                ?? $application->created_at
                ?? now()->toDateTimeString();
            $adopterName = trim(implode(' ', array_filter([
                $application->applicant_first_name ?: $application->user_first_name,
                $application->applicant_last_name ?: $application->user_last_name,
            ])));
            $address = implode(', ', array_filter([
                $application->applicant_street_address,
                $application->applicant_barangay,
                $application->applicant_city_municipality,
                $application->applicant_province,
                $application->applicant_region,
                $application->applicant_zip_code,
            ]));
            $now = now();

            $handoverId = DB::table('handovers')->insertGetId([
                'code' => 'HV-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
                'application_id' => $application->id,
                'pet_id' => $application->pet_id,
                'user_id' => $application->user_id,
                'adopter_name' => $adopterName !== '' ? $adopterName : null,
                'adopter_phone' => $application->applicant_phone,
                'adopter_email' => $application->applicant_email ?: $application->user_email,
                'adopter_address' => $address !== '' ? $address : null,
                'approved_at' => $approvedAt,
                'history' => json_encode([[
                    'at' => $approvedAt,
                    'label' => 'Application approved',
                    'actor' => 'System',
                ]]),
                'reminders' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('handover_notifications')->insert([
                'handover_id' => $handoverId,
                'user_id' => $application->user_id,
                'kind' => 'prepared',
                'title' => 'Your adoption has been approved',
                'body' => 'Your adoption has been approved. Shelter staff will contact you when the handover is scheduled.',
                'channels' => json_encode(['In-app']),
                'action_label' => 'View handover details',
                'action_url' => '/adopter/'.$handoverId,
                'read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // This data migration intentionally preserves the resulting adoption
        // history when rolling back the codebase.
    }
};
