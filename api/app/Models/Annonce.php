<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annonce extends Model
{
    protected $fillable = ['school_id', 'titre', 'contenu', 'publie_par', 'publiee_le', 'cible_type', 'cible_data'];

    protected function casts(): array
    {
        return ['publiee_le' => 'datetime', 'cible_data' => 'array'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /**
     * Annonces que ce compte a le droit de lire, selon leur ciblage :
     * « tous » pour toute l'école, « fonction » pour le seul personnel de ces
     * fonctions, « utilisateurs » pour ces seuls comptes.
     *
     * Celui qui publie (`annonces.publish`) les voit toutes, pour pouvoir les
     * gérer. Sans ce filtre, un parent ou un élève lisait aussi les annonces
     * destinées à une fonction du personnel.
     */
    public function scopeVisiblesPour(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->permissionsEffectives()->contains('annonces.publish')) {
            return $query;
        }

        $fonctionId = $user->personnel?->fonction_id;

        return $query->where(function (Builder $q) use ($user, $fonctionId) {
            $q->whereNull('cible_type')
                ->orWhere('cible_type', 'tous')
                ->orWhere(fn (Builder $u) => $u->where('cible_type', 'utilisateurs')
                    ->whereJsonContains('cible_data', $user->id));

            if ($fonctionId !== null) {
                $q->orWhere(fn (Builder $f) => $f->where('cible_type', 'fonction')
                    ->whereJsonContains('cible_data', (int) $fonctionId));
            }
        });
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'publie_par');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
