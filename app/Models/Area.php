<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Area extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::saving(function (Area $area) {
            if (empty($area->slug)) {
                $area->slug = static::uniqueSlug($area->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'area';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** Users granted access to this area, with their per-area permissions. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['can_view', 'can_download', 'can_edit'])
            ->withTimestamps();
    }
}
