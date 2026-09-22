<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BanqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'code' => $this->code,
            'numero_compte_ecole' => $this->numero_compte_ecole,
            'personnels_count' => $this->when(isset($this->personnels_count), $this->personnels_count),
        ];
    }
}
