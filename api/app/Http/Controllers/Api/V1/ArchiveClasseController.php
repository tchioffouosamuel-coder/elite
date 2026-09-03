<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\ArchiveClasseAnnee;
use App\Services\ArchivageService;
use App\Support\Pdf\BulletinArchiveGenerator;
use App\Support\Pdf\ProcesVerbalConseilGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consultation des archives pédagogiques d'années révolues — lecture seule,
 * jamais recalculée depuis les tables vivantes (cf. {@see ArchivageService}).
 */
class ArchiveClasseController extends Controller
{
    public function __construct(private readonly ArchivageService $archivage) {}

    /** Années intégralement archivées de l'école. */
    public function annees(): JsonResponse
    {
        $annees = AnneeScolaire::whereIn('school_id', Tenant::schoolIds())
            ->whereNotNull('archivee_le')
            ->orderByDesc('date_debut')
            ->get(['id', 'libelle', 'date_debut', 'date_fin', 'archivee_le']);

        return ApiResponse::success($annees);
    }

    /** Classes archivées d'une année donnée. */
    public function classes(int $anneeId): JsonResponse
    {
        $annee = AnneeScolaire::whereIn('school_id', Tenant::schoolIds())->findOrFail($anneeId);

        $classes = ArchiveClasseAnnee::forSchool(Tenant::schoolIds())
            ->where('annee_scolaire_id', $annee->id)
            ->orderBy('classe_nom')
            ->get(['id', 'classe_id', 'classe_nom', 'niveau_libelle', 'effectif']);

        return ApiResponse::success($classes);
    }

    /** Détail d'une classe archivée : roster (avec décisions), sans le détail lourd notes/absences (téléchargé à part en PDF). */
    public function show(int $anneeId, int $classeId): JsonResponse
    {
        $archive = ArchiveClasseAnnee::forSchool(Tenant::schoolIds())
            ->where('annee_scolaire_id', $anneeId)->where('classe_id', $classeId)
            ->firstOrFail();

        return ApiResponse::success([
            'id' => $archive->id,
            'classe_nom' => $archive->classe_nom,
            'niveau_libelle' => $archive->niveau_libelle,
            'effectif' => $archive->effectif,
            'roster' => $archive->roster_json,
            'absences' => $archive->absences_json,
            'discipline' => $archive->discipline_json,
            'infirmerie' => $archive->infirmerie_json,
        ]);
    }

    public function bulletin(int $anneeId, int $classeId, int $eleveId): Response
    {
        $archive = ArchiveClasseAnnee::forSchool(Tenant::schoolIds())
            ->where('annee_scolaire_id', $anneeId)->where('classe_id', $classeId)
            ->firstOrFail();

        $donnees = $this->archivage->donneesBulletinArchive($archive, $eleveId);
        abort_if($donnees === null, 404, "Cet élève n'apparaît pas dans cette classe archivée.");

        $pdf = (new BulletinArchiveGenerator)->build($donnees);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bulletin-archive-'.$eleveId.'.pdf"',
        ]);
    }

    public function pv(int $anneeId, int $classeId): Response
    {
        $archive = ArchiveClasseAnnee::forSchool(Tenant::schoolIds())
            ->where('annee_scolaire_id', $anneeId)->where('classe_id', $classeId)
            ->with(['school', 'classe', 'anneeScolaire', 'conseilClasse.classeDestination', 'conseilClasse.decisions.eleve'])
            ->firstOrFail();

        abort_if($archive->conseilClasse === null, 404, "Aucun conseil de classe n'a produit cette archive.");

        $conseil = $archive->conseilClasse;
        $decisions = $conseil->decisions;
        $ligne = fn ($d) => [
            'nom_complet' => $d->eleve->nom_complet,
            'matricule' => $d->eleve->matricule,
            'moyenne_annuelle' => $d->moyenne_annuelle,
            'gracie' => $d->gracie,
            'motif' => $d->motif,
        ];

        $pdf = (new ProcesVerbalConseilGenerator)->build([
            'school' => $archive->school,
            'classe' => $archive->classe ?? $conseil->classe,
            'annee' => $archive->anneeScolaire,
            'seuil_moyenne' => (float) $conseil->seuil_moyenne,
            'motif_seuil' => $conseil->motif_seuil,
            'classe_destination' => $conseil->classeDestination?->nom,
            'valide_le' => $conseil->valide_le?->format('d/m/Y'),
            'admis' => $decisions->where('decision_finale', 'admis')->map($ligne)->values()->all(),
            'redoublants' => $decisions->where('decision_finale', 'redouble')->map($ligne)->values()->all(),
            'exclus' => $decisions->where('decision_finale', 'exclu')->map($ligne)->values()->all(),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pv-archive-'.$archive->classe_nom.'.pdf"',
        ]);
    }
}
