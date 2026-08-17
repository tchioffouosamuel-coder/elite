<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisiteInfirmerieRequest extends FormRequest
{
    use ScopedRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eleve_id' => ['required', $this->scopedExists('eleves')],
            'date_visite' => ['required', 'date'],
            'raison' => ['required', 'string', 'max:2000'],
            'soins_prodiges' => ['required', 'string', 'max:2000'],
            'cout_soins' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'observations' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
