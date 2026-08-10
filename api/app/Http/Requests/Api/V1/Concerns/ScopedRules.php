<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\AnneeScolaire;
use App\Models\Trimestre;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * `exists:table,id` seul ne vérifie qu'une existence globale : un utilisateur
 * de l'école A pourrait référencer un id appartenant à l'école B (élève,
 * classe, matière, personnel, département, trimestre, séquence...). Ces
 * helpers ajoutent le filtre `school_id` (direct ou via relation) manquant.
 */
trait ScopedRules
{
    /**
     * Pour les tables avec une colonne school_id directe
     * (classes, eleves, personnels, matieres, departements, annee_scolaires).
     */
    protected function scopedExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('school_id', app('tenant.school_id'));
    }

    /** Pour `trimestres`, scopé via annee_scolaires.school_id. */
    protected function scopedExistsTrimestre(): Exists
    {
        $anneeIds = AnneeScolaire::where('school_id', app('tenant.school_id'))->pluck('id');

        return Rule::exists('trimestres', 'id')->whereIn('annee_scolaire_id', $anneeIds);
    }

    /** Pour `sequences`, scopé via trimestres.annee_scolaire_id.school_id. */
    protected function scopedExistsSequence(): Exists
    {
        $trimestreIds = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', app('tenant.school_id'))
        )->pluck('id');

        return Rule::exists('sequences', 'id')->whereIn('trimestre_id', $trimestreIds);
    }
}
