<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisiteAutoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_visite' => ['sometimes', 'required', 'date'],
            'qualite_autorite' => ['sometimes', 'required', 'string', 'max:150'],
            'nature_visite' => ['nullable', 'string', 'max:150'],
            'objectifs' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:255'],
        ];
    }
}
