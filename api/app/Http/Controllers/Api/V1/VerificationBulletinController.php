<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Services\BulletinService;
use Illuminate\Http\JsonResponse;

/**
 * Vérification publique d'authenticité d'un bulletin, via le QR code imprimé
 * dessus — accessible sans authentification puisque c'est un tiers externe
 * (employeur, autre établissement) qui scanne, pas un utilisateur de la
 * plateforme. Ne renvoie donc que des données déjà visibles sur le bulletin
 * lui-même : aucune coordonnée de tuteur ni information sensible.
 */
class VerificationBulletinController extends Controller
{
    public function __construct(private readonly BulletinService $service) {}

    public function show(int $eleveId, int $trimestreId, string $signature): JsonResponse
    {
        $eleve = Eleve::with('classe.school')->find($eleveId);
        $trimestre = Trimestre::find($trimestreId);

        if (! $eleve || ! $trimestre) {
            return ApiResponse::notFound('Bulletin introuvable.');
        }

        if (! \App\Support\SignatureBulletin::verifier($eleveId, $trimestreId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun bulletin authentique.', 422);
        }

        $donnees = $this->service->donneesClasse($eleve->classe, $trimestre, [$eleve->id]);
        $bulletin = $donnees['eleves'][0] ?? null;

        if (! $bulletin) {
            return ApiResponse::notFound('Bulletin introuvable.');
        }

        return ApiResponse::success([
            'eleve' => [
                'nom_complet' => $eleve->nom_complet,
                'matricule' => $eleve->matricule,
            ],
            'classe' => $eleve->classe->nom,
            'ecole' => $eleve->classe->school->name,
            'trimestre' => $trimestre->libelle,
            'annee_scolaire' => $donnees['annee']?->libelle,
            'moyenne_generale' => $bulletin['moyenne_generale'],
            'rang' => $bulletin['rang'],
            'effectif' => $donnees['effectif']['total'],
            'cote' => $bulletin['cote'],
            'appreciation' => $bulletin['appreciation'],
        ]);
    }
}
