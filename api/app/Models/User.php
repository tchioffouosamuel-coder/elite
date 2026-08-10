<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
