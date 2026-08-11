<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            // Saisis via un éditeur riche : la limite couvre le balisage HTML,
            // pas seulement le texte visible.
            'header_fr' => ['nullable', 'string', 'max:4000'],
            'header_en' => ['nullable', 'string', 'max:4000'],
            'niveau_ids' => ['required', 'array', 'min:1'],
            'niveau_ids.*' => ['integer', 'exists:niveaux,id'],
        ];
    }
}
