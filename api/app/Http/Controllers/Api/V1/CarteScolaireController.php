<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CarteScolaireController extends Controller
{
    public function show(int $eleveId): Response
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->with(['classe.anneeScolaire', 'school'])->findOrFail($eleveId);

        $pdf = Pdf::loadView('pdf.carte-scolaire', [
            'eleve' => $eleve,
            'classe' => $eleve->classe,
            'school' => $eleve->school,
            'anneeScolaire' => $eleve->classe?->anneeScolaire,
        ])->setPaper([0, 0, 340, 220], 'portrait');

        return $pdf->stream('carte-scolaire-'.Str::slug($eleve->nomComplet()).'.pdf');
    }
}
