<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVenteDenreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nature' => ['sometimes', 'required', 'string', 'max:100'],
            'vendeur_nom' => ['nullable', 'string', 'max:200'],
            'dossier_medical_ok' => ['nullable', 'boolean'],
            'frais_verses' => ['nullable', 'integer', 'min:0'],
            'gestion_frais' => ['nullable', 'string', 'max:255'],
        ];
    }
}
