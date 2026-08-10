<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClasseMatiereRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'matiere_id' => [
                'required',
                $this->scopedExists('matieres'),
                Rule::unique('classe_matieres')->where('classe_id', $this->route('classeId')),
            ],
            'personnel_id' => ['nullable', $this->scopedExists('personnels')],
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'quota_horaire' => ['nullable', 'integer', 'min:1', 'max:40'],
            'groupe' => ['nullable', 'integer', 'min:1', 'max:3'],
            'competences' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'matiere_id.unique' => 'Cette matière est déjà affectée à cette classe.',
        ];
    }
}
