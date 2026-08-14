<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FonctionReferentiel extends Model
{
    protected $table = 'fonction_referentiel';

    public $timestamps = false;

    protected $fillable = ['school_id', 'label_fr', 'label_en'];

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class, 'fonction_id');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en' && $this->label_en) {
            return $this->label_en;
        }

        return $this->label_fr;
    }
}
