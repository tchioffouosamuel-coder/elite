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
     * École à laquelle rattacher les vérifications d'existence : celle
     * explicitement soumise dans la requête (super admin créant/modifiant une
     * donnée d'une école précise en mode agrégé) sinon celle du tenant
     * ambiant. Une école hors périmètre soumise ici n'est pas un trou de
     * sécurité : le contrôleur revalide toujours via Tenant::resolveWriteSchoolId().
     *
     * Passe par le helper `request()` plutôt que `$this->filled()` : ce trait
     * est utilisé aussi bien par des FormRequest que directement par certains
     * contrôleurs (ex. BusVehiculeController), qui n'ont pas ces méthodes.
     */
    private function schoolIdPourValidation(): ?int
    {
        return request()->filled('school_id') ? (int) request()->input('school_id') : app('tenant.school_id');
    }

    /**
     * Pour les tables avec une colonne school_id directe
     * (classes, eleves, personnels, matieres, departements, annee_scolaires).
     */
    protected function scopedExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('school_id', $this->schoolIdPourValidation());
    }

    /** Pour `trimestres`, scopé via annee_scolaires.school_id. */
    protected function scopedExistsTrimestre(): Exists
    {
        $anneeIds = AnneeScolaire::where('school_id', $this->schoolIdPourValidation())->pluck('id');

        return Rule::exists('trimestres', 'id')->whereIn('annee_scolaire_id', $anneeIds);
    }

    /** Pour `sequences`, scopé via trimestres.annee_scolaire_id.school_id. */
    protected function scopedExistsSequence(): Exists
    {
        $trimestreIds = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', $this->schoolIdPourValidation())
        )->pluck('id');

        return Rule::exists('sequences', 'id')->whereIn('trimestre_id', $trimestreIds);
    }
}
