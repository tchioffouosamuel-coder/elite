<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FonctionReferentielResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'code' => $this->school->code,
                'type' => $this->school->type,
            ] : null),
            'label_fr' => $this->label_fr,
            'label_en' => $this->label_en,
            'label' => $this->label($request->header('X-Locale')),
            'personnels_count' => $this->when(isset($this->personnels_count), $this->personnels_count),
        ];
    }
}
