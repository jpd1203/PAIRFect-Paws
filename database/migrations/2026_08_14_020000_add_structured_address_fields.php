<?php

use App\Support\PhilippineAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region')->nullable()->after('branch_id');
            $table->string('province')->nullable()->after('region');
            $table->string('city_municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('city_municipality');
            $table->string('street_address', 1000)->nullable()->after('barangay');
            $table->string('zip_code')->nullable()->after('street_address');
        });

        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->string('applicant_region')->nullable()->after('applicant_phone');
            $table->string('applicant_province')->nullable()->after('applicant_region');
            $table->string('applicant_city_municipality')->nullable()->after('applicant_province');
            $table->string('applicant_barangay')->nullable()->after('applicant_city_municipality');
            $table->string('applicant_street_address', 1000)->nullable()->after('applicant_barangay');
            $table->string('applicant_zip_code')->nullable()->after('applicant_street_address');
        });

        DB::table('adoption_applications')
            ->whereNotNull('applicant_address')
            ->update(['applicant_street_address' => DB::raw('applicant_address')]);

        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropColumn('applicant_address');
        });
    }

    public function down(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->text('applicant_address')->nullable()->after('applicant_phone');
        });

        DB::table('adoption_applications')
            ->select([
                'id',
                'applicant_region',
                'applicant_province',
                'applicant_city_municipality',
                'applicant_barangay',
                'applicant_street_address',
                'applicant_zip_code',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    DB::table('adoption_applications')
                        ->where('id', $application->id)
                        ->update([
                            'applicant_address' => PhilippineAddress::format([
                                'region' => $application->applicant_region,
                                'province' => $application->applicant_province,
                                'city_municipality' => $application->applicant_city_municipality,
                                'barangay' => $application->applicant_barangay,
                                'street_address' => $application->applicant_street_address,
                                'zip_code' => $application->applicant_zip_code,
                            ]),
                        ]);
                }
            });

        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_region',
                'applicant_province',
                'applicant_city_municipality',
                'applicant_barangay',
                'applicant_street_address',
                'applicant_zip_code',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'region',
                'province',
                'city_municipality',
                'barangay',
                'street_address',
                'zip_code',
            ]);
        });
    }
};
