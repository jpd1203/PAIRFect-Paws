<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VIDEO_HASH_UNIQUE_INDEX = 'post_adoption_logs_video_sha256_unique';

    public function up(): void
    {
        if (Schema::hasTable('post_adoption_logs')) {
            if (! Schema::hasColumn('post_adoption_logs', 'video_path')) {
                Schema::table('post_adoption_logs', function (Blueprint $table): void {
                    $table->string('video_path')->nullable()->after('photo_path');
                });
            }

            if (! Schema::hasColumn('post_adoption_logs', 'video_sha256')) {
                Schema::table('post_adoption_logs', function (Blueprint $table): void {
                    $table->char('video_sha256', 64)->nullable()->after('video_path');
                });
            }

            if (! Schema::hasColumn('post_adoption_logs', 'video_mime_type')) {
                Schema::table('post_adoption_logs', function (Blueprint $table): void {
                    $table->string('video_mime_type', 64)->nullable()->after('video_sha256');
                });
            }

            if (! Schema::hasColumn('post_adoption_logs', 'video_duration_ms')) {
                Schema::table('post_adoption_logs', function (Blueprint $table): void {
                    $table->unsignedInteger('video_duration_ms')->nullable()->after('video_mime_type');
                });
            }

            if (! Schema::hasIndex('post_adoption_logs', self::VIDEO_HASH_UNIQUE_INDEX)) {
                Schema::table('post_adoption_logs', function (Blueprint $table): void {
                    $table->unique('video_sha256', self::VIDEO_HASH_UNIQUE_INDEX);
                });
            }
        }

        if (
            Schema::hasTable('post_adoption_capture_challenges')
            && ! Schema::hasColumn('post_adoption_capture_challenges', 'video_sha256')
        ) {
            Schema::table('post_adoption_capture_challenges', function (Blueprint $table): void {
                $table->char('video_sha256', 64)->nullable()->after('photo_sha256');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('post_adoption_capture_challenges')) {
            $columns = array_values(array_filter(
                ['video_sha256'],
                fn (string $column): bool => Schema::hasColumn('post_adoption_capture_challenges', $column),
            ));

            if ($columns !== []) {
                Schema::table('post_adoption_capture_challenges', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        if (! Schema::hasTable('post_adoption_logs')) {
            return;
        }

        if (Schema::hasIndex('post_adoption_logs', self::VIDEO_HASH_UNIQUE_INDEX)) {
            Schema::table('post_adoption_logs', function (Blueprint $table): void {
                $table->dropUnique(self::VIDEO_HASH_UNIQUE_INDEX);
            });
        }

        $columns = array_values(array_filter(
            ['video_path', 'video_sha256', 'video_mime_type', 'video_duration_ms'],
            fn (string $column): bool => Schema::hasColumn('post_adoption_logs', $column),
        ));

        if ($columns !== []) {
            Schema::table('post_adoption_logs', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
