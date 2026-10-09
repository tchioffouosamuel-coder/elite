<?php

namespace App\Support\Historique;

use App\Models;

class ImportsAnnulables
{
    // Permission, libelle et modeles dont les effets sont captures integralement.
    public const ROUTES = [
        'api.v1.notes.import' => ['notes.create', 'Import des notes', [Models\Note::class]],
        'api.v1.eleves.import' => ['eleves.import', 'Import des eleves', [Models\Eleve::class, Models\Tuteur::class, Models\EleveTuteur::class, Models\DetteAnterieure::class, Models\DossierScolarite::class]],
        'api.v1.eleves.import-traiter' => ['eleves.import', 'Import des eleves', [Models\Eleve::class, Models\Tuteur::class, Models\EleveTuteur::class, Models\DetteAnterieure::class, Models\DossierScolarite::class]],
        'api.v1.personnels.import' => ['personnel.import', 'Import du personnel', [Models\Personnel::class, Models\User::class, Models\Classe::class, Models\Banque::class, Models\FonctionReferentiel::class, Models\Departement::class]],
        'api.v1.classes.import' => ['classes.import', 'Import des classes', [Models\Classe::class]],
        'api.v1.niveaux.import' => ['niveaux.import', 'Import des niveaux', [Models\Niveau::class]],
        'api.v1.matieres.import' => ['matieres.import', 'Import des matieres', [Models\Matiere::class, Models\Departement::class, Models\ClasseMatiere::class]],
        'api.v1.competences.import' => ['competences.import', 'Import des competences', [Models\Competence::class, Models\ClasseCompetence::class, Models\ClasseMatiere::class]],
        'api.v1.departements.import' => ['departements.import', 'Import des departements', [Models\Departement::class]],
        'api.v1.fonctions-referentiel.import' => ['fonctions.import', 'Import des fonctions', [Models\FonctionReferentiel::class]],
        'api.v1.banques.import' => ['banques.import', 'Import des banques', [Models\Banque::class]],
        'api.v1.sous-systemes.import' => ['sous_systemes.import', 'Import des sous-systemes', [Models\SousSysteme::class]],
        'api.v1.appreciations.import' => ['appreciations.import', 'Import des appreciations', [Models\Appreciation::class]],
        'api.v1.inventaire.import' => ['inventaire.import', 'Import de l\'inventaire', [Models\InventaireArticle::class]],
        'api.v1.infrastructures.import' => ['infrastructures.import', 'Import des infrastructures', [Models\Infrastructure::class]],
        'api.v1.infrastructures.equipements.import' => ['equipements.import', 'Import des equipements', [Models\EquipementMobilier::class]],
        'api.v1.bus.vehicules.import' => ['bus_vehicules.import', 'Import des vehicules', [Models\BusVehicule::class]],
        'api.v1.tranches-scolarite.import' => ['tranches_scolarite.import', 'Import des tranches de scolarite', [Models\TrancheScolarite::class]],
        'api.v1.tarifs.grille-frais.import' => ['tarifs.import', 'Import de la grille des frais', [Models\GrilleFrais::class]],
        'api.v1.tarifs.frais-annexes.import' => ['frais_annexes.import', 'Import des frais annexes', [Models\FraisAnnexe::class]],
    ];
}
