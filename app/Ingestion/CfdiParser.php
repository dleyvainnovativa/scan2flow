<?php

namespace App\Ingestion;

use SimpleXMLElement;
use Throwable;

/**
 * Parses a CFDI 4.0 XML into a flat map of well-known keys. This is the primary
 * metadata source for CFDI documents (the XML is authoritative).
 *
 * Returned keys (when present in the XML):
 *   folio, serie, uuid, fecha, total, subtotal, moneda, tipo_comprobante,
 *   forma_pago, metodo_pago, uso_cfdi,
 *   emisor_rfc, emisor_nombre, receptor_rfc, receptor_nombre
 *
 * The ingestion mapper aligns these to a template's field keys (e.g. a field
 * keyed `proveedor` maps from `emisor_nombre`, `monto` from `total`).
 */
class CfdiParser
{
    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';
    private const NS_TFD  = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    /**
     * @return array<string,string>  parsed values (empty array if not a CFDI 4.0)
     */
    public function parse(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);
        } catch (Throwable $e) {
            return [];
        }

        $namespaces = $xml->getNamespaces(true);
        // Confirm this is a CFDI 4.0 document.
        if (! in_array(self::NS_CFDI, $namespaces, true)) {
            return [];
        }

        $out = [];
        $comprobante = $xml->attributes(); // root Comprobante attributes (no ns)

        $this->put($out, 'folio', $comprobante['Folio'] ?? null);
        $this->put($out, 'serie', $comprobante['Serie'] ?? null);
        $this->put($out, 'fecha', $comprobante['Fecha'] ?? null);
        $this->put($out, 'total', $comprobante['Total'] ?? null);
        $this->put($out, 'subtotal', $comprobante['SubTotal'] ?? null);
        $this->put($out, 'moneda', $comprobante['Moneda'] ?? null);
        $this->put($out, 'tipo_comprobante', $comprobante['TipoDeComprobante'] ?? null);
        $this->put($out, 'forma_pago', $comprobante['FormaPago'] ?? null);
        $this->put($out, 'metodo_pago', $comprobante['MetodoPago'] ?? null);

        // Emisor / Receptor live in the cfdi namespace.
        $cfdi = $xml->children(self::NS_CFDI);

        if (isset($cfdi->Emisor)) {
            $emisor = $cfdi->Emisor->attributes();
            $this->put($out, 'emisor_rfc', $emisor['Rfc'] ?? null);
            $this->put($out, 'emisor_nombre', $emisor['Nombre'] ?? null);
        }

        if (isset($cfdi->Receptor)) {
            $receptor = $cfdi->Receptor->attributes();
            $this->put($out, 'receptor_rfc', $receptor['Rfc'] ?? null);
            $this->put($out, 'receptor_nombre', $receptor['Nombre'] ?? null);
            $this->put($out, 'uso_cfdi', $receptor['UsoCFDI'] ?? null);
        }

        // UUID from the Timbre Fiscal Digital in the Complemento.
        $uuid = $this->extractUuid($xml, $namespaces);
        if ($uuid) {
            $out['uuid'] = $uuid;
        }

        return $out;
    }

    /** Locate the TFD UUID regardless of complemento nesting. */
    private function extractUuid(SimpleXMLElement $xml, array $namespaces): ?string
    {
        if (! in_array(self::NS_TFD, $namespaces, true)) {
            return null;
        }

        // XPath into the TimbreFiscalDigital node.
        $xml->registerXPathNamespace('tfd', self::NS_TFD);
        $nodes = $xml->xpath('//tfd:TimbreFiscalDigital');
        if ($nodes && isset($nodes[0])) {
            $uuid = (string) ($nodes[0]->attributes()['UUID'] ?? '');
            return $uuid !== '' ? $uuid : null;
        }
        return null;
    }

    private function put(array &$out, string $key, $value): void
    {
        $value = $value !== null ? trim((string) $value) : '';
        if ($value !== '') {
            $out[$key] = $value;
        }
    }
}
