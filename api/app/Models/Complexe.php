<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groupe d'établissements partageant une même identité (ELITES réunit une
 * maternelle, un primaire et un secondaire). Le super administrateur travaille
 * à ce niveau et bascule d'une école à l'autre.
 */
class Complexe extends Model
{
    protected $fillable = ['name', 'code', 'logo_path', 'address', 'phone', 'email', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
