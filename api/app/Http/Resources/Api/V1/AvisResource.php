<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sujet' => $this->sujet,
            'contenu' => $this->contenu,
            'note' => $this->note,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'auteur' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->getRoleNames()->first(),
            ] : null),
        ];
    }
}
