<?php

namespace App\Providers;

use App\Contracts\AiStructuring;
use App\Contracts\OcrEngine;
use App\Ingestion\Ai\NullAiStructuring;
use App\Ingestion\Ai\OpenAiStructuring;
use App\Ingestion\Ocr\NullOcrEngine;
use App\Ingestion\Ocr\TesseractOcrEngine;
use Illuminate\Support\ServiceProvider;

class IngestionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OCR engine (M1-P1)
        $this->app->bind(OcrEngine::class, function () {
            return match (config('ocr.engine', 'null')) {
                'tesseract' => new TesseractOcrEngine(),
                default     => new NullOcrEngine(),
            };
        });

        // AI structuring engine (M1-P2)
        //   'openai' → OpenAiStructuring
        //   'null'   → disabled
        // (later: swap in other providers behind the same interface)
        $this->app->bind(AiStructuring::class, function () {
            return match (config('ai.engine', 'null')) {
                'openai' => new OpenAiStructuring(),
                default  => new NullAiStructuring(),
            };
        });
    }
}
