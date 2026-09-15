<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegleValidationSeanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'sous_systeme_id' => $this->sous_systeme_id,
            'sous_systeme' => $this->whenLoaded('sousSysteme', fn () => $this->sousSysteme?->nom),
            'methode_validation' => $this->methode_validation,
            'delai_valeur' => $this->delai_valeur,
            'delai_unite' => $this->delai_unite,
            'delai_en_minutes' => $this->delaiEnMinutes(),
        ];
    }
}
