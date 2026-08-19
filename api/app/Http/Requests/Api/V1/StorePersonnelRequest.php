<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ReglesDossierPersonnel;
use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonnelRequest extends FormRequest
{
    use ReglesDossierPersonnel, ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accessible uniquement via App\Support\Tenant::schoolIds() côté
            // contrôleur — un id hors du périmètre du compte y est rejeté (403)
            // plutôt qu'ici, où on ne connaît que l'existence de l'école.
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nom_complet' => ['required', 'string', 'max:200'],
            'fonction_id' => ['required', $this->scopedExists('fonction_referentiel')],
            ...$this->reglesDossier(),
        ];
    }
}
