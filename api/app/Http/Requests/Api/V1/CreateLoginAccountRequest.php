<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateLoginAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * L'adresse est facultative : sans elle, le service en dérive une du nom
     * sur le domaine de l'établissement. Le rôle a disparu — un compte d'agent
     * tient ses droits de sa fonction (cf. CompteAgentService).
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
