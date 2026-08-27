<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'complexe_id', 'name', 'code', 'type', 'national_school_code', 'logo_path', 'stamp_path', 'signature_path',
        'address', 'phone', 'email', 'header_fr', 'header_en', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function complexe(): BelongsTo
    {
        return $this->belongsTo(Complexe::class);
    }

    /**
     * Au secondaire on note par matière (un enseignant par matière, départements) ;
     * au primaire et en maternelle un unique enseignant tient la classe.
     */
    public function estSecondaire(): bool
    {
        return $this->type === 'secondaire';
    }

    /**
     * La maternelle évalue par appréciation — un visage colorié sur le
     * bulletin — et non par une note chiffrée : c'est ce test qui aiguille la
     * saisie, le bulletin et l'absence de moyenne générale.
     */
    public function estMaternelle(): bool
    {
        return $this->type === 'maternelle';
    }

    public function niveaux(): HasMany
    {
        return $this->hasMany(Niveau::class);
    }

    public function anneesScolaires(): HasMany
    {
        return $this->hasMany(AnneeScolaire::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
