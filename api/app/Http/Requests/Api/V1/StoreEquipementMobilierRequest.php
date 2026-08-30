<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipementMobilierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'nature' => ['required', 'string', 'max:150'],
            'quantite' => ['required', 'integer', 'min:0', 'max:99999'],
            'besoin_quantite' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }
}
