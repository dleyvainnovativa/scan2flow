<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * A few starter plans. Custom plans are just more rows — edit limits/pages/price
 * per customer as data, never as code.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico', 'slug' => 'basico',
                'max_templates' => 5, 'max_areas' => 3, 'max_users' => 5,
                'page_bundle' => 10000, 'price_cents' => 0, 'currency' => 'MXN',
            ],
            [
                'name' => 'Profesional', 'slug' => 'profesional',
                'max_templates' => 20, 'max_areas' => 10, 'max_users' => 25,
                'page_bundle' => 50000, 'price_cents' => 0, 'currency' => 'MXN',
            ],
            [
                'name' => 'Empresarial', 'slug' => 'empresarial',
                'max_templates' => null, 'max_areas' => null, 'max_users' => null, // unlimited
                'page_bundle' => 250000, 'price_cents' => 0, 'currency' => 'MXN',
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['slug' => $p['slug']], $p);
        }

        $this->command?->info('Starter plans seeded (Básico, Profesional, Empresarial).');
    }
}
