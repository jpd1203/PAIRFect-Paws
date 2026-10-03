<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'post_adoption_logs_application_milestone_unique';

    private const FOREIGN_KEY_SUPPORT_INDEX = 'post_adoption_logs_application_fk_support_index';

    private const PHOTO_HASH_UNIQUE_INDEX = 'post_adoption_logs_photo_sha256_unique';

    private const MANIFEST_UNIQUE_INDEX = 'post_adoption_logs_c2pa_manifest_sha256_unique';

    /** @var array<string, string> */
    private const MILESTONE_MAP = [
        'ThreeDays' => '3_days',
        'three_days' => '3_days',
        '3-Day' => '3_days',
        '3 Day' => '3_days',
        'ThreeWeeks' => '3_weeks',
        'three_weeks' => '3_weeks',
        '3-Week' => '3_weeks',
        '3 Week' => '3_weeks',
        'ThreeMonths' => '3_months',
        'three_months' => '3_months',
        '3-Month' => '3_months',
        '3 Month' => '3_months',
    ];

    /** @var array<string, string> */
    private const LEGACY_MILESTONE_MAP = [
        '3_days' => 'ThreeDays',
        '3_weeks' => 'ThreeWeeks',
        '3_months' => 'ThreeMonths',
    ];

    /** @var list<string> */
    private const LEGACY_SURVEY_COLUMNS = [
        'pet_current_status',
        'behavioral_observations',
        'living_conditions',
        'eating_habits',
        'vet_visit_details',
        'concerns',
    ];

    public function up(): void
    {
        $this->addStableAdoptionDate();

        if (! Schema::hasTable('post_adoption_logs')) {
            return;
        }

        if (! Schema::hasColumn('post_adoption_logs', 'survey_data')) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->json('survey_data')->nullable()->after('submitted_date');
            });
        }

        if (! Schema::hasColumn('post_adoption_logs', 'last_reminder_sent_at')) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->timestamp('last_reminder_sent_at')->nullable()->after('reminders_sent');
            });
        }

        if (! Schema::hasColumn('post_adoption_logs', 'flag_reasons')) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->json('flag_reasons')->nullable()->after('reminders_sent');
            });
        }

        if (! Schema::hasColumn('post_adoption_logs', 'photo_sha256')) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->char('photo_sha256', 64)->nullable()->after('photo_path');
            });
        }

        if (! Schema::hasColumn('post_adoption_logs', 'c2pa_manifest_sha256')) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->char('c2pa_manifest_sha256', 64)->nullable()->after('photo_sha256');
            });
        }

        if (
            Schema::hasColumn('post_adoption_logs', 'adoption_application_id')
            && ! Schema::hasColumn('post_adoption_logs', 'application_id')
        ) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->renameColumn('adoption_application_id', 'application_id');
            });
        }

        if (
            Schema::hasColumn('post_adoption_logs', 'flagged_for_review')
            && ! Schema::hasColumn('post_adoption_logs', 'is_flagged')
        ) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->renameColumn('flagged_for_review', 'is_flagged');
            });
        }

        $this->normalizeMilestones();
        $this->rejectUnknownMilestones();
        $this->backfillSurveyData();
        $this->mergeDuplicateMilestones();

        if (
            Schema::hasColumn('post_adoption_logs', 'application_id')
            && ! Schema::hasIndex('post_adoption_logs', self::UNIQUE_INDEX)
        ) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->unique(['application_id', 'milestone'], self::UNIQUE_INDEX);
            });
        }

        if (Schema::hasIndex('post_adoption_logs', self::FOREIGN_KEY_SUPPORT_INDEX)) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->dropIndex(self::FOREIGN_KEY_SUPPORT_INDEX);
            });
        }

        if (! Schema::hasIndex('post_adoption_logs', self::PHOTO_HASH_UNIQUE_INDEX)) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->unique('photo_sha256', self::PHOTO_HASH_UNIQUE_INDEX);
            });
        }

        if (! Schema::hasIndex('post_adoption_logs', self::MANIFEST_UNIQUE_INDEX)) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->unique('c2pa_manifest_sha256', self::MANIFEST_UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('post_adoption_logs')) {
            $this->dropStableAdoptionDate();

            return;
        }

        foreach ([self::PHOTO_HASH_UNIQUE_INDEX, self::MANIFEST_UNIQUE_INDEX] as $index) {
            if (Schema::hasIndex('post_adoption_logs', $index)) {
                Schema::table('post_adoption_logs', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index);
                });
            }
        }

        if (Schema::hasIndex('post_adoption_logs', self::UNIQUE_INDEX)) {
            // MySQL requires another application_id index while the foreign key
            // remains in place; the unique index may be its only supporting index.
            if (! Schema::hasIndex('post_adoption_logs', self::FOREIGN_KEY_SUPPORT_INDEX)) {
                Schema::table('post_adoption_logs', function (Blueprint $table) {
                    $table->index('application_id', self::FOREIGN_KEY_SUPPORT_INDEX);
                });
            }

            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        $this->restoreMergedDuplicateLogs();
        $this->restoreLegacySurveyColumns();

        foreach (self::LEGACY_MILESTONE_MAP as $normalized => $legacy) {
            DB::table('post_adoption_logs')
                ->where('milestone', $normalized)
                ->update(['milestone' => $legacy]);
        }

        $columnsToDrop = array_values(array_filter(
            [
                'survey_data',
                'flag_reasons',
                'last_reminder_sent_at',
                'photo_sha256',
                'c2pa_manifest_sha256',
            ],
            fn (string $column): bool => Schema::hasColumn('post_adoption_logs', $column)
        ));

        if ($columnsToDrop !== []) {
            Schema::table('post_adoption_logs', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        if (
            Schema::hasColumn('post_adoption_logs', 'is_flagged')
            && ! Schema::hasColumn('post_adoption_logs', 'flagged_for_review')
        ) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->renameColumn('is_flagged', 'flagged_for_review');
            });
        }

        if (
            Schema::hasColumn('post_adoption_logs', 'application_id')
            && ! Schema::hasColumn('post_adoption_logs', 'adoption_application_id')
        ) {
            Schema::table('post_adoption_logs', function (Blueprint $table) {
                $table->renameColumn('application_id', 'adoption_application_id');
            });
        }

        $this->dropStableAdoptionDate();
    }

    private function normalizeMilestones(): void
    {
        foreach (self::MILESTONE_MAP as $legacy => $normalized) {
            DB::table('post_adoption_logs')
                ->where('milestone', $legacy)
                ->update(['milestone' => $normalized]);
        }
    }

    private function rejectUnknownMilestones(): void
    {
        $unknown = DB::table('post_adoption_logs')
            ->where(function ($query) {
                $query->whereNull('milestone')
                    ->orWhereNotIn('milestone', array_keys(self::LEGACY_MILESTONE_MAP));
            })
            ->distinct()
            ->orderBy('milestone')
            ->pluck('milestone')
            ->map(fn ($milestone): string => is_string($milestone) && $milestone !== ''
                ? $milestone
                : '[empty]')
            ->values();

        if ($unknown->isNotEmpty()) {
            throw new RuntimeException(
                'Unsupported post-adoption milestone value(s): '.$unknown->implode(', ')
            );
        }
    }

    private function backfillSurveyData(): void
    {
        $legacyColumns = array_values(array_filter(
            self::LEGACY_SURVEY_COLUMNS,
            fn (string $column): bool => Schema::hasColumn('post_adoption_logs', $column)
        ));

        if ($legacyColumns === []) {
            return;
        }

        DB::table('post_adoption_logs')
            ->select(array_merge(['id', 'survey_data'], $legacyColumns))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($legacyColumns) {
                foreach ($rows as $row) {
                    $survey = $this->decodeJson($row->survey_data ?? null);

                    foreach ($legacyColumns as $column) {
                        $value = $row->{$column};
                        if ($value !== null && ! array_key_exists($column, $survey)) {
                            $survey[$column] = $value;
                        }
                    }

                    if ($survey !== []) {
                        DB::table('post_adoption_logs')
                            ->where('id', $row->id)
                            ->update(['survey_data' => $this->encodeJson($survey)]);
                    }
                }
            }, 'id');
    }

    /**
     * Old installations did not have a uniqueness constraint. Merge accidental
     * duplicates before adding it, while retaining a snapshot of every removed
     * row inside survey_data for audit/recovery purposes.
     */
    private function mergeDuplicateMilestones(): void
    {
        if (! Schema::hasColumn('post_adoption_logs', 'application_id')) {
            return;
        }

        $duplicates = DB::table('post_adoption_logs')
            ->select('application_id', 'milestone', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('application_id', 'milestone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('post_adoption_logs')
                ->where('application_id', $duplicate->application_id)
                ->where('milestone', $duplicate->milestone)
                ->orderBy('id')
                ->get();

            $winner = $rows->first(fn ($row) => $row->submitted_date !== null) ?? $rows->first();
            if (! $winner) {
                continue;
            }

            $mergedSurvey = $this->decodeJson($winner->survey_data ?? null);
            $mergedReasons = $this->decodeJson($winner->flag_reasons ?? null);
            $snapshots = [];

            foreach ($rows as $row) {
                if ($row->id === $winner->id) {
                    continue;
                }

                // Preserve the submitted winner's answers. Duplicate rows may
                // fill missing keys, but must never overwrite verified data.
                $mergedSurvey = array_replace_recursive(
                    $this->decodeJson($row->survey_data ?? null),
                    $mergedSurvey,
                );
                $mergedReasons = array_values(array_merge(
                    $mergedReasons,
                    $this->decodeJson($row->flag_reasons ?? null)
                ));
                $snapshots[] = (array) $row;
            }

            if ($snapshots !== []) {
                $mergedSurvey['_merged_duplicate_logs'] = array_values(array_merge(
                    $mergedSurvey['_merged_duplicate_logs'] ?? [],
                    $snapshots
                ));
            }

            $winnerFirst = collect([$winner])->concat(
                $rows->reject(fn ($row) => $row->id === $winner->id)
            );
            $hasUnresolvedFlag = $rows->contains(
                fn ($row): bool => (bool) $row->is_flagged && $row->resolved_at === null
            );

            $updates = [
                'scheduled_date' => $rows->pluck('scheduled_date')->filter()->sort()->first(),
                'submitted_date' => $winner->submitted_date,
                'survey_data' => $mergedSurvey === [] ? null : $this->encodeJson($mergedSurvey),
                'photo_path' => $winnerFirst->pluck('photo_path')->first(fn ($value) => filled($value)),
                'photo_sha256' => $winnerFirst->pluck('photo_sha256')->first(fn ($value) => filled($value)),
                'c2pa_manifest_sha256' => $winnerFirst->pluck('c2pa_manifest_sha256')->first(fn ($value) => filled($value)),
                'is_flagged' => $rows->contains(fn ($row) => (bool) $row->is_flagged),
                'flag_reasons' => $mergedReasons === [] ? null : $this->encodeJson($mergedReasons),
                'reminders_sent' => (int) $rows->max('reminders_sent'),
                'last_reminder_sent_at' => $rows->pluck('last_reminder_sent_at')->filter()->sortDesc()->first(),
                'resolved_at' => $hasUnresolvedFlag
                    ? null
                    : $winnerFirst->pluck('resolved_at')->first(fn ($value) => $value !== null),
                'resolved_by_user_id' => $hasUnresolvedFlag
                    ? null
                    : $winnerFirst->pluck('resolved_by_user_id')->first(fn ($value) => $value !== null),
                'resolution_note' => $hasUnresolvedFlag
                    ? null
                    : $winnerFirst->pluck('resolution_note')->first(fn ($value) => filled($value)),
                'version' => (int) $rows->max('version'),
            ];

            foreach (self::LEGACY_SURVEY_COLUMNS as $column) {
                if (Schema::hasColumn('post_adoption_logs', $column)) {
                    $updates[$column] = $winnerFirst->pluck($column)->first(fn ($value) => $value !== null);
                }
            }

            DB::table('post_adoption_logs')->where('id', $winner->id)->update($updates);
            DB::table('post_adoption_logs')
                ->where('application_id', $duplicate->application_id)
                ->where('milestone', $duplicate->milestone)
                ->where('id', '!=', $winner->id)
                ->delete();
        }
    }

    private function restoreLegacySurveyColumns(): void
    {
        if (! Schema::hasColumn('post_adoption_logs', 'survey_data')) {
            return;
        }

        $legacyColumns = array_values(array_filter(
            self::LEGACY_SURVEY_COLUMNS,
            fn (string $column): bool => Schema::hasColumn('post_adoption_logs', $column)
        ));

        DB::table('post_adoption_logs')
            ->select(['id', 'survey_data'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($legacyColumns) {
                foreach ($rows as $row) {
                    $survey = $this->decodeJson($row->survey_data ?? null);
                    $updates = [];

                    foreach ($legacyColumns as $column) {
                        if (array_key_exists($column, $survey)) {
                            $updates[$column] = $survey[$column];
                        }
                    }

                    if ($updates !== []) {
                        DB::table('post_adoption_logs')->where('id', $row->id)->update($updates);
                    }
                }
            }, 'id');
    }

    private function restoreMergedDuplicateLogs(): void
    {
        if (! Schema::hasColumn('post_adoption_logs', 'survey_data')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('post_adoption_logs'));

        DB::table('post_adoption_logs')
            ->select(['id', 'survey_data'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($columns) {
                foreach ($rows as $row) {
                    $survey = $this->decodeJson($row->survey_data ?? null);
                    $snapshots = $survey['_merged_duplicate_logs'] ?? [];

                    if (! is_array($snapshots)) {
                        continue;
                    }

                    foreach ($snapshots as $snapshot) {
                        if (! is_array($snapshot)) {
                            continue;
                        }

                        $record = array_intersect_key($snapshot, $columns);
                        $id = $record['id'] ?? null;

                        if (! is_int($id) && ! ctype_digit((string) $id)) {
                            continue;
                        }

                        if (DB::table('post_adoption_logs')->where('id', (int) $id)->exists()) {
                            continue;
                        }

                        DB::table('post_adoption_logs')->insert($record);
                    }
                }
            }, 'id');
    }

    private function addStableAdoptionDate(): void
    {
        if (! Schema::hasTable('adoption_applications')) {
            return;
        }

        if (! Schema::hasColumn('adoption_applications', 'adopted_at')) {
            Schema::table('adoption_applications', function (Blueprint $table) {
                $table->timestamp('adopted_at')->nullable()->after('queue_closed_at');
            });
        }

        DB::table('adoption_applications')
            ->where('status', 'Approved')
            ->whereNull('adopted_at')
            ->update([
                'adopted_at' => DB::raw('COALESCE(queue_closed_at, updated_at, created_at)'),
            ]);
    }

    private function dropStableAdoptionDate(): void
    {
        if (
            Schema::hasTable('adoption_applications')
            && Schema::hasColumn('adoption_applications', 'adopted_at')
        ) {
            Schema::table('adoption_applications', function (Blueprint $table) {
                $table->dropColumn('adopted_at');
            });
        }
    }

    /** @return array<mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
