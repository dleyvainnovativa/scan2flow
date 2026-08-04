<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Admin-only user management. Creating/disabling/deleting a user keeps Firebase
 * Auth and the local `users` table in sync. Firebase is the source of truth for
 * credentials; the local row carries app role + profile + active flag.
 */
class UserController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    public function index()
    {
        // User is intentionally NOT globally tenant-scoped (auth guard must load
        // users without tenant context). So filter the LIST explicitly.
        $users = User::forCurrentTenant()
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'member'])],
        ]);
        app(\App\Services\PlanGate::class)->ensureCanCreate('users');

        // Create in Firebase first; if the local insert fails, roll it back.
        $uid = $this->firebase->createUser($data['email'], $data['password'], $data['name']);

        try {
            $user = DB::transaction(fn() => User::create([
                'firebase_uid' => $uid,
                'name'         => $data['name'],
                'email'        => $data['email'],
                'role'         => $data['role'],
                'is_active'    => true,
                'tenant_id' => $request->user()->tenant_id
            ]));
        } catch (Throwable $e) {
            // Compensating action: remove the orphaned Firebase user.
            $this->firebase->deleteUser($uid);
            throw $e;
        }

        return response()->json([
            'message' => 'Usuario creado.',
            'user'    => $user,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::in(['admin', 'member'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $fbUpdate = ['email' => $data['email'], 'displayName' => $data['name']];
        if (! empty($data['password'])) {
            $fbUpdate['password'] = $data['password'];
        }
        $this->firebase->updateUser($user->firebase_uid, $fbUpdate);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'],
        ]);

        return response()->json(['message' => 'Usuario actualizado.', 'user' => $user]);
    }

    /** Toggle active state (mirrors Firebase disabled flag). */
    public function toggleActive(Request $request, User $user)
    {
        $newState = ! $user->is_active;

        $this->firebase->setDisabled($user->firebase_uid, disabled: ! $newState);
        $user->update(['is_active' => $newState]);

        return response()->json([
            'message'   => $newState ? 'Usuario activado.' : 'Usuario desactivado.',
            'is_active' => $newState,
        ]);
    }

    public function destroy(User $user)
    {
        // Guard: don't let an admin delete themselves.
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        $this->firebase->deleteUser($user->firebase_uid);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado.']);
    }
}
