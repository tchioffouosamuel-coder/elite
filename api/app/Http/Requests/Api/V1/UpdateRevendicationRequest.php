<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Revendication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Une fois enregistrée, seule son instruction évolue : passage en cours,
 * puis résolution ou rejet, avec la décision motivée qui l'accompagne.
 * L'élève, la matière visée et le motif d'origine restent tels que déclarés.
 */
class UpdateRevendicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in(Revendication::STATUTS)],
            'decision' => ['nullable', 'string', 'required_if:statut,resolue,rejetee'],
        ];
    }
}
