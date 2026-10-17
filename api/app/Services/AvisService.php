<?php

namespace App\Services;

use App\Models\Avis;
use App\Models\User;

/**
 * Espace d'évaluation (point 24) : dépôt d'un avis libre par un parent, un
 * enseignant ou un membre du personnel. L'origine se déduit du rôle de
 * l'auteur à la lecture, pas d'une colonne dédiée (cf. {@see Avis}).
 */
class AvisService extends BaseService
{
    public function creer(User $auteur, string $contenu, ?string $sujet, ?int $note): Avis
    {
        return Avis::create([
            'school_id' => $auteur->school_id,
            'user_id' => $auteur->id,
            'sujet' => $sujet,
            'contenu' => $contenu,
            'note' => $note,
        ]);
    }
}
