<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personnel extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'departement_id',
        'fonction_id',
        'matricule',
        'nom_complet',
        'telephone',
        'email',
        'date_embauche',
        'statut',
        'photo_path',
    ];

    protected function casts(): array
    {
        return ['date_embauche' => 'date'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function fonctionReference(): BelongsTo
    {
        return $this->belongsTo(FonctionReferentiel::class, 'fonction_id');
    }

    /**
     * Libelle de la fonction. La colonne texte a cede la place au referentiel,
     * mais tout ce qui affiche une fiche de personnel — exports, fichier du
     * personnel, tableau de bord — continue de lire `->fonction`.
     */
    public function getFonctionAttribute(): ?string
    {
        return $this->fonctionReference?->label();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Classes enseignées au primaire/maternelle (ce personnel est titulaire). */
    public function classesTenues(): HasMany
    {
        return $this->hasMany(Classe::class, 'titulaire_id');
    }
}
