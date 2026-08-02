<?php

/*
|==============================================================================
| REFERENCE — merge into your existing bootstrap/providers.php
|==============================================================================
| Add App\Providers\FirebaseServiceProvider::class to the returned array.
*/

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FirebaseServiceProvider::class,   // >>> ADD THIS LINE <<<
    App\Providers\SearchServiceProvider::class,     // >>> ADD THIS (Phase 4) <<<
    App\Providers\IngestionServiceProvider::class,  // >>> ADD (Module 1) <<<
];
