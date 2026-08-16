<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ReglesDossierPersonnel;
use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonnelRequest extends FormRequest
{
    use ReglesDossierPersonnel, ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_complet' => ['sometimes', 'required', 'string', 'max:200'],
            'fonction_id' => ['sometimes', 'required', $this->scopedExists('fonction_referentiel')],
            ...$this->reglesDossier(),
        ];
    }
}
