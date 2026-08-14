<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Niveau extends Model
{
    public $timestamps = true;

    protected $fillable = ['code', 'name_fr', 'name_en', 'sous_system_id', 'school_id'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function sousSysteme(): BelongsTo
    {
        return $this->belongsTo(SousSysteme::class);
    }
}
