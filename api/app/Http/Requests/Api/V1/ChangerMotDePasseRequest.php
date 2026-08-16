<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangerMotDePasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ancien_mot_de_passe' => ['required', 'string'],
            // `confirmed` attend un champ `nouveau_mot_de_passe_confirmation`.
            'nouveau_mot_de_passe' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers(), 'different:ancien_mot_de_passe'],
        ];
    }

    public function messages(): array
    {
        return [
            'nouveau_mot_de_passe.different' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
            'nouveau_mot_de_passe.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ];
    }
}
