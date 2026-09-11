<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\BusVersement;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Models\Versement;
use App\Services\BulletinService;
use App\Support\SignatureBulletin;
use App\Support\SignatureEmploiDuTemps;
use App\Support\SignatureVersement;
use App\Support\SignatureVersementBus;
use Illuminate\Http\JsonResponse;

/**
 * Point d'entrée unique de vérification publique (QR code), pour l'app
 * mobile : chaque type de document (bulletin, versement, versement bus,
 * emploi du temps) garde sa propre route `verification-*` — c'est
 * exactement ce qu'encode le QR imprimé sur le document — mais cette route
 * délègue à la route existante correspondante puis aplatit le résultat dans
 * une forme unique (`statut`, `type_document`, `nom_eleve`, ...), pour que le
 * client mobile n'ait qu'un seul contrat de réponse à consommer quel que
 * soit le type de document scanné.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly BulletinService $bulletinService) {}

    public function show(string $path): JsonResponse
    {
        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));
        $type = array_shift($segments);

        return match ($type) {
            'verification-bulletin' => $this->bulletin($segments),
            'verification-versement' => $this->versement($segments),
            'verification-versement-bus' => $this->versementBus($segments),
            'verification-emploi-du-temps' => $this->emploiDuTemps($segments),
            default => ApiResponse::notFound('Type de document inconnu.'),
        };
    }

    private function bulletin(array $segments): JsonResponse
    {
        if (count($segments) !== 3) {
            return ApiResponse::notFound('Bulletin introuvable.');
        }
        [$eleveId, $trimestreId, $signature] = $segments;

        $eleve = Eleve::with('classe.school')->find((int) $eleveId);
        $trimestre = Trimestre::find((int) $trimestreId);

        if (! $eleve || ! $trimestre) {
            return ApiResponse::notFound('Bulletin introuvable.');
        }

        if (! SignatureBulletin::verifier((int) $eleveId, (int) $trimestreId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun bulletin authentique.', 422);
        }

        $donnees = $this->bulletinService->donneesClasse($eleve->classe, $trimestre, [$eleve->id]);
        $bulletin = $donnees['eleves'][0] ?? null;

        if (! $bulletin) {
            return ApiResponse::notFound('Bulletin introuvable.');
        }

        return ApiResponse::success([
            'statut' => 'valide',
            'type_document' => 'Bulletin scolaire',
            'nom_eleve' => $eleve->nom_complet,
            'classe' => $eleve->classe->nom,
            'etablissement' => $eleve->classe->school->name,
            'annee_scolaire' => $donnees['annee']?->libelle ?? '-',
            'genere_le' => now()->toDateString(),
            'valide_jusqu_au' => null,
            'motif_revocation' => null,
        ]);
    }

    private function versement(array $segments): JsonResponse
    {
        if (count($segments) !== 2) {
            return ApiResponse::notFound('Reçu introuvable.');
        }
        [$versementId, $signature] = $segments;

        if (! SignatureVersement::verifier((int) $versementId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun reçu authentique.', 422);
        }

        $versement = Versement::with(['dossier.eleve.classe', 'dossier.school', 'dossier.anneeScolaire'])->find((int) $versementId);

        if (! $versement) {
            return ApiResponse::notFound('Reçu introuvable.');
        }

        $dossier = $versement->dossier;

        return ApiResponse::success([
            'statut' => $versement->annule_le !== null ? 'revoque' : 'valide',
            'type_document' => 'Reçu de versement',
            'nom_eleve' => $dossier->eleve->nom_complet,
            'classe' => $dossier->eleve->classe?->nom ?? '-',
            'etablissement' => $dossier->school->name,
            'annee_scolaire' => $dossier->anneeScolaire?->libelle ?? '-',
            'genere_le' => $versement->date_versement->toDateString(),
            'valide_jusqu_au' => null,
            'motif_revocation' => $versement->annule_le !== null ? 'Reçu annulé.' : null,
        ]);
    }

    private function versementBus(array $segments): JsonResponse
    {
        if (count($segments) !== 2) {
            return ApiResponse::notFound('Reçu introuvable.');
        }
        [$versementId, $signature] = $segments;

        if (! SignatureVersementBus::verifier((int) $versementId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun reçu authentique.', 422);
        }

        $versement = BusVersement::with(['affectation.eleve.classe', 'affectation.trajet.school', 'affectation.anneeScolaire'])->find((int) $versementId);

        if (! $versement) {
            return ApiResponse::notFound('Reçu introuvable.');
        }

        $affectation = $versement->affectation;

        return ApiResponse::success([
            'statut' => $versement->annule_le !== null ? 'revoque' : 'valide',
            'type_document' => 'Reçu de transport scolaire',
            'nom_eleve' => $affectation->eleve->nom_complet,
            'classe' => $affectation->eleve->classe?->nom ?? '-',
            'etablissement' => $affectation->trajet->school?->name ?? '-',
            'annee_scolaire' => $affectation->anneeScolaire?->libelle ?? '-',
            'genere_le' => $versement->date_versement->toDateString(),
            'valide_jusqu_au' => null,
            'motif_revocation' => $versement->annule_le !== null ? 'Reçu annulé.' : null,
        ]);
    }

    private function emploiDuTemps(array $segments): JsonResponse
    {
        if (count($segments) !== 3) {
            return ApiResponse::notFound('Document introuvable.');
        }
        [$classeId, $anneeId, $signature] = $segments;

        $classe = Classe::with('school')->find((int) $classeId);
        $annee = $classe ? AnneeScolaire::where('school_id', $classe->school_id)->find((int) $anneeId) : null;

        if (! $classe || ! $annee) {
            return ApiResponse::notFound('Document introuvable.');
        }

        if (! SignatureEmploiDuTemps::verifier((int) $classeId, (int) $anneeId, $signature)) {
            return ApiResponse::error('Signature invalide : ce lien ne correspond à aucun emploi du temps authentique.', 422);
        }

        return ApiResponse::success([
            'statut' => 'valide',
            'type_document' => 'Emploi du temps',
            'nom_eleve' => '-',
            'classe' => $classe->nom,
            'etablissement' => $classe->school?->name ?? '-',
            'annee_scolaire' => $annee->libelle,
            'genere_le' => now()->toDateString(),
            'valide_jusqu_au' => null,
            'motif_revocation' => null,
        ]);
    }
}
