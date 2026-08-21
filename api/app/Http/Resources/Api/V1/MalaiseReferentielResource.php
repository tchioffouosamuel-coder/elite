<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MalaiseReferentielResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label_fr' => $this->label_fr,
            'label_en' => $this->label_en,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
            ] : null),
        ];
    }
}
