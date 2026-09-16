<?php

namespace App\Models;

use App\Services\DemandeArticleInventaireService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'ajout d'un article d'inventaire soumise par un employé, en
 * attente d'examen par un titulaire de `inventaire.manage`.
 *
 * @see DemandeArticleInventaireService pour la validation (qui
 *      crée l'article réel via InventaireService) et le rejet.
 */
class DemandeArticleInventaire extends Model
{
    protected $table = 'demandes_articles_inventaire';

    protected $fillable = [
        'school_id',
        'personnel_id',
        'donnees',
        'statut',
        'motif_rejet',
        'inventaire_article_id',
        'traite_par',
        'traite_le',
    ];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
            'traite_le' => 'datetime',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function inventaireArticle(): BelongsTo
    {
        return $this->belongsTo(InventaireArticle::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
