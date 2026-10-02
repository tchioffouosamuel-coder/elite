<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Correspondance entre les anciens privilèges globaux (`X.manage`) et les
 * privilèges par action qui les remplacent.
 *
 * Deux usages, tous deux transitoires par nature :
 * - la migration de bascule, qui accorde à chaque rôle, fonction ou compte
 *   détenteur d'un ancien code l'ensemble de ses remplaçants — personne ne
 *   perd ni ne gagne un accès le jour du découpage ;
 * - {@see alias()}, qui continue de servir l'ancien code aux applications
 *   mobiles installées avant le découpage : elles ne connaissent que
 *   `eleves.manage` pour afficher leurs boutons, et l'API reste seule juge de
 *   chaque action.
 *
 * Chaque nouveau code descend d'un seul ancien : c'est ce qui garantit que la
 * bascule ne déplace aucun droit d'un module à l'autre.
 */
class AnciensPrivileges
{
    /** ancien code => codes qui le remplacent */
    public const REMPLACEMENTS = [
        'ecoles.manage' => [
            'ecoles.update', 'parametres.update',
            'annees_scolaires.view', 'annees_scolaires.create', 'annees_scolaires.update',
            'annees_scolaires.activer', 'annees_scolaires.archiver', 'annees_scolaires.seances',
            'trimestres.create', 'trimestres.update', 'trimestres.activer', 'trimestres.seances',
        ],
        'personnel.manage' => [
            'personnel.create', 'personnel.update', 'personnel.delete', 'personnel.import',
            'personnel.archiver', 'personnel.comptes', 'personnel.presences', 'personnel.attestations',
            'departements.create', 'departements.update', 'departements.delete', 'departements.import',
            'fonctions.create', 'fonctions.update', 'fonctions.delete', 'fonctions.import',
            'banques.create', 'banques.update', 'banques.delete', 'banques.import', 'banques.mouvements',
            'regles_seance.create', 'regles_seance.update', 'regles_seance.delete',
        ],
        'classes.manage' => [
            'classes.create', 'classes.update', 'classes.delete', 'classes.import', 'classes.fusionner',
            'sous_systemes.view', 'sous_systemes.create', 'sous_systemes.update', 'sous_systemes.delete', 'sous_systemes.import',
        ],
        'niveaux.manage' => ['niveaux.create', 'niveaux.update', 'niveaux.delete', 'niveaux.import'],
        'eleves.manage' => [
            'eleves.create', 'eleves.update', 'eleves.delete', 'eleves.import',
            'eleves.transferer', 'eleves.fusionner', 'eleves.comptes',
            'matricules_nationaux.update', 'matricules_nationaux.import',
            'tuteurs.view', 'tuteurs.update', 'tuteurs.delete', 'tuteurs.fusionner', 'tuteurs.comptes',
            'preinscriptions.view', 'preinscriptions.create', 'preinscriptions.update',
            'preinscriptions.delete', 'preinscriptions.import', 'preinscriptions.valider',
            'modifications_eleves.view', 'modifications_eleves.valider',
            'justifications.view',
            'observations.view', 'observations.repondre',
        ],
        'pedagogie.manage' => [
            'matieres.create', 'matieres.update', 'matieres.delete', 'matieres.import', 'matieres.fusionner',
            'affectations.create', 'affectations.update', 'affectations.delete',
            'tronc_commun.create', 'tronc_commun.delete',
            'calendrier_scolaire.create', 'calendrier_scolaire.delete',
            'competences.create', 'competences.update', 'competences.delete', 'competences.import', 'competences.attribuer',
            'appreciations.create', 'appreciations.update', 'appreciations.delete', 'appreciations.import',
            'niveaux_scolaires.create', 'niveaux_scolaires.update', 'niveaux_scolaires.delete',
            'progression.update', 'progression.import',
            'evaluations.create', 'evaluations.update', 'evaluations.delete',
        ],
        'appel.manage' => ['appel.saisir'],
        'discipline.manage' => ['absences.saisir', 'sanctions.create', 'sanctions.update', 'sanctions.delete'],
        'infirmerie.manage' => [
            'infirmerie.create', 'infirmerie.update', 'infirmerie.delete', 'infirmerie.import',
            'malaises.create', 'malaises.update', 'malaises.delete',
        ],
        'bus.manage' => [
            'bus_vehicules.create', 'bus_vehicules.update', 'bus_vehicules.delete', 'bus_vehicules.import',
            'bus_trajets.create', 'bus_trajets.update', 'bus_trajets.delete', 'bus_trajets.import', 'bus_trajets.notifier',
            'bus_arrets.create', 'bus_arrets.update', 'bus_arrets.delete', 'bus_arrets.import',
        ],
        'inventaire.manage' => [
            'inventaire.create', 'inventaire.update', 'inventaire.delete', 'inventaire.import', 'inventaire.etiquettes',
            'demandes_articles.view', 'demandes_articles.valider',
        ],
        'infrastructures.manage' => [
            'infrastructures.create', 'infrastructures.update', 'infrastructures.delete', 'infrastructures.import',
            'equipements.create', 'equipements.update', 'equipements.delete', 'equipements.import',
        ],
        'rapport_rentree.manage' => [
            'rapport_rentree.update',
            'visites_autorites.create', 'visites_autorites.update', 'visites_autorites.delete',
            'activites_rentree.create', 'activites_rentree.update', 'activites_rentree.delete',
            'ventes_denrees.create', 'ventes_denrees.update', 'ventes_denrees.delete',
        ],
        'rapport_trimestre.manage' => ['rapport_trimestre.update'],
        'point_de_vente.manage' => ['point_de_vente.annuler', 'point_de_vente.approvisionner'],
        'emploi_du_temps.manage' => [
            'emploi_du_temps.create', 'emploi_du_temps.update', 'emploi_du_temps.delete', 'emploi_du_temps.import',
            'edt_elements.create', 'edt_elements.update', 'edt_elements.delete', 'edt_elements.appliquer',
            'seances.create', 'seances.update', 'seances.delete', 'seances.generer',
            'salles.create', 'salles.update', 'salles.delete',
        ],
        'finance.manage' => [
            'tarifs.update', 'tarifs.delete', 'tarifs.import',
            'frais_annexes.create', 'frais_annexes.update', 'frais_annexes.delete', 'frais_annexes.import',
            'tranches_scolarite.update', 'tranches_scolarite.import',
            'remises.create', 'remises.update', 'remises.delete',
            'moratoires.create', 'moratoires.delete',
            'dettes_anterieures.create', 'dettes_anterieures.delete', 'dettes_anterieures.import', 'dettes_anterieures.oublier',
            'budget_fonctionnement.update',
            'assurances_scolaires.create', 'assurances_scolaires.update', 'assurances_scolaires.delete',
            'conseil_ecole.update', 'apee.update',
        ],
        'bibliotheque.manage' => ['bibliotheque.create', 'bibliotheque.update', 'bibliotheque.delete'],
        'revendications.manage' => ['revendications.create', 'revendications.update', 'revendications.delete'],
        'conseil_classe.manage' => ['conseil_classe.update', 'conseil_classe.valider'],
    ];

    /**
     * Traduit une liste d'anciens et de nouveaux codes en nouveaux codes
     * seulement : un ancien code est remplacé par ses descendants, le reste
     * passe tel quel.
     *
     * @param  iterable<string>  $codes
     * @return list<string>
     */
    public static function convertir(iterable $codes): array
    {
        $convertis = [];

        foreach ($codes as $code) {
            array_push($convertis, ...(self::REMPLACEMENTS[$code] ?? [$code]));
        }

        return array_values(array_unique($convertis));
    }

    /**
     * Anciens codes à servir en plus des privilèges effectifs, pour les
     * clients antérieurs au découpage. Détenir **un** remplaçant suffit : ces
     * clients n'ont qu'un interrupteur par module, et le fermer priverait de
     * ses boutons l'agent à qui l'on n'a laissé qu'une partie des actions.
     * L'API refuse de toute façon celles qu'il n'a pas.
     *
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    public static function alias(Collection $permissions): Collection
    {
        return collect(self::REMPLACEMENTS)
            ->filter(fn (array $remplacants) => $permissions->intersect($remplacants)->isNotEmpty())
            ->keys();
    }
}
