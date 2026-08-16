<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'doit_changer_mot_de_passe' => (bool) $this->doit_changer_mot_de_passe,
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
                // Filigrane de l'interface : le logo doit être connu de tous les
                // comptes, alors que `/ecole` est réservé à `ecoles.manage`.
                'logo_url' => $ecole->logo_path ? Storage::disk('public')->url($ecole->logo_path) : null,
            ])->values(),
        ];
    }
}
