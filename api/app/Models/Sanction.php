<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    /** Reprend l'échelle du conseil de discipline : du simple avertissement au renvoi. */
    public const TYPES = ['avertissement', 'blame', 'corvee', 'exclusion_temporaire', 'exclusion_definitive', 'autre'];

    public const STATUTS = ['en_attente', 'confirmee', 'annulee'];

    /** Libellés français, pour les documents imprimés (PV, bilans) — le frontend a les siens via i18n. */
    public const LIBELLES_TYPES = [
        'avertissement' => 'Avertissement',
        'blame' => 'Blâme',
        'corvee' => 'Corvée',
        'exclusion_temporaire' => 'Exclusion temporaire',
        'exclusion_definitive' => 'Exclusion définitive',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'eleve_id', 'classe_id', 'trimestre_id', 'type', 'duree_jours', 'date_debut', 'date_fin',
        'motif', 'commentaire', 'date_sanction', 'statut', 'impacte_bulletin', 'enregistre_par',
    ];

    protected function casts(): array
    {
        return [
            'date_sanction' => 'date',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'impacte_bulletin' => 'boolean',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('eleve', fn ($q) => is_array($schoolId) ? $q->whereIn('school_id', $schoolId) : $q->where('school_id', $schoolId));
    }

    /**
     * Exclusion (temporaire, en cours de période, ou définitive) confirmée par
     * le conseil : c'est elle qui répond à « cet élève est-il actuellement
     * exclu ? », une simple sanction en attente ou annulée ne comptant pas.
     */
    public function scopeExclusionsActives(Builder $query): Builder
    {
        return $query->where('statut', 'confirmee')
            ->where(function ($q) {
                $q->where('type', 'exclusion_definitive')
                    ->orWhere(fn ($qq) => $qq->where('type', 'exclusion_temporaire')
                        ->whereDate('date_fin', '>=', now()->toDateString()));
            });
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'enregistre_par');
    }
}
