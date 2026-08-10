<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Niveau extends Model
{
    public $timestamps = true;

    protected $fillable = ['code', 'name_fr', 'name_en', 'ordre'];

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_niveau');
    }
}
