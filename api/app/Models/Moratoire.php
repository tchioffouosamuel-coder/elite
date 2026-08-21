<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Moratoire extends Model
{
    protected $fillable = [
        'school_id',
        'eleve_id',
        'date_delivrance',
        'date_expiration',
        'motif',
        'accorde_par',
    ];

    protected function casts(): array
    {
        return [
            'date_delivrance' => 'date',
            'date_expiration' => 'date',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    /** Encore couvrant aujourd'hui — un moratoire expiré n'a plus rien à dire sur la liste des insolvables. */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereDate('date_expiration', '>=', Carbon::today());
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function accordePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accorde_par');
    }

    public function getValideAttribute(): bool
    {
        return $this->date_expiration?->isFuture() || $this->date_expiration?->isToday();
    }
}
