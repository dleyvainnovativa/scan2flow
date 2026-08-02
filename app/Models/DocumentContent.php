<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentContent extends Model
{
    protected $fillable = ['document_id', 'body', 'source'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
