<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Trimestre;
use App\Services\BulletinService;
use App\Support\Pdf\MpdfFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BulletinController extends Controller
{
    public function __construct(private readonly BulletinService $service)
    {
    }

    public function show(Request $request, int $eleveId): Response
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->with('classe.school')->findOrFail($eleveId);

        if (! $eleve->classe_id) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $trimestreId = $request->integer('trimestre_id');
        $trimestre = $trimestreId
            ? Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', app('tenant.school_id')))->findOrFail($trimestreId)
            : Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', app('tenant.school_id')))->where('is_active', true)->firstOrFail();

        $donnees = $this->service->donnees($eleve, $trimestre);

        $nomFichier = 'bulletin-'.\Illuminate\Support\Str::slug($eleve->nomComplet()).'-'.$trimestre->libelle.'.pdf';

        return MpdfFactory::streamFromView('pdf.bulletin', $donnees, $nomFichier);
    }
}
