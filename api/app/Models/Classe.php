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
        // `code_salle` en est la version courte, saisissable à la main quand
        // le scan n'est pas possible — cf. MaJourneeController::enregistrer().
        static::creating(function (self $classe) {
            $classe->qr_token ??= (string) Str::uuid();
            $classe->code_salle ??= self::genererCodeSalle();
        });
    }

    /** Code à 6 chiffres, jamais réutilisé — pas de confirmation possible entre deux salles. */
    private static function genererCodeSalle(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (self::where('code_salle', $code)->exists());

        return $code;
    }

    protected $fillable = [
        'school_id',
        'niveau_id',
        'niveau_scolaire_id',
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
        'code_salle',
    ];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /**
     * Preuve de présence dans cette salle : le jeton scanné, ou le code
     * saisi à la main — l'un ou l'autre suffit, quelle que soit la méthode
     * assignée à l'agent (cf. `User::methodeValidationSeance()`), qui ne fait
     * que guider l'écran vers l'un ou l'autre plutôt que d'exclure l'autre.
     * Utilisé par `MaJourneeController` et `SeanceController`.
     */
    public function preuvePresenceValide(?string $qrToken, ?string $codeSalle): bool
    {
        $qrValide = ! empty($qrToken) && $this->qr_token !== null && hash_equals($this->qr_token, $qrToken);
        $codeValide = ! empty($codeSalle) && $this->code_salle !== null && hash_equals($this->code_salle, $codeSalle);

        return $qrValide || $codeValide;
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

    /** Compétences attribuées à la classe — primaire et maternelle. */
    public function classeCompetences(): HasMany
    {
        return $this->hasMany(ClasseCompetence::class);
    }

    public function classeMatieres(): HasMany
    {
        return $this->hasMany(ClasseMatiere::class);
    }
}