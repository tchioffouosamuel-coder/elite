<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresencePersonnelJournaliere extends Model
{
    protected $table = 'presences_personnel_journalieres';

    protected $fillable = [
        'school_id',
        'personnel_id',
        'date_presence',
        'heure_arrivee',
        'heure_depart',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date_presence' => 'date',
        ];
    }

    public function scopeForSchool(Builder $query, int|array $schoolId): Builder
    {
        return is_array($schoolId) ? $query->whereIn('school_id', $schoolId) : $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
