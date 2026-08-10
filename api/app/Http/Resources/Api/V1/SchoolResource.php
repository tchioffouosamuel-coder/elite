<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'header_fr' => $this->header_fr,
            'header_en' => $this->header_en,
            'niveau_ids' => $this->whenLoaded('niveaux', fn () => $this->niveaux->pluck('id')->values()),
        ];
    }
}
