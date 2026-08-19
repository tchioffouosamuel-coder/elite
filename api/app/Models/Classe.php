<?php

namespace App\Models;

use App\Models\Concerns\FiltreParPerimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classe extends Model
{
    use FiltreParPerimetre;

    /** La classe se rattache à elle-même : c'est sa propre clé qui la situe. */
    protected static function colonneClasse(): string
    {
        return 'id';
    }

    protected static function booted(): void
    {
        // Chaque classe porte un jeton dès sa création : la salle qu'on lui
        // affecte peut ainsi afficher son QR code sans étape supplémentaire.
        static::creating(fn (self $classe) => $classe->qr_token ??= (string) Str::uuid());
    }

    protected $fillable = [
        'school_id',
        'niveau_id',
        'niveau_scolaire_id',
        'annee_scolaire_id',
        'sous_systeme_id',
        'professeur_principal_id',
        'titulaire_id',
        'surveillant_general_id',
        'censeur_id',
        'conseiller_orientation_id',
        'nom',
        'sigle',
        'niveau_classe',
        'filiere',
        'code_examen',
        'capacite',
        'qr_token',
    ];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /** Niveau d'enseignement (SIL, CP, CE1…) — primaire et maternelle. */
    public function niveauScolaire(): BelongsTo
    {
        return $this->belongsTo(NiveauScolaire::class);
    }

    /** Enseignant unique de la classe au primaire/maternelle. */
    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'titulaire_id');
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'professeur_principal_id');
    }

    public function surveillantGeneral(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'surveillant_general_id');
    }

    public function censeur(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'censeur_id');
    }

    public function conseillerOrientation(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'conseiller_orientation_id');
    }

    public function sousSysteme(): BelongsTo
    {
        return $this->belongsTo(SousSysteme::class);
    }

    public function emploiDuTemps(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    public function eleves(): HasMany
    {
        return $this->hasMany(Eleve::class);
    }

    public function classeMatieres(): HasMany
    {
        return $this->hasMany(ClasseMatiere::class);
    }
}
