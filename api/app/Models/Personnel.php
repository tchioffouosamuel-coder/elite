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
        'affectation',
        'matricule',
        'nom_complet',
        'civilite',
        'sexe',
        'date_naissance',
        'numero_cni',
        'numero_cnps',
        'departement_origine',
        'residence',
        'telephone',
        'telephone_2',
        'numero_permis',
        'situation_matrimoniale',
        'nombre_enfants',
        'diplome_professionnel',
        'diplome_academique',
        'email',
        'date_embauche',
        'date_fin',
        'date_retraite',
        'statut',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'date_embauche' => 'date',
            'date_naissance' => 'date',
            'date_fin' => 'date',
            'date_retraite' => 'date',
            'nombre_enfants' => 'integer',
        ];
    }

    /**
     * Départ à la retraite à 60 ans quand la date n'est pas saisie — c'est
     * l'âge retenu par le tableau de mise en place, et le calculer évite de
     * laisser la colonne vide sur les deux tiers des fiches.
     */
    public function getDateRetraiteCalculeeAttribute(): ?string
    {
        return $this->date_retraite?->format('Y-m-d')
            ?? $this->date_naissance?->addYears(60)->format('Y-m-d');
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
