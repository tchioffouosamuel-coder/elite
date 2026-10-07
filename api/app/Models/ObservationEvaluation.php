<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationEvaluation extends Model
{
    protected $table = 'observations_evaluations';

    protected $fillable = [
        'eleve_id',
        'trimestre_id',
        'classe_matiere_id',
        'classe_competence_id',
        'texte',
    ];
}
