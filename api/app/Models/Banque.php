<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Banque extends Model
{
    protected $fillable = ['nom', 'code', 'numero_compte_ecole'];

    protected function casts(): array
    {
        return ['solde' => 'integer'];
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class, 'banque_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(BanqueMouvement::class);
    }
}
