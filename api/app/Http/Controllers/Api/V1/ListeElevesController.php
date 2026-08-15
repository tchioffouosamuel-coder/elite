<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Services\ListeElevesService;
use App\Support\Pdf\ListeElevesGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ListeElevesController extends Controller
{
    public function __construct(private readonly ListeElevesService $service) {}

    public function pdf(int $classeId): Response
    {
        $classe = $this->classe($classeId);

        $pdf = (new ListeElevesGenerator)->build($classe, $this->eleves($classe));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-eleves-'.Str::slug($classe->nom).'.pdf"',
        ]);
    }

    public function word(int $classeId): BinaryFileResponse
    {
        $classe = $this->classe($classeId);

        $path = $this->service->genererWord($classe, $this->eleves($classe));
        $nomFichier = 'liste-eleves-'.Str::slug($classe->nom).'.docx';

        return response()->download($path, $nomFichier)->deleteFileAfterSend();
    }

    private function classe(int $id): Classe
    {
        return Classe::forSchool(app('tenant.school_id'))->with(['school', 'anneeScolaire'])->findOrFail($id);
    }

    private function eleves(Classe $classe): Collection
    {
        return Eleve::forSchool($classe->school_id)
            ->where('classe_id', $classe->id)
            ->where('statut', 'actif')
            ->with('tuteurs')
            ->orderBy('nom_complet')
            ->get();
    }
}
