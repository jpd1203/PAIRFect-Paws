<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MediaVerificationService
{
    /**
     * Verifies the C2PA cryptographic signature of an uploaded media file.
     *
     * @param string $filePath
     * @return bool
     */
    public function verifyC2paSignature(string $filePath): bool
    {
        // Execute c2patool via Symfony Process
        $process = new Process(['c2patool', $filePath]);
        $process->run();

        // If the process wasn't successful, verification failed
        if (!$process->isSuccessful()) {
            // throw new ProcessFailedException($process);
            return false;
        }

        // Parse the JSON output from c2patool
        $output = $process->getOutput();
        $data = json_decode($output, true);

        // Analyze $data to ensure signatures are valid and untampered
        return isset($data['validation_status']) && $data['validation_status'] === 'valid';
    }
}
