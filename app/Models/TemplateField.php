<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateField extends Model
{
    public const TYPES = ['text', 'number', 'date', 'currency', 'select'];

    protected $fillable = [
        'template_id', 'key', 'label', 'type', 'is_required', 'position', 'options',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options'     => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Normalize a raw value for the value_norm column so filtering/sorting is
     * consistent per type. Kept here so Module 1 and the UI use the same rules.
     */
    public function normalize(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($this->type) {
            'number', 'currency' => self::normalizeNumeric($raw),
            'date'               => self::normalizeDate($raw),
            default              => mb_strtolower(trim($raw)),
        };
    }

    private static function normalizeNumeric(string $raw): string
    {
        // Strip currency symbols/commas, keep sign + decimals, zero-pad the
        // integer part to 15 chars so string sorting matches numeric sorting.
        $clean = preg_replace('/[^0-9.\-]/', '', $raw);
        $num = is_numeric($clean) ? (float) $clean : 0.0;
        return sprintf('%019.4f', $num); // 15 int digits + '.' + 4 decimals
    }

    private static function normalizeDate(string $raw): string
    {
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : mb_strtolower(trim($raw));
    }
}
