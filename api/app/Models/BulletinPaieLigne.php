<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne d'un bulletin : un gain, ou une retenue a deux parts. */
class BulletinPaieLigne extends Model
{
    protected $fillable = [
        'bulletin_paie_id', 'ordre', 'type', 'libelle', 'libelle_en', 'base',
        'taux_salarial', 'taux_patronal', 'montant_salarial', 'montant_patronal',
    ];

    protected function casts(): array
    {
        return [
            'base' => 'integer',
            'montant_salarial' => 'integer',
            'montant_patronal' => 'integer',
            'taux_salarial' => 'float',
            'taux_patronal' => 'float',
        ];
    }

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(BulletinPaie::class, 'bulletin_paie_id');
    }
}
