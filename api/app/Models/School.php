<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'logo_path', 'stamp_path', 'signature_path',
        'address', 'phone', 'email', 'header_fr', 'header_en', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'school_niveau');
    }

    public function anneesScolaires(): HasMany
    {
        return $this->hasMany(AnneeScolaire::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
