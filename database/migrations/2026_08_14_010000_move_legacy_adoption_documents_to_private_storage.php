<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $publicPathsToDelete = [];

        DB::table('adoption_applications')
            ->where('document_disk', 'public')
            ->whereNotNull('document_path')
            ->orderBy('id')
            ->each(function ($application) use (&$publicPathsToDelete) {
                if (! Storage::disk('public')->exists($application->document_path)) {
                    return;
                }

                $extension = pathinfo($application->document_path, PATHINFO_EXTENSION);
                $privatePath = 'adoption-documents/legacy-'.$application->id
                    .'-'.bin2hex(random_bytes(12))
                    .($extension ? '.'.strtolower($extension) : '');
                $contents = Storage::disk('public')->get($application->document_path);

                if (! Storage::disk('local')->put($privatePath, $contents)) {
                    return;
                }

                DB::table('adoption_applications')->where('id', $application->id)->update([
                    'document_path' => $privatePath,
                    'document_disk' => 'local',
                    'document_original_name' => $application->document_original_name
                        ?: basename($application->document_path),
                    'document_mime_type' => $application->document_mime_type
                        ?: Storage::disk('public')->mimeType($application->document_path),
                ]);

                $publicPathsToDelete[$application->document_path] = true;
            });

        foreach (array_keys($publicPathsToDelete) as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function down(): void
    {
        // Documents intentionally remain private if this migration is rolled back.
    }
};
