<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk for document files
    |--------------------------------------------------------------------------
    | Which filesystem disk (config/filesystems.php) holds uploaded PDFs/XMLs.
    | Default 'local' = storage/app, which is NOT web-accessible — files are
    | streamed through a permission-checked controller, never linked directly.
    |
    | For Module 1 later, point this at the disk where the capture module writes.
    */
    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    | Base directory (within the disk) under which document files are organized.
    | Final layout: {root}/{area_slug}/{template_slug}/{filename}
    */
    'root' => env('DOCUMENTS_ROOT', 'documents'),

    /*
    | Max upload size in kilobytes for the manual test-upload flow.
    */
    'max_upload_kb' => (int) env('DOCUMENTS_MAX_UPLOAD_KB', 20480), // 20 MB

];
