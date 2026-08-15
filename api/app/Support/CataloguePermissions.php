<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Catalogue des privilèges de l'application, regroupés par module.
 *
 * Le catalogue vit dans le code et non en base : un privilège n'existe que
 * parce qu'une route l'exige, en créer un depuis l'interface ne protégerait
 * rien. Ce que le super administrateur gère, c'est leur **répartition** entre
 * les fonctions (cf. `fonction_permission`) — pas la liste elle-même.
 *
 * Les libellés servent à l'écran de gestion des permissions et aux messages
 * d'erreur : « Il vous manque le privilège “Modifier les élèves” » est plus
 * exploitable pour un chef d'établissement que « eleves.manage ».
 */
class CataloguePermissions
{
    /**
     * module => [libellé du module, [code => [libellé fr, libellé en]]].
     *
     * Une entrée ajoutée ici doit l'être en même temps que la route qui
     * l'exige ; `php artisan permissions:synchroniser` recale la table.
     */
    private const MODULES = [
        'dashboard' => ['Tableau de bord', 'Dashboard', [
            'dashboard.view' => ['Consulter le tableau de bord', 'View the dashboard'],
        ]],
        'ecoles' => ['Établissement', 'School', [
            'ecoles.manage' => ["Administrer l'établissement (année scolaire, trimestres, paramètres)", 'Administer the school'],
        ]],
        'personnel' => ['Personnel', 'Staff', [
            'personnel.view' => ['Consulter le personnel', 'View staff'],
            'personnel.manage' => ['Gérer le personnel, les départements et les fonctions', 'Manage staff'],
        ]],
        'classes' => ['Classes', 'Classes', [
            'classes.view' => ['Consulter les classes', 'View classes'],
            'classes.manage' => ['Créer et modifier les classes', 'Manage classes'],
        ]],
        'niveaux' => ['Niveaux globaux', 'Global levels', [
            'niveaux.view' => ['Consulter les niveaux', 'View levels'],
            'niveaux.manage' => ['Gérer les niveaux', 'Manage levels'],
        ]],
        'eleves' => ['Élèves', 'Pupils', [
            'eleves.view' => ['Consulter les élèves', 'View pupils'],
            'eleves.manage' => ['Inscrire, modifier, transférer et importer des élèves', 'Manage pupils'],
        ]],
        'pedagogie' => ['Pédagogie', 'Teaching', [
            'pedagogie.view' => ['Consulter matières, affectations et progression', 'View teaching data'],
            'pedagogie.manage' => ['Gérer matières, affectations et progression', 'Manage teaching data'],
        ]],
        'notes' => ['Notes', 'Marks', [
            'notes.view' => ['Consulter les notes et les classements', 'View marks'],
            'notes.create' => ['Saisir et importer des notes', 'Enter marks'],
        ]],
        'appel' => ['Appel', 'Attendance', [
            'appel.manage' => ["Faire l'appel et déclarer les leçons traitées", 'Take attendance'],
        ]],
        'discipline' => ['Discipline', 'Discipline', [
            'discipline.view' => ['Consulter absences et sanctions', 'View discipline records'],
            'discipline.manage' => ['Enregistrer absences et sanctions', 'Manage discipline records'],
        ]],
        'bulletins' => ['Bulletins et statistiques', 'Reports and statistics', [
            'bulletins.view' => ['Consulter bulletins, palmarès et statistiques', 'View reports'],
            'bulletins.publish' => ['Publier les bulletins', 'Publish reports'],
        ]],
        'emploi_du_temps' => ['Emploi du temps', 'Timetable', [
            'emploi_du_temps.view' => ["Consulter l'emploi du temps et les séances", 'View the timetable'],
            'emploi_du_temps.manage' => ["Gérer l'emploi du temps et les séances", 'Manage the timetable'],
        ]],
        'finance' => ['Finances', 'Finance', [
            'finance.view' => ['Consulter les finances', 'View finances'],
            'finance.manage' => ['Gérer les finances', 'Manage finances'],
        ]],
        'annonces' => ['Annonces', 'Announcements', [
            'annonces.view' => ['Consulter les annonces', 'View announcements'],
            'annonces.publish' => ['Publier des annonces', 'Publish announcements'],
        ]],
    ];

    /**
     * Tous les codes de privilège, dans l'ordre du catalogue.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        $codes = [];

        foreach (self::MODULES as [, , $permissions]) {
            foreach (array_keys($permissions) as $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public static function existe(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }

    /**
     * Libellé lisible d'un privilège, pour les messages d'erreur et l'interface.
     * Retombe sur le code brut si le privilège n'est pas catalogué — mieux vaut
     * un message technique qu'un message vide.
     */
    public static function libelle(string $code, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        foreach (self::MODULES as [, , $permissions]) {
            if (isset($permissions[$code])) {
                [$fr, $en] = $permissions[$code];

                return $locale === 'en' ? $en : $fr;
            }
        }

        return $code;
    }

    /**
     * Catalogue mis en forme pour l'écran de gestion des permissions.
     *
     * @return Collection<int, array{code: string, libelle: string, permissions: list<array{code: string, libelle: string}>}>
     */
    public static function parModule(?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();

        return collect(self::MODULES)->map(fn (array $module, string $code) => [
            'code' => $code,
            'libelle' => $locale === 'en' ? $module[1] : $module[0],
            'permissions' => collect($module[2])
                ->map(fn (array $libelles, string $permission) => [
                    'code' => $permission,
                    'libelle' => $locale === 'en' ? $libelles[1] : $libelles[0],
                ])
                ->values()
                ->all(),
        ])->values();
    }
}
