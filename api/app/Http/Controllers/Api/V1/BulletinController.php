<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Services\BulletinService;
use App\Support\Pdf\BulletinGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BulletinController extends Controller
{
    public function __construct(private readonly BulletinService $service) {}

    /** Bulletin d'un seul élève (le reste de la classe sert au calcul des rangs). */
    public function show(Request $request, int $eleveId): Response
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->with('classe.school')->findOrFail($eleveId);

        if (! $eleve->classe) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $trimestre = $this->trimestre($request);
        $donnees = $this->service->donneesClasse($eleve->classe, $trimestre, [$eleve->id]);

        return $this->pdf($donnees, 'bulletin-'.Str::slug($eleve->nomComplet()).'-'.Str::slug($trimestre->libelle));
    }

    /**
     * Bulletins de toute la classe dans un document unique — un élève par page,
     * comme report_cards_single.php dans _smapp.
     */
    public function classe(Request $request, int $classeId): Response
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->with('school')->findOrFail($classeId);
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
        return response((new BulletinGenerator)->build($donnees), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'.pdf"',
        ]);
    }
}
