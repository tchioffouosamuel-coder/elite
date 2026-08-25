<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Seance extends Model
{
    /** Fenêtre de correction de l'appel après son premier enregistrement. */
    public const MINUTES_VERROUILLAGE_APPEL = 15;

    protected $fillable = [
        'school_id', 'classe_id', 'classe_matiere_id', 'trimestre_id', 'emploi_du_temps_id',
        'date_seance', 'heure_debut', 'heure_fin', 'salle', 'contenu', 'statut',
        'observations', 'donnees_personnalisees', 'appel_verrouille_le',
    ];

    protected function casts(): array
    {
        return [
            'date_seance' => 'date',
            'donnees_personnalisees' => 'array',
            'appel_verrouille_le' => 'datetime',
        ];
    }

    /**
     * L'appel reste corrigeable 15 minutes après son premier enregistrement
     * (`appel_verrouille_le`, figé une seule fois) — passé ce délai,
     * l'enseignant doit passer par le Surveillant Général pour toute
     * correction plutôt que de pouvoir réécrire l'historique de pointage.
     */
    public function appelVerrouille(): bool
    {
        return $this->appel_verrouille_le !== null
            && now()->greaterThan($this->appel_verrouille_le->addMinutes(self::MINUTES_VERROUILLAGE_APPEL));
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /** Leçons du programme traitées pendant la séance. */
    public function lecons(): BelongsToMany
    {
        return $this->belongsToMany(ProgressionItem::class, 'lecon_seance')->withTimestamps();
    }

    /** Durée de la séance en heures, base du cumul d'absences. */
    public function emploiDuTemps(): BelongsTo
    {
        return $this->belongsTo(EmploiDuTemps::class);
    }

    /**
     * Classes convoquées à cette séance.
     *
     * Une séance ordinaire n'en concerne qu'une. Une séance née d'un créneau
     * en tronc commun en réunit plusieurs : c'est ce périmètre qui compose la
     * feuille d'appel, et c'est lui qui autorise un pointage.
     *
     * Le repli sur la seule classe porteuse couvre les séances créées à la
     * main, sans créneau d'origine.
     *
     * @return Collection<int, Classe>
     */
    public function classesConcernees(): Collection
    {
        $this->loadMissing('emploiDuTemps.classesAssociees', 'emploiDuTemps.classe', 'classe');

        return $this->emploiDuTemps
            ? $this->emploiDuTemps->toutesLesClasses()
            : collect([$this->classe])->filter()->values();
    }

    public function estTroncCommun(): bool
    {
        return $this->classesConcernees()->count() > 1;
    }

    /**
     * Élèves attendus à l'appel : tous les actifs des classes concernées.
     *
     * @return Collection<int, Eleve>
     */
    public function elevesAttendus(): Collection
    {
        return Eleve::whereIn('classe_id', $this->classesConcernees()->pluck('id'))
            ->where('statut', 'actif')
            ->orderBy('nom_complet')
            ->get();
    }

    public function dureeHeures(): float
    {
        $debut = strtotime($this->heure_debut);
        $fin = strtotime($this->heure_fin);

        return $fin > $debut ? round(($fin - $debut) / 3600, 2) : 0.0;
    }
}
