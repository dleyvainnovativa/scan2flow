<?php

namespace App\Ingestion;

use App\Models\Template;

/**
 * Maps parsed CFDI values onto a template's field keys.
 *
 * Resolution order for each template field:
 *   1. Exact key match (template field key == CFDI key, e.g. `folio`, `uso_cfdi`).
 *   2. Known aliases (e.g. `proveedor` -> emisor_nombre, `monto` -> total).
 *   3. Fuzzy contains match on the field key against CFDI keys.
 * Unmatched fields are left for the AI structuring step (M1-P2) or manual entry.
 */
class CfdiFieldMapper
{
    /**
     * Common Spanish field-key aliases -> CFDI parser keys.
     * Keys here are template field keys the client is likely to use.
     */
    private const ALIASES = [
        'proveedor'      => 'emisor_nombre',
        'emisor'         => 'emisor_nombre',
        'rfc_emisor'     => 'emisor_rfc',
        'rfc_proveedor'  => 'emisor_rfc',
        'cliente'        => 'receptor_nombre',
        'receptor'       => 'receptor_nombre',
        'rfc_receptor'   => 'receptor_rfc',
        'monto'          => 'total',
        'importe'        => 'total',
        'total'          => 'total',
        'subtotal'       => 'subtotal',
        'uso_cfdi'       => 'uso_cfdi',
        'uso'            => 'uso_cfdi',
        'folio'          => 'folio',
        'serie'          => 'serie',
        'uuid'           => 'uuid',
        'fecha'          => 'fecha',
        'moneda'         => 'moneda',
        'forma_pago'     => 'forma_pago',
        'metodo_pago'    => 'metodo_pago',
    ];

    /**
     * @param  array<string,string>  $cfdi  output of CfdiParser::parse()
     * @return array<string,string>         template field key => value
     */
    public function map(Template $template, array $cfdi): array
    {
        $resolved = [];

        foreach ($template->fields as $field) {
            $key = $field->key;

            // 1. Exact match.
            if (isset($cfdi[$key])) {
                $resolved[$key] = $cfdi[$key];
                continue;
            }

            // 2. Alias.
            if (isset(self::ALIASES[$key]) && isset($cfdi[self::ALIASES[$key]])) {
                $resolved[$key] = $cfdi[self::ALIASES[$key]];
                continue;
            }

            // 3. Fuzzy: field key contained in a CFDI key or vice versa.
            foreach ($cfdi as $cfdiKey => $value) {
                if (str_contains($cfdiKey, $key) || str_contains($key, $cfdiKey)) {
                    $resolved[$key] = $value;
                    break;
                }
            }
        }

        return $resolved;
    }
}
