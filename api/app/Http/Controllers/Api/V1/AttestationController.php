<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Services\AttestationService;
use App\Support\Tenant;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttestationController extends Controller
{
    public function __construct(private readonly AttestationService $service) {}

    public function scolarite(int $eleveId): BinaryFileResponse
    {
        // `tuteurs` : le modèle de certificat mentionne la filiation
        // (« Fils de… Et de… »), résolue depuis les rattachements de l'élève.
        $eleve = Eleve::forSchool(Tenant::schoolIds())
            ->with(['classe', 'school', 'tuteurs'])
            ->findOrFail($eleveId);

        $path = $this->service->genererScolarite($eleve);
        $nomFichier = 'certificat-scolarite-'.Str::slug($eleve->nom_complet).'.docx';

        return response()->download($path, $nomFichier)->deleteFileAfterSend();
    }
}
