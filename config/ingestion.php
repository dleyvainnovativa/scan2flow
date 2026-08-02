<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Processing mode
    |--------------------------------------------------------------------------
    | 'sync'  → process inline during the request / command (M1-P0..P2 behavior)
    | 'queue' → dispatch a job per template; a worker processes it
    |
    | Queue mode needs a running worker: php artisan queue:work
    */
    'mode' => env('INGESTION_MODE', 'sync'),

    /*
    | Queue name for ingestion jobs (lets you isolate/prioritize them).
    */
    'queue' => env('INGESTION_QUEUE', 'ingestion'),

    /*
    |--------------------------------------------------------------------------
    | Archive source files after processing
    |--------------------------------------------------------------------------
    | When true, originals in the INPUT folder are MOVED after handling:
    |   success → {input}/processed/    failure → {input}/failed/
    | When false (default), originals are left in place (idempotency still
    | prevents re-creating documents via ingestion_records).
    */
    'archive_after' => (bool) env('INGESTION_ARCHIVE_AFTER', false),
    'archive_dirs'  => [
        'success' => 'processed',
        'failure' => 'failed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Job retry policy (queue mode)
    |--------------------------------------------------------------------------
    */
    'tries'        => (int) env('INGESTION_TRIES', 3),
    'backoff'      => [30, 120, 300], // seconds between retries
    'job_timeout'  => (int) env('INGESTION_JOB_TIMEOUT', 900), // 15 min for big scans

];
