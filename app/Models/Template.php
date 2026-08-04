<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Concerns\BelongsToTenant;

class Template extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'area_id',
        'name',
        'slug',
        'description',
        'input_folder_path',
        'naming_rule',
        'ai_enabled'
    ];

    protected static function booted(): void
    {
        static::saving(function (Template $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name) ?: 'plantilla';
            }
        });
    }

    protected $casts = ['ai_enabled' => 'boolean'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
