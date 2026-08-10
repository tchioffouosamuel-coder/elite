<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Support\Pdf\CarteScolaireGenerator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CarteScolaireController extends Controller
{
    public function classe(int $id): Response
    {
        $classe = Classe::forSchool(app('tenant.school_id'))->with(['school', 'anneeScolaire'])->findOrFail($id);

        $pdf = (new CarteScolaireGenerator)->build($classe, $classe->anneeScolaire?->libelle ?? '');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cartes-scolaires-'.Str::slug($classe->nom).'.pdf"',
        ]);
    }
}
