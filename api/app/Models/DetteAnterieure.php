<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetteAnterieure extends Model
{
    protected $table = 'dettes_anterieures';

    protected $fillable = [
        'school_id',
        'eleve_id',
        'montant',
        'motif',
        'accorde_par',
        'imputee_dossier_id',
    ];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Pas encore reprise dans le report_dette d'un dossier — c'est ce qui reste à imputer. */
    public function scopeNonImputees(Builder $query): Builder
    {
        return $query->whereNull('imputee_dossier_id');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function accordePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accorde_par');
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierScolarite::class, 'imputee_dossier_id');
    }
}
