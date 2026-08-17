<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerte interne affichée dans la cloche de notifications — le pendant « en
 * application » des SMS envoyés aux parents, à destination du personnel.
 */
class NotificationInterne extends Model
{
    protected $table = 'notifications_internes';

    protected $fillable = ['school_id', 'user_id', 'type', 'titre', 'message', 'lien', 'lu', 'lu_le'];

    protected function casts(): array
    {
        return ['lu' => 'boolean', 'lu_le' => 'datetime'];
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopePourUtilisateur(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
