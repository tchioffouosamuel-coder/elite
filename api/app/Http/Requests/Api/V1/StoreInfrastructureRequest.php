<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfrastructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'type' => ['required', 'in:salle_classe,bloc_administratif,wc,cloture,point_eau,electricite,aire_jeu,logement_maitre,autre'],
            'libelle' => ['nullable', 'string', 'max:150'],
            'materiau' => ['nullable', 'in:dur,semi_dur,provisoire'],
            'etat' => ['nullable', 'in:bon,assez_bon,mauvais'],
            'quantite' => ['required', 'integer', 'min:0', 'max:9999'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
