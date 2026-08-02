<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use App\Models\TemplateField;

/**
 * Centralizes writing document metadata into the EAV table so both the manual
 * upload flow (Phase 3) and Module 1's ingestion use identical normalization.
 */
class MetadataService
{
    /**
     * Persist a map of field key => raw value for a document.
     * Unknown keys are ignored; missing fields are simply not written.
     *
     * @param  array<string,string|null>  $values  keyed by TemplateField.key
     */
    public function sync(Document $document, array $values): void
    {
        $fields = $document->template->fields()->get()->keyBy('key');

        foreach ($values as $key => $raw) {
            $field = $fields->get($key);
            if (! $field) {
                continue;
            }

            $raw = is_string($raw) ? trim($raw) : $raw;

            $document->metadata()->updateOrCreate(
                ['template_field_id' => $field->id],
                [
                    'value'      => $raw !== '' ? $raw : null,
                    'value_norm' => $field->normalize($raw),
                ]
            );
        }
    }

    /**
     * Validate that required fields have values. Returns an array of
     * [key => message] for any missing required field (empty if all good).
     *
     * @param  array<string,string|null>  $values
     * @return array<string,string>
     */
    public function missingRequired(Template $template, array $values): array
    {
        $errors = [];

        foreach ($template->fields as $field) {
            if ($field->is_required) {
                $v = $values[$field->key] ?? null;
                if ($v === null || trim((string) $v) === '') {
                    $errors[$field->key] = "El campo «{$field->label}» es obligatorio.";
                }
            }
        }

        return $errors;
    }
}
