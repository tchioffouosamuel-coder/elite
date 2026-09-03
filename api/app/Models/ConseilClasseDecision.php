<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Décision d'un conseil de classe pour un élève donné. `decision_defaut` est
 * le calcul automatique (moyenne annuelle vs seuil) ; `decision_finale` est
 * ce qui s'applique réellement une fois les ajustements du conseil pris en
 * compte — les deux sont conservées pour que « gracié » (defaut=redouble,
 * finale=admis) reste distinguable d'un « admis » ordinaire.
 */
class ConseilClasseDecision extends Model
{
    protected $fillable = [
        'conseil_classe_id', 'eleve_id', 'moyenne_annuelle', 'decision_defaut', 'decision_finale', 'gracie', 'motif',
    ];

    protected function casts(): array
    {
        return [
            'moyenne_annuelle' => 'float',
            'gracie' => 'boolean',
        ];
    }

    public function conseilClasse(): BelongsTo
    {
        return $this->belongsTo(ConseilClasse::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
