<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI structuring engine
    |--------------------------------------------------------------------------
    | 'null'   → disabled (M1-P0/P1 behavior)
    | 'openai' → OpenAI structured extraction (M1-P2)
    */
    'engine' => env('AI_ENGINE', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI credentials & model
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key'  => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model'    => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout'  => (int) env('OPENAI_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    | max_input_chars : truncate OCR text sent to the model (cost/context guard).
    | temperature     : low for deterministic extraction.
    */
    'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 12000),
    'temperature'     => (float) env('AI_TEMPERATURE', 0.0),

];
