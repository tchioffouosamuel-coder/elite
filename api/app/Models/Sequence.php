<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sequence extends Model
{
    protected $fillable = ['trimestre_id', 'ordre', 'libelle', 'saisie_ouverte'];

    protected $attributes = ['saisie_ouverte' => true];

    protected function casts(): array
    {
        return ['saisie_ouverte' => 'boolean'];
    }

    public function verifierSaisieNotes(User $user, int $schoolId): void
    {
        abort_unless($this->trimestre?->anneeScolaire?->school_id === $schoolId, 403,
            'Cette séquence ne relève pas de cet établissement.');
        if (! $user->peutSaisirHorsTrimestreActif()) {
            abort_unless($this->trimestre->is_active, 403,
                "Ce trimestre n'est plus actif : seule la direction peut encore y modifier des notes.");
        }
        if ($user->estEnseignant() || ! $user->peutSaisirHorsTrimestreActif()) {
            abort_unless($this->saisie_ouverte, 403,
                'La saisie des notes est fermée pour cette séquence.');
        }
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
