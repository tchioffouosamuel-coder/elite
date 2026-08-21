<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un article sorti du stock sur une facture. Le libellé et les prix sont
 * recopiés à la vente : réimprimer une facture doit rendre le document remis
 * à la famille, pas le tarif du jour.
 */
class VenteFournitureLigne extends Model
{
    protected $fillable = [
        'vente_fourniture_id', 'inventaire_article_id', 'libelle',
        'quantite', 'prix_unitaire', 'cout_unitaire',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire' => 'integer',
            'cout_unitaire' => 'integer',
        ];
    }

    public function getTotalAttribute(): int
    {
        return $this->quantite * $this->prix_unitaire;
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(VenteFourniture::class, 'vente_fourniture_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(InventaireArticle::class, 'inventaire_article_id');
    }
}
