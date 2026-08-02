<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    protected $fillable = [
        'area_id',
        'template_id',
        'title',
        'pdf_path',
        'xml_path',
        'ocr_status',
        'page_count',
        'status',
        'uploaded_by',
        'rejection_reason',              // >>> ADD
        'reviewed_at',
        'reviewed_by',                             // >>> ADD
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(DocumentMetadatum::class);
    }

    /** Extracted OCR / text-layer content (one row per document). */
    public function content(): HasOne
    {
        return $this->hasOne(DocumentContent::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
