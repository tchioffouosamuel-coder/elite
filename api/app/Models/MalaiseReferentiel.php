<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MalaiseReferentiel extends Model
{
    protected $table = 'malaises_referentiel';

    public $timestamps = false;

    protected $fillable = ['school_id', 'label_fr', 'label_en'];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function visites(): BelongsToMany
    {
        return $this->belongsToMany(VisiteInfirmerie::class, 'visite_infirmerie_malaise', 'malaise_referentiel_id', 'visite_infirmerie_id');
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
