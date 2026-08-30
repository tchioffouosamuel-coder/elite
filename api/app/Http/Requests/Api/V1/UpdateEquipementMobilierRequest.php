<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipementMobilierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nature' => ['sometimes', 'required', 'string', 'max:150'],
            'quantite' => ['sometimes', 'required', 'integer', 'min:0', 'max:99999'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
