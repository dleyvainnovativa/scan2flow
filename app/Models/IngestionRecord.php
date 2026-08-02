<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestionRecord extends Model
{
    protected $fillable = [
        'template_id', 'document_id', 'base_name',
        'source_pdf_path', 'source_xml_path',
        'status', 'error', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
