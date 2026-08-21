<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisiteInfirmerieMateriel extends Model
{
    protected $table = 'visite_infirmerie_materiels';

    protected $fillable = [
        'visite_infirmerie_id',
        'inventaire_article_id',
        'nom',
        'quantite',
        'cout_unitaire',
        'cout',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'cout_unitaire' => 'integer',
            'cout' => 'integer',
        ];
    }

    public function visite(): BelongsTo
    {
        return $this->belongsTo(VisiteInfirmerie::class, 'visite_infirmerie_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(InventaireArticle::class, 'inventaire_article_id');
    }
}
