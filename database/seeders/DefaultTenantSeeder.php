<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Creates a default tenant and attaches any existing users to it. On a clean
 * system this gives your current local login a tenant so the global scope
 * resolves. Idempotent.
 */
class DefaultTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default', 'page_balance' => 100000, 'status' => 'active']
        );

        // Attach any tenant-less users to the default tenant.
        // (Bypass the scope: users may currently have null tenant_id.)
        app(TenantContext::class)->withoutScope(function () use ($tenant) {
            User::whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);
        });

        $this->command?->info("Default tenant ready (id {$tenant->id}); tenant-less users attached.");
    }
}
