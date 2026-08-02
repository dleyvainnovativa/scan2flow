<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Template;
use App\Models\TemplateField;
use Illuminate\Database\Seeder;

/**
 * Sample data mirroring the client's example (Finanzas → Facturas CxC with
 * Folio, Proveedor, Monto, C. Costos, Uso CFDI). Run:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $area = Area::firstOrCreate(
            ['slug' => 'finanzas'],
            ['name' => 'Finanzas', 'description' => 'Documentos contables y financieros.']
        );

        if ($area->templates()->where('slug', 'facturas-cxc')->exists()) {
            $this->command->warn('La plantilla de ejemplo ya existe. Omitido.');
            return;
        }

        $tpl = Template::create([
            'area_id'           => $area->id,
            'name'              => 'Facturas CxC',
            'slug'              => 'facturas-cxc',
            'description'       => 'Facturas de cuentas por cobrar.',
            'input_folder_path' => '/input/finanzas/facturas_cxc',
            'naming_rule'       => 'same_name',
        ]);

        $fields = [
            ['key' => 'folio',     'label' => 'Folio',      'type' => 'text',     'is_required' => true],
            ['key' => 'proveedor', 'label' => 'Proveedor',  'type' => 'text',     'is_required' => true],
            ['key' => 'monto',     'label' => 'Monto',      'type' => 'currency', 'is_required' => true],
            ['key' => 'c_costos',  'label' => 'C. Costos',  'type' => 'text',     'is_required' => false],
            ['key' => 'uso_cfdi',  'label' => 'Uso CFDI',   'type' => 'select',   'is_required' => false,
                'options' => ['G01', 'G03', 'P01', 'I01']],
        ];

        foreach ($fields as $pos => $f) {
            TemplateField::create(array_merge($f, [
                'template_id' => $tpl->id,
                'position'    => $pos,
            ]));
        }

        $this->command->info('Datos de ejemplo creados: Finanzas → Facturas CxC.');
    }
}
