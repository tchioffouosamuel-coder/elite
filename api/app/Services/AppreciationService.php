<?php

namespace App\Services;

use App\Models\Appreciation;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Référentiel d'appréciations de la maternelle.
 *
 * « Paramétrable » ne veut pas dire « vide au départ » : une école qui ouvre
 * l'écran de saisie doit pouvoir cocher un visage tout de suite. Le référentiel
 * se dote donc de ses trois niveaux d'usage à la première lecture, puis l'école
 * les ajuste — libellé, émoji, couleur, ordre.
 */
class AppreciationService extends BaseService
{
    /**
     * Appréciations actives de l'école, dans l'ordre des colonnes du bulletin.
     *
     * @return Collection<int, Appreciation>
     */
    public function referentiel(int $schoolId): Collection
    {
        $this->assurerDefauts($schoolId);

        return Appreciation::forSchool($schoolId)->actives()->orderBy('ordre')->get();
    }

    /**
     * Pose les niveaux d'usage si l'école n'en a aucun.
     *
     * Volontairement silencieux quand le référentiel existe déjà, y compris
     * s'il a été vidé volontairement puis re-rempli autrement : on ne réinjecte
     * que sur une école qui n'a jamais rien eu.
     */
    public function assurerDefauts(int $schoolId): void
    {
        if (Appreciation::forSchool($schoolId)->exists()) {
            // Les écoles déjà dotées par la migration doivent aussi être
            // marquées : sinon la suppression volontaire du dernier code
            // déclencherait une recréation au prochain affichage.
            Setting::set($schoolId, 'appreciations_initialisees', 1);

            return;
        }

        if (Setting::get($schoolId, 'appreciations_initialisees') !== null) {
            return;
        }

        $this->transaction(function () use ($schoolId) {
            foreach (Appreciation::DEFAUTS as $defaut) {
                Appreciation::create([...$defaut, 'school_id' => $schoolId, 'statut' => 'actif']);
            }

            Setting::set($schoolId, 'appreciations_initialisees', 1);
        });
    }
}
