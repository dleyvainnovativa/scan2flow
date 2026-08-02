<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SessionLoginController extends Controller
{
    public function __construct(
        private FirebaseService $firebase,
        private AuditService $audit,   // >>> ADD
    ) {}
    /** Show the login page. */
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Called by the login page JS after Firebase email/password sign-in.
     * Receives the Firebase ID token, verifies it server-side, then opens a
     * regular Laravel session for Blade routes.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $verified = $this->firebase->verifyIdToken($data['id_token']);
            $uid = $this->firebase->uidFromToken($verified);
        } catch (Throwable $e) {
            return response()->json(['message' => 'No se pudo verificar la sesión.'], 401);
        }

        $user = User::where('firebase_uid', $uid)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Tu cuenta no está registrada en el sistema. Contacta al administrador.',
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Tu cuenta está desactivada.'], 403);
        }

        Auth::login($user, remember: true);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();
        $this->audit->log('login', $user, "Inició sesión: {$user->email}");


        return response()->json(['redirect' => route('dashboard')]);
    }

    /** Log out of the Laravel session. Firebase client sign-out happens in JS. */
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
