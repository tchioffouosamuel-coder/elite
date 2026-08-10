<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceTrimestre extends Model
{
    protected $fillable = ['eleve_id', 'trimestre_id', 'heures_justifiees', 'heures_non_justifiees', 'mis_a_jour_par'];

    protected function casts(): array
    {
        return [
            'heures_justifiees' => 'decimal:1',
            'heures_non_justifiees' => 'decimal:1',
        ];
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }
}
