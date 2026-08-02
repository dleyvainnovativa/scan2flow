<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR engine selection
    |--------------------------------------------------------------------------
    | 'null'      → no OCR (M1-P0 behavior)
    | 'tesseract' → local pdftotext + pdftoppm + tesseract (M1-P1)
    | (later: 'textract' for AWS)
    */
    'engine' => env('OCR_ENGINE', 'tesseract'),

    /*
    |--------------------------------------------------------------------------
    | Binary paths (override in .env if not on PATH)
    |--------------------------------------------------------------------------
    | macOS (brew): usually /opt/homebrew/bin or /usr/local/bin.
    | Linux: usually /usr/bin.
    */
    'bin' => [
        'pdftotext' => env('OCR_BIN_PDFTOTEXT', 'pdftotext'),
        'pdftoppm'  => env('OCR_BIN_PDFTOPPM', 'pdftoppm'),
        'tesseract' => env('OCR_BIN_TESSERACT', 'tesseract'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract language(s). Spanish primary; add '+eng' if mixed.
    |--------------------------------------------------------------------------
    */
    'lang' => env('OCR_LANG', 'spa'),

    /*
    |--------------------------------------------------------------------------
    | Digital-vs-scanned threshold
    |--------------------------------------------------------------------------
    | If pdftotext extracts at least this many characters, treat the PDF as
    | digital and skip image OCR. Below it, rasterize + Tesseract.
    */
    'text_layer_min_chars' => (int) env('OCR_TEXT_MIN_CHARS', 40),

    /*
    |--------------------------------------------------------------------------
    | Rasterization DPI for scanned pages (higher = better OCR, slower).
    |--------------------------------------------------------------------------
    */
    'dpi' => (int) env('OCR_DPI', 200),

    /*
    |--------------------------------------------------------------------------
    | Max pages to OCR per document (guards runaway scans). 0 = no limit.
    |--------------------------------------------------------------------------
    */
    'max_pages' => (int) env('OCR_MAX_PAGES', 30),

    /*
    |--------------------------------------------------------------------------
    | Per-process timeout in seconds for each external command.
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('OCR_TIMEOUT', 120),

];
