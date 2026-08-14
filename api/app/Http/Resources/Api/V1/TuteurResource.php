<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TuteurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_complet' => $this->nom_complet,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'profession' => $this->profession,
            'lien_parente' => $this->whenPivotLoaded('eleve_tuteur', fn () => $this->pivot->lien_parente),
            'is_principal' => $this->whenPivotLoaded('eleve_tuteur', fn () => (bool) $this->pivot->is_principal),
        ];
    }
}
