<?php

namespace App\Models;

use App\Models\Concerns\FiltreParPerimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eleve extends Model
{
    use FiltreParPerimetre;

    protected $fillable = [
        'school_id',
        'user_id',
        'classe_id',
        'matricule',
        'matricule_national',
        'nom_complet',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'region_origine',
        'departement_origine',
        'numero_acte_naissance',
        'lieu_delivrance_acte',
        'officier_etat_civil',
        'refugie',
        'deplace_interne',
        'bororo',
        'baka',
        'handicap',
        'type_handicap',
        'adresse',
        'photo_path',
        'photo_tenue_path',
        'groupe_sanguin',
        'situation_sanitaire',
        'aptitude',
        'allergies',
        'redoublant',
        'ecole_precedente',
        'statut',
        'alerte_absence_declenchee_le',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'redoublant' => 'boolean',
            'refugie' => 'string',
            'deplace_interne' => 'string',
            'bororo' => 'string',
            'baka' => 'string',
            'handicap' => 'string',
            'alerte_absence_declenchee_le' => 'date:Y-m-d',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /**
     * Restreint aux élèves ayant une préinscription (en attente ou validée)
     * pour l'année scolaire active de leur propre école — même définition
     * que `PreinscriptionService::estPreinscritAnneeActive()`, mais en
     * sous-requête pour filtrer une liste plutôt que tester un élève à la
     * fois. Sert à limiter les listes d'élèves à ceux réellement engagés pour
     * l'année en cours, hors tableau de bord et page des dettes antérieures
     * (qui doivent au contraire voir tout le monde).
     */
    public function scopePreinscritAnneeActive(Builder $query): Builder
    {
        return $query->whereExists(function ($sous) {
            $sous->selectRaw('1')
                ->from('preinscriptions')
                ->whereColumn('preinscriptions.eleve_id', 'eleves.id')
                ->whereIn('preinscriptions.statut', ['en_attente', 'validee'])
                ->whereExists(function ($annee) {
                    $annee->selectRaw('1')
                        ->from('annee_scolaires')
                        ->whereColumn('annee_scolaires.id', 'preinscriptions.annee_scolaire_id')
                        ->whereColumn('annee_scolaires.school_id', 'eleves.school_id')
                        ->where('annee_scolaires.is_active', true);
                });
        });
    }

    /** Format YYELITES-NNNN : année sur deux chiffres puis ordre global. */
    public static function genererMatricule(int $schoolId): string
    {
        $annee = now()->format('y');
        $plusGrandNumero = static::query()
            ->where('matricule', 'like', $annee . 'ELITES-%')
            ->pluck('matricule')
            ->map(fn(?string $matricule) => preg_match('/^\d{2}ELITES-(\d{4})$/', (string) $matricule, $matches) ? (int) $matches[1] : 0)
            ->max();

        return $annee . 'ELITES-' . str_pad((string) ($plusGrandNumero + 1), 4, '0', STR_PAD_LEFT);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Compte de connexion du portail élève, quand l'accès a été ouvert — cf. CompteEleveService. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function tuteurs(): BelongsToMany
    {
        return $this->belongsToMany(Tuteur::class, 'eleve_tuteur')
            ->withPivot(['lien_parente', 'is_principal'])
            ->withTimestamps();
    }

    public function busAffectations(): HasMany
    {
        return $this->hasMany(BusAffectation::class);
    }

    /** Âge en années révolues à la date du jour — nul si la date de naissance n'est pas renseignée. */
    public function getAgeAttribute(): ?int
    {
        return $this->date_naissance?->age;
    }
}