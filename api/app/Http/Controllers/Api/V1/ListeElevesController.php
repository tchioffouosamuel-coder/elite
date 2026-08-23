<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Services\ListeElevesService;
use App\Support\Pdf\ListeElevesGenerator;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
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

    /**
     * Toute une école, toutes classes confondues — l'action « Liste par
     * école » de la page Élèves. `school_id` restreint à une seule école du
     * périmètre ; sans lui, la première école accessible sert d'en-tête mais
     * la liste couvre tout le périmètre en mode agrégé.
     */
    public function pdfEcole(Request $request): Response
    {
        $schoolId = $request->integer('school_id') ?: null;
        $schoolIds = $schoolId ? [$schoolId] : Tenant::schoolIds();

        if ($schoolId) {
            abort_unless(in_array($schoolId, Tenant::schoolIds(), true), 403, "Cet établissement n'est pas accessible à votre compte.");
        }

        $school = School::findOrFail($schoolIds[0]);

        $eleves = Eleve::forSchool($schoolIds)
            ->where('statut', 'actif')
            ->with(['tuteurs', 'classe'])
            ->orderBy('nom_complet')
            ->get();

        $pdf = (new ListeElevesGenerator)->buildEcole($school, $eleves);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="liste-eleves-'.Str::slug($school->name).'.pdf"',
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
        return Classe::forSchool(app('tenant.school_id'))->with('school')->findOrFail($id);
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
