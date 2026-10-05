<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création et modification d'une compétence évaluée.
 *
 * Ne reste ici que l'identité de la compétence — ce que l'on évalue. Le barème
 * et la répartition des volets sont passés à son attribution à une classe
 * ({@see StoreAttributionCompetenceRequest}) : une même compétence ne se note
 * pas de la même façon au CP et au CM2.
 */
class StoreCompetenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Résolue via App\Support\Tenant côté contrôleur : un id hors du
            // périmètre du compte y est rejeté (403), pas ici où l'on ne
            // connaît que l'existence de l'école.
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'label_fr' => ['required', 'string', 'max:150'],
            'label_en' => ['nullable', 'string', 'max:150'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
            'statut' => ['nullable', 'in:actif,inactif'],
        ];
    }
}
