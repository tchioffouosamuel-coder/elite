<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|unique:niveaux,code,' . $this->route('id') . '|max:50',
            'name_fr' => 'required|string|max:100',
            'name_en' => 'required|string|max:100',
            'school_id' => 'required|integer|exists:schools,id',
            'sous_system_id' => 'nullable|integer|exists:sous_systemes,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code est requis',
            'code.unique' => 'Ce code existe déjà',
            'name_fr.required' => 'Le nom français est requis',
            'name_en.required' => 'Le nom anglais est requis',
            'school_id.required' => 'L\'école est requise',
            'school_id.exists' => 'L\'école sélectionnée n\'existe pas',
        ];
    }
}
