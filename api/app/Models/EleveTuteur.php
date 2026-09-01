<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne du pivot `eleve_tuteur`, exposée comme modèle à part entière pour le
 * registre de synchronisation ({@see \App\Support\Sync\RegistreSync}) : la
 * relation `Eleve::tuteurs()`/`Tuteur::eleves()` (`BelongsToMany`) suffit à
 * l'application web, mais le registre a besoin d'une classe de modèle
 * interrogeable directement, comme toute autre entité du catalogue.
 */
class EleveTuteur extends Model
{
    protected $table = 'eleve_tuteur';

    protected $fillable = ['eleve_id', 'tuteur_id', 'lien_parente', 'is_principal'];

    protected $casts = ['is_principal' => 'boolean'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }
}
