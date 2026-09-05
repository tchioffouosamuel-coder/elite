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
            // Un agent peut changer d'établissement au sein du complexe. Comme
            // à la création, l'appartenance de l'id au périmètre du compte est
            // vérifiée côté contrôleur (403), pas ici.
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nom_complet' => ['sometimes', 'required', 'string', 'max:200'],
            'fonction_id' => ['sometimes', 'required', $this->scopedExists('fonction_referentiel')],
            ...$this->reglesDossier(),
        ];
    }
}
