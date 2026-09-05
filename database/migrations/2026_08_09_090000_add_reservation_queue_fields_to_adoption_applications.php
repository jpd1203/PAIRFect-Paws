<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->boolean('is_primary_candidate')->default(false)->after('conducted_by')->index();
            $table->timestamp('queue_promoted_at')->nullable()->after('is_primary_candidate');
            $table->timestamp('admin_review_flagged_at')->nullable()->after('queue_promoted_at')->index();
            $table->timestamp('queue_closed_at')->nullable()->after('admin_review_flagged_at');
            $table->text('override_reason')->nullable()->after('queue_closed_at');
            $table->index(['pet_id', 'status', 'created_at'], 'applications_pet_queue_index');
        });

        // Preserve existing in-flight interviews when this feature is introduced.
        $active = DB::table('adoption_applications')
            ->whereIn('status', ['InterviewScheduled', 'UnderReview'])
            ->orderBy('pet_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('pet_id');

        foreach ($active as $petId => $applications) {
            $primary = $applications->first();
            DB::table('adoption_applications')->where('id', $primary->id)->update([
                'is_primary_candidate' => true,
            ]);
            DB::table('adoption_applications')
                ->where('pet_id', $petId)
                ->where('id', '!=', $primary->id)
                ->whereIn('status', ['Pending', 'InterviewScheduled', 'UnderReview'])
                ->update([
                    'status' => 'Waitlisted',
                    'is_primary_candidate' => false,
                    'admin_review_flagged_at' => null,
                ]);
            DB::table('pets')->where('id', $petId)->update([
                'availability_status' => 'Soft-Reserved',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('adoption_applications', function (Blueprint $table) {
            $table->dropIndex('applications_pet_queue_index');
            $table->dropIndex(['is_primary_candidate']);
            $table->dropIndex(['admin_review_flagged_at']);
            $table->dropColumn([
                'is_primary_candidate', 'queue_promoted_at', 'admin_review_flagged_at',
                'queue_closed_at', 'override_reason',
            ]);
        });
    }
};
