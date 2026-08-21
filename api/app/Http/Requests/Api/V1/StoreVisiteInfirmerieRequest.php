<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisiteInfirmerieRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eleve_id' => ['required', $this->scopedExists('eleves')],
            'date_visite' => ['required', 'date'],
            'raison' => ['required', 'string', 'max:2000'],
            'malaise_ids' => ['nullable', 'array'],
            'malaise_ids.*' => [$this->scopedExists('malaises_referentiel')],
            'soins_prodiges' => ['required', 'string', 'max:2000'],
            'type_traitement' => ['required', 'in:interne,externe,mixte'],
            'structure_externe' => ['required_if:type_traitement,externe,mixte', 'nullable', 'string', 'max:255'],
            'cout_soins' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'materiels' => ['nullable', 'array'],
            'materiels.*.inventaire_article_id' => ['required', $this->scopedExists('inventaire_articles')],
            'materiels.*.quantite' => ['required', 'integer', 'min:1'],
            'autre_materiel' => ['nullable', 'string', 'max:255'],
            'cout_autre_materiel' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'observations' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
