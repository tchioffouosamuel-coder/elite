<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/** Un document de la bibliothèque numérique, visible par les écoles rattachées via {@see ecoles()}. */
class BibliothequeDocument extends Model
{
    protected $table = 'bibliotheque_documents';

    protected $fillable = [
        'titre', 'description', 'fichier_path', 'fichier_nom_original', 'taille', 'type_mime', 'uploaded_par',
    ];

    protected function casts(): array
    {
        return ['taille' => 'integer'];
    }

    public function ecoles(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'bibliotheque_document_school');
    }

    public function uploadePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_par');
    }

    public function getFichierUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->fichier_path);
    }

    /** @param int|array<int> $schoolId */
    public function scopeVisiblePour(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('ecoles', fn (Builder $q) => $q->whereIn('schools.id', (array) $schoolId));
    }
}
