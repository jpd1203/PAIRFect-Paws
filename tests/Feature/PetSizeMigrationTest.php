<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PetSizeMigrationTest extends TestCase
{
    public function test_upgrade_snapshots_manual_labels_without_changing_historical_values(): void
    {
        $original = config('database.default');
        config(['database.connections.size_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'size_upgrade']);
        try {
            $this->assertSame(':memory:', DB::getDatabaseName());
            Schema::create('pets', function (Blueprint $table): void {
                $table->id();
                $table->string('physical_size')->nullable();
                $table->decimal('weight_kg', 10, 3)->nullable();
                $table->integer('age')->nullable();
                $table->timestamps();
            });
            DB::table('pets')->insert([
                ['id' => 1, 'physical_size' => 'Small', 'weight_kg' => '15', 'age' => 36, 'created_at' => '2020-01-01', 'updated_at' => '2020-01-01'],
                ['id' => 2, 'physical_size' => null, 'weight_kg' => null, 'age' => null, 'created_at' => '2020-01-01', 'updated_at' => '2020-01-01'],
            ]);
            $before = DB::table('pets')->get()->toArray();
            $migration = require base_path('database/migrations/2026_11_11_000001_add_weight_size_classification_to_pets.php');
            $migration->up();
            foreach ($before as $old) {
                $new = DB::table('pets')->find($old->id);
                foreach ((array) $old as $field => $value) {
                    $this->assertSame($value, $new->{$field});
                }
                $this->assertSame($old->physical_size, $new->legacy_physical_size);
                $this->assertSame('legacy_unclassified', $new->size_classification_status);
                $this->assertNull($new->size_rule_version);
            }
            $migration->down();
            $this->assertSame(json_encode($before), json_encode(DB::table('pets')->get()->toArray()));
        } finally {
            DB::purge('size_upgrade');
            config(['database.default' => $original]);
        }
    }
}
