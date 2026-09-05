<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Verify only the two built-in staff accounts whose placeholder domain
     * does not have a usable mailbox. All other staff must still verify.
     */
    public function up(): void
    {
        $verifiedAt = now();

        foreach ([
            'admin@pairfectpaws.com' => 'Administrator',
            'volunteer@pairfectpaws.com' => 'Volunteer',
        ] as $email => $role) {
            DB::table('users')
                ->where('email', $email)
                ->where('role', $role)
                ->whereNull('email_verified_at')
                ->update([
                    'email_verified_at' => $verifiedAt,
                    'updated_at' => $verifiedAt,
                ]);
        }
    }

    /**
     * Do not revoke verification if this data migration is rolled back.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
