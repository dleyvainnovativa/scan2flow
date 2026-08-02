<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Creates the first admin account in BOTH Firebase and the local DB.
 * Configure credentials via env before running:
 *   ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_NAME
 *
 * Run:  php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'ChangeMe123!');
        $name = env('ADMIN_NAME', 'Administrador');

        if (User::where('email', $email)->exists()) {
            $this->command->warn("El usuario {$email} ya existe. Omitido.");
            return;
        }

        /** @var FirebaseService $firebase */
        $firebase = app(FirebaseService::class);

        try {
            $uid = $firebase->createUser($email, $password, $name);
        } catch (Throwable $e) {
            $this->command->error('No se pudo crear el usuario en Firebase: ' . $e->getMessage());
            return;
        }

        User::create([
            'firebase_uid' => $uid,
            'name'         => $name,
            'email'        => $email,
            'role'         => 'admin',
            'is_active'    => true,
        ]);

        $this->command->info("Administrador creado: {$email}");
    }
}
