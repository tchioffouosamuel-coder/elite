<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'type' => $this->type,
            'complexe_id' => $this->complexe_id,
            'header_fr' => $this->header_fr,
            'header_en' => $this->header_en,
            'logo_url' => $this->urlImage($this->logo_path),
            'cachet_url' => $this->urlImage($this->stamp_path),
            'signature_url' => $this->urlImage($this->signature_path),
            'niveau_ids' => $this->whenLoaded('niveaux', fn () => $this->niveaux->pluck('id')->values()),
        ];
    }

    private function urlImage(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
