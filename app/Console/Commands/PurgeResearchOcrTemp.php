<?php

namespace App\Console\Commands;

use App\Services\ResearchDocumentVerification;
use App\Support\ResearchMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class PurgeResearchOcrTemp extends Command
{
    protected $signature = 'research:purge-ocr-temp';

    protected $description = 'Remove abandoned private research OCR files older than one hour';

    public function handle(): int
    {
        if (! ResearchMode::ready()) {
            return self::FAILURE;
        }

        $disk = Storage::disk(ResearchDocumentVerification::DISK);
        $cutoff = now()->subHour()->timestamp;
        $failed = false;
        foreach ($disk->files() as $path) {
            if (preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\.ocr\z/', $path)
                && $disk->lastModified($path) < $cutoff) {
                $failed = ! $disk->delete($path) || $failed;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
