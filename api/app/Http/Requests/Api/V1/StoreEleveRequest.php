<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreEleveRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classe_id' => ['nullable', $this->scopedExists('classes')],
            'matricule' => ['nullable', 'string', 'max:50'],
            'nom_complet' => ['required', 'string', 'max:200'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'numero_acte_naissance' => ['nullable', 'string', 'max:255'],
            'refugie' => ['nullable', 'in:Oui,Non'],
            'deplace_interne' => ['nullable', 'in:Oui,Non'],
            'bororo' => ['nullable', 'in:Oui,Non'],
            'baka' => ['nullable', 'in:Oui,Non'],
            'redoublant' => ['nullable', 'boolean'],

            'tuteurs' => ['nullable', 'array'],
            'tuteurs.*.tuteur_id' => ['nullable', 'integer'],
            'tuteurs.*.nom_complet' => ['required_with:tuteurs', 'string', 'max:200'],
            'tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'tuteurs.*.telephones' => ['nullable', 'array'],
            'tuteurs.*.telephones.*.numero' => ['required', 'string', 'max:30'],
            'tuteurs.*.telephones.*.is_principal' => ['nullable', 'boolean'],
            'tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'tuteurs.*.profession' => ['nullable', 'string', 'max:255'],
            'tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'tuteurs.*.is_principal' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Un tuteur saisi via le tableau `telephones` (le nouveau formulaire
     * d'inscription) doit en fournir au moins 3 — règle qu'une simple
     * `min:3` sur `tuteurs.*.telephones` ne peut pas exprimer telle quelle
     * car elle s'appliquerait aussi aux anciens flux qui n'envoient encore
     * que le champ `telephone` unique.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('tuteurs', []) as $index => $tuteur) {
                if (! array_key_exists('telephones', $tuteur ?? [])) {
                    continue;
                }

                if (count($tuteur['telephones'] ?? []) < 3) {
                    $validator->errors()->add("tuteurs.{$index}.telephones", 'Chaque tuteur doit avoir au moins 3 numéros de téléphone.');
                }
            }
        });
    }
}
