<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Sanction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Une fois prononcée, une sanction ne se réécrit pas : seuls son statut
 * (confirmation ou annulation par le conseil), son commentaire et son impact
 * sur le bulletin restent modifiables — élève, type et date font foi tels
 * qu'enregistrés.
 */
class UpdateSanctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['sometimes', Rule::in(Sanction::STATUTS)],
            'commentaire' => ['nullable', 'string'],
            'impacte_bulletin' => ['nullable', 'boolean'],
        ];
    }
}
