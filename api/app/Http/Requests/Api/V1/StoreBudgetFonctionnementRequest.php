<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetFonctionnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
            'montant_percu' => ['required', 'integer', 'min:0'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
