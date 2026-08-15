<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\CataloguePermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'locale', 'is_active', 'school_id', 'niveau_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function estSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** Fonction du référentiel portée par l'agent, quand le compte en représente un. */
    public function fonction(): ?FonctionReferentiel
    {
        return $this->personnel?->fonctionReference;
    }

    /**
     * Privilèges effectifs du compte, toutes provenances confondues :
     * attribution directe, rôle, et groupe de privilèges de la fonction.
     *
     * Le cumul est volontaire. La fonction dit ce que fait le métier (« un
     * censeur saisit des notes »), le rôle et les attributions directes gèrent
     * les cas particuliers (« ce censeur-là tient aussi la caisse ») sans
     * obliger à créer une fonction par exception.
     *
     * @return Collection<int, string>
     */
    public function permissionsEffectives(): Collection
    {
        if ($this->estSuperAdmin()) {
            return collect(CataloguePermissions::codes());
        }

        return $this->getAllPermissions()
            ->pluck('name')
            ->merge($this->fonction()?->codesPermissions() ?? [])
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Seul point de vérité pour « ce compte a-t-il le droit de… ». Le
     * middleware, les services et la ressource JSON s'y réfèrent tous, pour
     * qu'un droit accordé via la fonction se comporte exactement comme un droit
     * accordé via le rôle.
     */
    public function aLaPermission(string $code): bool
    {
        return $this->estSuperAdmin() || $this->permissionsEffectives()->contains($code);
    }

    /**
     * Établissements sur lesquels le compte peut travailler. Le super
     * administrateur voit les trois écoles du complexe et bascule de l'une à
     * l'autre ; tout autre compte reste sur le sien, vers lequel il est
     * directement redirigé à la connexion.
     *
     * @return Collection<int, School>
     */
    public function ecolesAccessibles(): Collection
    {
        if (! $this->hasRole('super_admin')) {
            return $this->school ? collect([$this->school]) : collect();
        }

        return School::where('is_active', true)
            ->when($this->school?->complexe_id, fn ($q, $id) => $q->where('complexe_id', $id))
            ->orderBy('type')
            ->get();
    }
}
