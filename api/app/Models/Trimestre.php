<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trimestre extends Model
{
    protected $fillable = ['annee_scolaire_id', 'libelle', 'ordre', 'date_debut', 'date_fin', 'is_active'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class);
    }

    /**
     * Séquences effectivement prises en compte pour les moyennes et bulletins.
     *
     * Les séquences d'un trimestre sont créées une fois pour toutes (à la
     * création du trimestre, d'après `num_sequences` à cet instant) et ne
     * sont jamais supprimées automatiquement : baisser le réglage après coup
     * laisserait sinon des séquences excédentaires en base, avec parfois des
     * notes déjà saisies dessus. Plutôt que de les effacer (perte de saisie),
     * on se limite ici aux `num_sequences` premières par ordre : le réglage
     * redevient la source de vérité pour tout calcul sans jamais supprimer de
     * données.
     *
     * @return Collection<int, Sequence>
     */
    public function sequencesRetenues(): Collection
    {
        $schoolId = $this->anneeScolaire?->school_id;
        $nombre = $schoolId ? (int) Setting::get($schoolId, 'num_sequences', 2) : PHP_INT_MAX;

        return $this->sequences()->orderBy('ordre')->take(max($nombre, 1))->get();
    }
}
