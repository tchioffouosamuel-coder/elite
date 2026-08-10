<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['school_id', 'key', 'value'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function get(int $schoolId, string $key, string|int|float|null $default = null): ?string
    {
        return static::query()->where('school_id', $schoolId)->where('key', $key)->value('value') ?? (
            $default === null ? null : (string) $default
        );
    }

    public static function set(int $schoolId, string $key, string|int|float $value): void
    {
        static::updateOrCreate(['school_id' => $schoolId, 'key' => $key], ['value' => (string) $value]);
    }
}
