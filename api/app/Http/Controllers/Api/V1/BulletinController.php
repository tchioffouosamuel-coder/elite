<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BulletinPublication;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Services\BulletinService;
use App\Services\NotificationService;
use App\Support\Pdf\BulletinGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BulletinController extends Controller
{
    public function __construct(
        private readonly BulletinService $service,
        private readonly NotificationService $notifications,
    ) {}

    /** Bulletin d'un seul élève (le reste de la classe sert au calcul des rangs). */
    public function show(Request $request, int $eleveId): Response
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->with('classe.school')->findOrFail($eleveId);

        if (! $eleve->classe) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $trimestre = $this->trimestre($request, $eleve->classe->school_id);
        $donnees = $this->service->donneesClasse($eleve->classe, $trimestre, [$eleve->id]);

        return $this->pdf($donnees, 'bulletin-'.Str::slug($eleve->nom_complet).'-'.Str::slug($trimestre->libelle));
    }

    /**
     * Bulletins de toute la classe dans un document unique — un élève par page,
     * comme report_cards_single.php dans _smapp.
     */
    public function classe(Request $request, int $classeId): Response
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);
        $trimestre = $this->trimestre($request, $classe->school_id);

        $donnees = $this->service->donneesClasse($classe, $trimestre);

        return $this->pdf($donnees, 'bulletins-'.Str::slug($classe->nom).'-'.Str::slug($trimestre->libelle));
    }

    /**
     * Marque les bulletins d'une classe comme disponibles pour le trimestre
     * courant (ou celui demandé) et notifie le personnel — une seule fois par
     * couple classe/trimestre, la publication ne se rejoue pas.
     */
    public function publier(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        $trimestre = $this->trimestre($request, $classe->school_id);

        $publication = BulletinPublication::firstOrCreate(
            ['trimestre_id' => $trimestre->id, 'classe_id' => $classe->id],
            [
                'school_id' => $classe->school_id,
                'publie_par' => $request->user()->personnel?->id,
                'publie_le' => now(),
            ],
        );

        if ($publication->wasRecentlyCreated) {
            $this->notifications->notifierParPermission(
                (int) $classe->school_id,
                'bulletins.view',
                'resultat_disponible',
                'Résultats disponibles',
                "Les bulletins du {$trimestre->libelle} pour la classe {$classe->nom} sont disponibles.",
            );
        }

        return ApiResponse::success($publication, 'Bulletins publiés.');
    }

    private function trimestre(Request $request, int $schoolId): Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', $schoolId)
        );

        return ($id = $request->integer('trimestre_id'))
            ? $query->findOrFail($id)
            : $query->where('is_active', true)->firstOrFail();
    }

    private function pdf(array $donnees, string $nom): Response
    {
        return response((new BulletinGenerator)->build($donnees), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'.pdf"',
        ]);
    }
}
