<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ReinitialiserMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Réservée au super administrateur : gérée par le middleware de route
        // (`super_admin`), au même titre que le reste de l'administration des
        // comptes.
        return true;
    }

    public function rules(): array
    {
        return [
            // `confirmed` attend un champ `nouveau_mot_de_passe_confirmation` :
            // sans l'ancien mot de passe pour se relire, une double saisie est
            // le seul garde-fou contre une faute de frappe.
            'nouveau_mot_de_passe' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'nouveau_mot_de_passe.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ];
    }
}
