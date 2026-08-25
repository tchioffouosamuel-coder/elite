<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marque le moment où les bulletins d'un trimestre ont été rendus disponibles
 * pour une classe — sert uniquement à déclencher (une seule fois) la
 * notification « résultats disponibles » auprès du personnel concerné.
 */
class BulletinPublication extends Model
{
    protected $fillable = ['school_id', 'trimestre_id', 'classe_id', 'publie_par', 'publie_le'];

    protected function casts(): array
    {
        return ['publie_le' => 'datetime'];
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'publie_par');
    }
}
