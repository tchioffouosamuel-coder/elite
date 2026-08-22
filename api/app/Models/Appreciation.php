<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Niveau d'appréciation de la maternelle : le visage que l'enseignante coche,
 * et la couleur dont la case du bulletin se remplit.
 *
 * Le référentiel est propre à l'établissement — libellé, émoji, couleur et
 * ordre se règlent depuis l'application. `ordre` fixe la colonne : c'est lui
 * qui rend le bulletin lisible de la même façon d'un trimestre à l'autre.
 */
class Appreciation extends Model
{
    /**
     * Niveaux d'usage, du plus favorable au moins favorable. Sert à doter une
     * école qui n'a pas encore de référentiel — la migration fait de même pour
     * celles qui existaient déjà.
     */
    public const DEFAUTS = [
        ['label_fr' => 'Acquis', 'label_en' => 'Acquired', 'emoji' => '🙂', 'couleur' => '#16a34a', 'ordre' => 1],
        ['label_fr' => "En cours d'acquisition", 'label_en' => 'In progress', 'emoji' => '😐', 'couleur' => '#f59e0b', 'ordre' => 2],
        ['label_fr' => 'Non acquis', 'label_en' => 'Not acquired', 'emoji' => '🙁', 'couleur' => '#dc2626', 'ordre' => 3],
    ];

    protected $fillable = [
        'school_id', 'label_fr', 'label_en', 'emoji', 'couleur', 'ordre', 'statut',
    ];

    protected function casts(): array
    {
        return ['ordre' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', 'actif');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
