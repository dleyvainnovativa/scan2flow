<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singular "Metadatum" so Eloquent's pluralizer maps it to `document_metadata`.
 */
class DocumentMetadatum extends Model
{
    protected $table = 'document_metadata';

    protected $fillable = [
        'document_id', 'template_field_id', 'value', 'value_norm',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(TemplateField::class, 'template_field_id');
    }
}
