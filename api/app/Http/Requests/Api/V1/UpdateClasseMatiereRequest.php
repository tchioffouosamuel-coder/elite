<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClasseMatiereRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personnel_id' => ['nullable', $this->scopedExists('personnels')],
            'coefficient' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:20'],
            'quota_horaire' => ['nullable', 'integer', 'min:1', 'max:40'],
            'groupe' => ['nullable', 'integer', 'min:1', 'max:3'],
            'competences' => ['nullable', 'string'],
            'statut' => ['nullable', 'in:actif,inactif'],
        ];
    }
}
