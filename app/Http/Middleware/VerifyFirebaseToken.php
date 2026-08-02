<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verifies a Firebase ID token supplied as `Authorization: Bearer <token>`
 * and logs the matching local user in for the request (stateless API guard).
 *
 * For Blade/session routes we rely on the normal `auth` middleware after the
 * session is opened by SessionLoginController; this middleware is for API
 * endpoints that present a bearer token instead of a cookie.
 */
class VerifyFirebaseToken
{
    public function __construct(private FirebaseService $firebase) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Token no proporcionado.'], 401);
        }

        $idToken = substr($header, 7);

        try {
            $verified = $this->firebase->verifyIdToken($idToken);
            $uid = $this->firebase->uidFromToken($verified);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Token inválido o expirado.'], 401);
        }

        $user = User::where('firebase_uid', $uid)->first();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Usuario no autorizado.'], 403);
        }

        // Bind the resolved user to the request for downstream controllers.
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
