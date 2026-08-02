<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Base controller. Laravel 13's default is empty, so we add the traits that
 * $this->authorize() and $this->validate() rely on. All controllers extend this.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
