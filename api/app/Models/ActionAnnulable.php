<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionAnnulable extends Model
{
    protected $table = 'actions_annulables';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contexte' => 'array', 'changements' => 'array', 'revision' => 'integer'];
    }
}
