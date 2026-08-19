<?php

namespace App\Models;

use App\Support\CataloguePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class FonctionReferentiel extends Model
{
    protected $table = 'fonction_referentiel';

    public $timestamps = false;

    protected $fillable = ['school_id', 'label_fr', 'label_en'];

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class, 'fonction_id');
    }

    /**
     * Groupe de privilèges de la fonction, hérité par tout agent qui la porte.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'fonction_permission', 'fonction_referentiel_id', 'permission_id');
    }

    /**
     * Remplace le groupe de privilèges. Les codes inconnus du catalogue sont
     * ignorés : ils ne protègent aucune route, les accepter ne ferait
     * qu'accumuler des lignes mortes en base.
     *
     * @param  list<string>  $codes
     * @return list<string> les codes réellement enregistrés
     */
    public function synchroniserPermissions(array $codes): array
    {
        $retenus = array_values(array_unique(array_filter($codes, CataloguePermissions::existe(...))));

        $ids = Permission::whereIn('name', $retenus)->where('guard_name', 'web')->pluck('id');
        $this->permissions()->sync($ids);

        // Les droits d'un agent sont résolus à chaque requête à partir du cache
        // de permissions de spatie : sans purge, un retrait de privilège
        // resterait sans effet jusqu'à expiration du cache.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $retenus;
    }

    /** @return Collection<int, string> */
    public function codesPermissions(): Collection
    {
        return $this->permissions->pluck('name');
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en' && $this->label_en) {
            return $this->label_en;
        }

        return $this->label_fr;
    }
}
