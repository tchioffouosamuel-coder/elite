<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ReinitialiserMotDePasseOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
            // `confirmed` attend un champ `nouveau_mot_de_passe_confirmation`.
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
