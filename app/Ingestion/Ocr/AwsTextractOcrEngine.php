<?php

namespace App\Ingestion\Ocr;

use App\Contracts\OcrEngine;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AWS Textract OCR engine (scaffold for the AWS migration).
 *
 * Textract advantages over local Tesseract for this project:
 *   - No binaries to manage on the server.
 *   - Returns WORD-level bounding boxes → enables true on-image highlighting for
 *     scanned PDFs (store boxes alongside document_contents to power it later).
 *   - Strong Spanish accuracy.
 *
 * To activate on AWS:
 *   1. composer require aws/aws-sdk-php
 *   2. Set OCR_ENGINE=textract and AWS creds (or use an IAM role on the instance).
 *   3. Uncomment the SDK calls below and bind this in IngestionServiceProvider:
 *        'textract' => new AwsTextractOcrEngine(),
 *
 * Left as a guarded stub so the codebase references the class without requiring
 * the AWS SDK until you migrate. isAvailable() returns false until wired.
 */
class AwsTextractOcrEngine implements OcrEngine
{
    public function isAvailable(): bool
    {
        // Flip to a real check once the SDK + credentials are configured, e.g.:
        //   return class_exists(\Aws\Textract\TextractClient::class)
        //       && ! empty(config('services.textract.region'));
        return false;
    }

    public function extractText(string $absolutePdfPath): string
    {
        if (! $this->isAvailable()) {
            return '';
        }

        try {
            // --- Reference implementation (uncomment after installing the SDK) ---
            //
            // $client = new \Aws\Textract\TextractClient([
            //     'region'  => config('services.textract.region', 'us-east-1'),
            //     'version' => 'latest',
            // ]);
            //
            // // For multi-page PDFs use the async API (StartDocumentTextDetection)
            // // with an S3 object + SNS/polling. For single images, DetectDocumentText
            // // works synchronously. Simplified single-call sketch:
            // $result = $client->detectDocumentText([
            //     'Document' => ['Bytes' => file_get_contents($absolutePdfPath)],
            // ]);
            //
            // $lines = [];
            // foreach ($result['Blocks'] as $block) {
            //     if (($block['BlockType'] ?? '') === 'LINE') {
            //         $lines[] = $block['Text'] ?? '';
            //     }
            //     // WORD blocks carry $block['Geometry']['BoundingBox'] — persist
            //     // these to enable on-image highlighting for scans.
            // }
            // return trim(implode("\n", $lines));

            return '';
        } catch (Throwable $e) {
            Log::warning('Textract OCR failed', ['error' => $e->getMessage()]);
            return '';
        }
    }
}
