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
            'solde' => $this->solde,
            'personnels_count' => $this->when(isset($this->personnels_count), $this->personnels_count),
            'mouvements' => $this->whenLoaded('mouvements', fn() => $this->mouvements->map(fn($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'montant' => $m->montant,
                'date' => $m->date_mouvement?->format('Y-m-d'),
                'libelle' => $m->libelle,
                'reference' => $m->reference,
                'cle_idempotence' => $m->cle_idempotence,
                'bulletin_paie_id' => $m->bulletin_paie_id,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
