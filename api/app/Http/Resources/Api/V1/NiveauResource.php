<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NiveauResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_fr' => $this->name_fr,
            'name_en' => $this->name_en,
            'school_id' => $this->school_id,
            'school' => $this->when($this->school, function () {
                return [
                    'id' => $this->school->id,
                    'name' => $this->school->name,
                ];
            }),
            'sous_system_id' => $this->sous_system_id,
            'sous_systeme' => $this->when($this->sousSysteme, function () {
                return [
                    'id' => $this->sousSysteme->id,
                    'code' => $this->sousSysteme->code,
                    'nom' => $this->sousSysteme->nom,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
