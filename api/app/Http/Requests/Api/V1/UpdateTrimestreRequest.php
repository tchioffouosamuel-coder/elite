<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrimestreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `annee_scolaire_id` ne se modifie pas ici : déplacer un trimestre vers
     * une autre année laisserait ses séquences et séances rattachées à
     * l'ancienne, un déplacement à traiter à part s'il devient nécessaire.
     */
    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:50'],
            'ordre' => ['required', 'integer', 'min:1', 'max:3'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
        ];
    }
}
