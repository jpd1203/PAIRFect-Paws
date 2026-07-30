<?php

namespace App\Services;

class DocumentVerificationService
{
    /**
     * Process an uploaded ID document through Tier 1 OCR (Google Cloud Vision).
     *
     * @param string $filePath The path to the uploaded document.
     * @return array The extracted text and verification status.
     */
    public function processDocument(string $filePath): array
    {
        // TODO: Initialize Google Cloud VisionClient
        // $vision = new \Google\Cloud\Vision\V1\ImageAnnotatorClient();
        
        // Stub for OCR processing
        $extractedText = "SAMPLE EXTRACTED TEXT FROM GOOGLE CLOUD VISION";
        
        return [
            'status' => 'Verified',
            'extracted_text' => $extractedText,
            'confidence_score' => 0.95
        ];
    }
}
