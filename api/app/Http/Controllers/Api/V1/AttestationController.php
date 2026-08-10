<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Services\AttestationService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttestationController extends Controller
{
    public function __construct(private readonly AttestationService $service)
    {
    }

    public function scolarite(int $eleveId): BinaryFileResponse
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->with(['classe.anneeScolaire', 'school'])->findOrFail($eleveId);

        $path = $this->service->genererScolarite($eleve);
        $nomFichier = 'attestation-'.Str::slug($eleve->nomComplet()).'.docx';

        return response()->download($path, $nomFichier)->deleteFileAfterSend();
    }
}
