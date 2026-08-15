<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'school_id' => $this->school_id,
            'niveau_id' => $this->niveau_id,
            'roles' => $this->getRoleNames(),
            'is_super_admin' => $this->estSuperAdmin(),
            // Privilèges effectifs, fonction comprise : l'interface masque ses
            // actions sur la même base que celle où l'API les refuse.
            'permissions' => $this->permissionsEffectives(),
            'fonction' => $this->whenNotNull($this->fonction()?->label()),
            'ecoles_accessibles' => $this->ecolesAccessibles()->map(fn ($ecole) => [
                'id' => $ecole->id,
                'name' => $ecole->name,
                'code' => $ecole->code,
                'type' => $ecole->type,
            ])->values(),
        ];
    }
}
