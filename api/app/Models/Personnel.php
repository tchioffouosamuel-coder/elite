<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Personnel extends Model
{
    protected $fillable = [
        'school_id', 'user_id', 'departement_id', 'matricule', 'nom', 'prenom',
        'fonction', 'telephone', 'email', 'date_embauche', 'statut', 'photo_path',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
