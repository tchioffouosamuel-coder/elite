<?php

namespace App\Models;

use App\Services\EcheancierService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une échéance de l'échéancier de scolarité : une part du total dû, et la date
 * à laquelle elle devient exigible.
 *
 * Le montant se calcule au dossier (cf. {@see EcheancierService})
 * plutôt que d'être figé ici : deux écoles n'ont pas la même scolarité, et une
 * remise ou un reliquat déplacent le total d'une famille à l'autre.
 */
class TrancheScolarite extends Model
{
    protected $table = 'tranches_scolarite';

    protected $fillable = [
        'school_id', 'annee_scolaire_id', 'libelle', 'pourcentage', 'date_echeance', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'pourcentage' => 'decimal:2',
            'date_echeance' => 'date',
            'ordre' => 'integer',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Déjà exigible à la date donnée — le délai de grâce s'applique en amont. */
    public function estEchue(\DateTimeInterface $date): bool
    {
        return $this->date_echeance !== null && $this->date_echeance->lessThanOrEqualTo($date);
    }

    /**
     * Jours restants avant l'échéance, négatif si elle est dépassée — à la
     * date d'aujourd'hui, pas celle corrigée par le délai de grâce : c'est le
     * compte à rebours brut affiché aux familles comme au personnel, le délai
     * de grâce ne jouant que sur le moment où un retard devient un impayé.
     *
     * Null sans date d'échéance : l'appelant distingue ainsi « pas de
     * calendrier » d'un simple retard.
     */
    public function joursRestants(?\DateTimeInterface $aujourdHui = null): ?int
    {
        if ($this->date_echeance === null) {
            return null;
        }

        $aujourdHui ??= now();

        // diffInDays(..., false) est signé par rapport à l'appelant : positif
        // quand l'échéance est après aujourd'hui (jours restants), négatif
        // quand elle est déjà passée (jours de retard).
        return (int) CarbonImmutable::parse($aujourdHui)->startOfDay()
            ->diffInDays(CarbonImmutable::parse($this->date_echeance)->startOfDay(), false);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
