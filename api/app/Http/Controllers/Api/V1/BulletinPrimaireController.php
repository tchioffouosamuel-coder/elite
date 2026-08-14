<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Services\BulletinPrimaireService;
use App\Support\Pdf\BulletinPrimaireGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bulletins du primaire et de la maternelle : même découpage que le
 * secondaire (un élève ou toute la classe dans un document unique), mais
 * grille par volets d'évaluation et notation sur barème.
 */
class BulletinPrimaireController extends Controller
{
    public function __construct(private readonly BulletinPrimaireService $service) {}

    public function show(Request $request, int $eleveId): Response
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))
            ->with('classe.school', 'classe.niveauScolaire', 'classe.titulaire')
            ->findOrFail($eleveId);

        if (! $eleve->classe) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $trimestre = $this->trimestre($request);
        $donnees = $this->service->donneesClasse($eleve->classe, $trimestre, [$eleve->id]);

        return $this->pdf($donnees, 'bulletin-'.Str::slug($eleve->nom_complet).'-'.Str::slug($trimestre->libelle));
    }

    public function classe(Request $request, int $classeId): Response
    {
        $classe = Classe::forSchool(app('tenant.school_id'))
            ->with('school', 'niveauScolaire', 'titulaire')
            ->findOrFail($classeId);

        $trimestre = $this->trimestre($request);
        $donnees = $this->service->donneesClasse($classe, $trimestre);

        return $this->pdf($donnees, 'bulletins-'.Str::slug($classe->nom).'-'.Str::slug($trimestre->libelle));
    }

    private function trimestre(Request $request): Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        );

        return ($id = $request->integer('trimestre_id'))
            ? $query->findOrFail($id)
            : $query->where('is_active', true)->firstOrFail();
    }

    private function pdf(array $donnees, string $nom): Response
    {
        return response((new BulletinPrimaireGenerator)->build($donnees), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'.pdf"',
        ]);
    }
}
