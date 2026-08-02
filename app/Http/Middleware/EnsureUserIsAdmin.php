<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate admin-only areas (user management, later: areas/templates admin).
 * Works for both session and bearer-authenticated users since both populate
 * auth()->user().
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Requiere permisos de administrador.'], 403);
            }
            abort(403, 'Requiere permisos de administrador.');
        }

        return $next($request);
    }
}
