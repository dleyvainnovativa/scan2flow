<?php

/*
|==============================================================================
| REFERENCE — schedule the ingestion command (routes/console.php in Laravel 13)
|==============================================================================
| Laravel 13 registers scheduled tasks in routes/console.php using Schedule.
| Add this to poll the INPUT folders periodically. Adjust the cadence as needed.
|
| On the server, one system cron entry drives the Laravel scheduler:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/

use Illuminate\Support\Facades\Schedule;

// Poll every 5 minutes. Use ->withoutOverlapping() so a long run doesn't stack.
Schedule::command('documents:ingest')
    ->everyFiveMinutes()
    ->withoutOverlapping();
