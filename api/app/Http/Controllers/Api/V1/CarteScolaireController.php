<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Support\Pdf\CarteScolaireGenerator;
use App\Support\Tenant;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CarteScolaireController extends Controller
{
    public function classe(int $id): Response
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($id);
        $annee = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->first();

        $pdf = (new CarteScolaireGenerator)->build($classe, $annee?->libelle ?? '');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cartes-scolaires-'.Str::slug($classe->nom).'.pdf"',
        ]);
    }
}
