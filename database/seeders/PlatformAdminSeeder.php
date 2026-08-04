<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Promotes a user to platform admin. Set the email via env or edit below, then:
 *   php artisan db:seed --class=PlatformAdminSeeder
 *
 * A platform admin has tenant_id = NULL (operates above tenancy) and
 * is_platform_admin = true. They must already exist as a user (created via your
 * normal auth flow / Firebase). This seeder just flips the flags.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL');
        if (! $email) {
            $this->command?->warn('Set PLATFORM_ADMIN_EMAIL in .env, then re-run this seeder.');
            return;
        }

        // Look the user up WITHOUT tenant scope (they may already be tenant-less).
        $user = app(TenantContext::class)->withoutScope(fn () =>
            User::where('email', $email)->first()
        );

        if (! $user) {
            $this->command?->error("No existe un usuario con email {$email}.");
            return;
        }

        app(TenantContext::class)->withoutScope(function () use ($user) {
            $user->update([
                'is_platform_admin' => true,
                'tenant_id'         => null, // operate above tenancy
            ]);
        });

        $this->command?->info("Usuario {$user->email} promovido a administrador de plataforma.");
    }
}
