<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActiviteRentreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie' => ['sometimes', 'required', 'in:pedagogique,eps,fenassco'],
            'activite' => ['sometimes', 'required', 'string', 'max:150'],
            'periode' => ['nullable', 'string', 'max:100'],
            'objectifs_vises' => ['nullable', 'string', 'max:255'],
            'prevues' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'faites' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'taux_realisation' => ['nullable', 'integer', 'min:0', 'max:100'],
            'observations' => ['nullable', 'string', 'max:255'],
        ];
    }
}
