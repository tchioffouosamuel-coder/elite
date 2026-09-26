<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BanqueMouvement extends Model
{
    protected $fillable = [
        'banque_id',
        'type',
        'montant',
        'date_mouvement',
        'libelle',
        'reference',
        'cle_idempotence',
        'bulletin_paie_id',
        'effectue_par',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_mouvement' => 'date',
        ];
    }

    public function banque(): BelongsTo
    {
        return $this->belongsTo(Banque::class);
    }

    public function bulletinPaie(): BelongsTo
    {
        return $this->belongsTo(BulletinPaie::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectue_par');
    }
}
