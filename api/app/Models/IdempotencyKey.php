<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['cle', 'user_id', 'empreinte', 'statut_http', 'reponse', 'expire_le'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime'];
    }
}
