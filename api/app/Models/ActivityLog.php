<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal des connexions et actions marquantes des utilisateurs — remplace la
 * reconstruction a posteriori (ex. dates de création des élèves) par une trace
 * réellement attribuée à qui a agi, comme l'exige le cahier des charges (§5.5,
 * exemple « MELINGUI ROGER — Super Admin »).
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'user_id',
        'causer_nom',
        'causer_role',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Point d'entrée unique pour tracer une action. Le nom et le rôle de
     * l'auteur sont figés dans la ligne elle-même plutôt que déduits par
     * jointure à la lecture, pour que le journal reste lisible même si le
     * compte change de rôle ou est désactivé plus tard.
     */
    public static function enregistrer(User $user, string $action, string $description, ?Model $subject = null): self
    {
        return self::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'causer_nom' => $user->name,
            'causer_role' => $user->libelleRole(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
