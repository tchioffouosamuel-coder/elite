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
        'classe_id',
        'matricule',
        'nom_complet',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'numero_acte_naissance',
        'lieu_delivrance_acte',
        'officier_etat_civil',
        'refugie',
        'deplace_interne',
        'bororo',
        'baka',
        'adresse',
        'photo_path',
        'photo_tenue_path',
        'groupe_sanguin',
        'situation_sanitaire',
        'aptitude',
        'allergies',
        'redoublant',
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
            'alerte_absence_declenchee_le' => 'date:Y-m-d',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /**
     * Format YYCDXXX : année d'inscription sur 2 chiffres, cycle de
     * l'établissement (MAT/PRIM/SEC), puis un compteur séquentiel sur 3
     * chiffres — repris du dernier matricule attribué à ce cycle cette
     * année-là plutôt que d'un COUNT, pour ne pas réutiliser un numéro après
     * suppression d'un élève.
     */
    public static function genererMatricule(int $schoolId): string
    {
        $codes = ['maternelle' => 'MAT', 'primaire' => 'PRIM', 'secondaire' => 'SEC'];
        $code = $codes[School::find($schoolId)?->type] ?? 'SEC';
        $prefixe = now()->format('y').$code;

        $dernier = static::where('school_id', $schoolId)
            ->where('matricule', 'like', $prefixe.'%')
            ->orderByDesc('matricule')
            ->value('matricule');

        $prochain = $dernier ? ((int) substr($dernier, strlen($prefixe)) + 1) : 1;

        return $prefixe.str_pad((string) $prochain, 3, '0', STR_PAD_LEFT);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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
