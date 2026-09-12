<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipementMobilierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nature' => ['required', 'string', 'max:150'],
            'date_acquisition' => ['nullable', 'date'],
            'quantite' => ['required', 'integer', 'min:0', 'max:99999'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'prix_unitaire' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'statut' => ['nullable', 'string', Rule::in(['bon', 'assez_bon', 'mauvais'])],
        ];
    }
}
