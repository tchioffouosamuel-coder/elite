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
            'telephones' => $this->whenLoaded('telephones', fn () => $this->telephones->map(fn (\App\Models\TuteurTelephone $tel) => [
                'id' => $tel->id,
                'numero' => $tel->numero,
                'is_principal' => $tel->is_principal,
            ])->values()),
            'email' => $this->email,
            'profession' => $this->profession,
            'adresse' => $this->adresse,
            'lien_parente' => $this->whenPivotLoaded('eleve_tuteur', fn () => $this->pivot->lien_parente),
            'is_principal' => $this->whenPivotLoaded('eleve_tuteur', fn () => (bool) $this->pivot->is_principal),
        ];
    }
}
