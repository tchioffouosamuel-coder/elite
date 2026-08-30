<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisiteAutoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'annee_scolaire_id' => ['required', 'integer', 'exists:annee_scolaires,id'],
            'date_visite' => ['required', 'date'],
            'qualite_autorite' => ['required', 'string', 'max:150'],
            'nature_visite' => ['nullable', 'string', 'max:150'],
            'objectifs' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:255'],
        ];
    }
}
