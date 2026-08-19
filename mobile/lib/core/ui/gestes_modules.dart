import 'package:flutter/material.dart';

import 'actions_ressource.dart';
import 'formulaire.dart';
import 'theme.dart';

/// Gestes de chaque module, déclarés au même endroit.
///
/// Les champs et leurs contraintes reprennent la validation du serveur
/// (`app/Http/Requests` et les `validate()` des contrôleurs) : les rejouer ici
/// évite d'envoyer une saisie que l'API refusera, sans devenir la référence —
/// c'est le serveur qui tranche, et ses erreurs par champ remontent dans le
/// formulaire.
///
/// Ce fichier est la table de correspondance qui rend la parité avec le web
/// vérifiable action par action.
abstract final class Gestes {
  // ------------------------------------------------- Transport scolaire

  static const vehicules = ActionsRessource(
    nomSingulier: 'Véhicule',
    chemin: 'bus/vehicules',
    champs: [
      Champ(cle: 'immatriculation', libelle: 'Immatriculation', requis: true),
      Champ(cle: 'marque', libelle: 'Marque'),
      Champ(cle: 'capacite', libelle: 'Capacité (places)', type: TypeChamp.nombre, min: 1, max: 200),
      Champ(
        cle: 'chauffeur_id',
        libelle: 'Chauffeur',
        type: TypeChamp.choix,
        optionsDepuis: ChoixDistants('personnels'),
      ),
      Champ(
        cle: 'statut',
        libelle: 'Statut',
        type: TypeChamp.choix,
        options: {'actif': 'Actif', 'hors_service': 'Hors service'},
      ),
    ],
  );

  static const trajets = ActionsRessource(
    nomSingulier: 'Trajet',
    chemin: 'bus/trajets',
    champs: [
      Champ(cle: 'nom', libelle: 'Nom du trajet', requis: true),
      Champ(cle: 'description', libelle: 'Description'),
      Champ(
        cle: 'vehicule_id',
        libelle: 'Véhicule',
        type: TypeChamp.choix,
        optionsDepuis: ChoixDistants('bus/vehicules'),
      ),
      Champ(cle: 'tarif_aller_simple', libelle: 'Tarif aller simple', type: TypeChamp.montant, min: 0),
      Champ(cle: 'tarif_retour_simple', libelle: 'Tarif retour simple', type: TypeChamp.montant, min: 0),
      Champ(cle: 'tarif_aller_retour', libelle: 'Tarif aller-retour', type: TypeChamp.montant, min: 0),
    ],
    actionsSupplementaires: [
      ActionMetier(
        libelle: 'Notifier les parents',
        icone: Icons.sms_outlined,
        sousChemin: 'notifier',
        confirmation: 'Un message sera envoyé aux parents des élèves de ce trajet.',
      ),
    ],
  );

  // -------------------------------------------------------- Inventaire

  static const inventaire = ActionsRessource(
    nomSingulier: 'Article',
    chemin: 'inventaire',
    champs: [
      Champ(cle: 'nom', libelle: 'Désignation', requis: true),
      Champ(
        cle: 'categorie',
        libelle: 'Catégorie',
        type: TypeChamp.choix,
        requis: true,
        options: {
          'mobilier': 'Mobilier',
          'informatique': 'Informatique',
          'pedagogique': 'Pédagogique',
          'sport': 'Sport',
          'autre': 'Autre',
        },
      ),
      Champ(cle: 'quantite', libelle: 'Quantité', type: TypeChamp.nombre, requis: true, min: 1),
      Champ(
        cle: 'etat',
        libelle: 'État',
        type: TypeChamp.choix,
        requis: true,
        options: {
          'bon': 'Bon',
          'moyen': 'Moyen',
          'mauvais': 'Mauvais',
          'hors_service': 'Hors service',
        },
      ),
      Champ(cle: 'localisation', libelle: 'Localisation'),
      Champ(cle: 'valeur_unitaire', libelle: 'Valeur unitaire', type: TypeChamp.montant, min: 0),
      Champ(cle: 'date_acquisition', libelle: "Date d'acquisition", type: TypeChamp.date),
      Champ(cle: 'notes', libelle: 'Notes', type: TypeChamp.texteLong),
    ],
  );

  // ------------------------------------------------------------- Santé

  static const infirmerie = ActionsRessource(
    nomSingulier: 'Visite',
    chemin: 'infirmerie/visites',
    champs: [
      Champ(
        cle: 'eleve_id',
        libelle: 'Élève',
        type: TypeChamp.choix,
        requis: true,
        optionsDepuis: ChoixDistants('eleves'),
      ),
      Champ(cle: 'date_visite', libelle: 'Date de la visite', type: TypeChamp.date, requis: true),
      Champ(cle: 'raison', libelle: 'Motif', type: TypeChamp.texteLong, requis: true),
      Champ(cle: 'soins_prodiges', libelle: 'Soins prodigués', type: TypeChamp.texteLong, requis: true),
      Champ(cle: 'cout_soins', libelle: 'Coût des soins', type: TypeChamp.montant, min: 0),
      Champ(cle: 'observations', libelle: 'Observations', type: TypeChamp.texteLong),
    ],
  );

  // ---------------------------------------------------------- Finances

  static const depenses = ActionsRessource(
    nomSingulier: 'Dépense',
    chemin: 'depenses',
    // Une dépense engagée puis payée ne se réécrit pas : c'est une pièce
    // comptable. Le web ne propose que payer ou annuler, on s'y tient.
    peutModifier: false,
    peutSupprimer: false,
    champs: [
      Champ(cle: 'libelle', libelle: 'Libellé', requis: true),
      Champ(cle: 'montant', libelle: 'Montant', type: TypeChamp.montant, requis: true, min: 1),
      Champ(cle: 'date_depense', libelle: 'Date', type: TypeChamp.date),
      Champ(cle: 'beneficiaire', libelle: 'Bénéficiaire'),
      Champ(
        cle: 'mode',
        libelle: 'Mode de règlement',
        type: TypeChamp.choix,
        options: {
          'especes': 'Espèces',
          'mobile_money': 'Mobile money',
          'virement': 'Virement',
          'cheque': 'Chèque',
          'depot_bancaire': 'Dépôt bancaire',
        },
      ),
      Champ(cle: 'reference_facture', libelle: 'Référence de facture'),
      Champ(cle: 'responsable', libelle: 'Responsable'),
    ],
    actionsSupplementaires: [
      ActionMetier(
        libelle: 'Marquer payée',
        icone: Icons.check_circle_outline,
        sousChemin: 'payer',
        couleur: Couleurs.synchro,
        confirmation: 'La dépense sera enregistrée comme payée.',
        visible: _estEngagee,
      ),
      ActionMetier(
        libelle: 'Annuler',
        icone: Icons.cancel_outlined,
        sousChemin: 'annuler',
        couleur: Couleurs.echec,
        confirmation: 'Cette annulation est tracée en comptabilité.',
        visible: _nestPasAnnulee,
      ),
    ],
  );

  static bool _estEngagee(Map<String, dynamic> l) => l['statut'] == 'engagee';
  static bool _nestPasAnnulee(Map<String, dynamic> l) => l['statut'] != 'annulee';

  static const avances = ActionsRessource(
    nomSingulier: 'Avance',
    chemin: 'avances-salaire',
    peutModifier: false,
    peutSupprimer: false,
    champs: [
      Champ(
        cle: 'personnel_id',
        libelle: 'Membre du personnel',
        type: TypeChamp.choix,
        requis: true,
        optionsDepuis: ChoixDistants('personnels'),
      ),
      Champ(cle: 'montant', libelle: 'Montant', type: TypeChamp.montant, requis: true, min: 1),
      Champ(cle: 'date_avance', libelle: 'Date', type: TypeChamp.date, requis: true),
      Champ(cle: 'motif', libelle: 'Motif', type: TypeChamp.texteLong),
    ],
    actionsSupplementaires: [
      ActionMetier(
        libelle: 'Annuler',
        icone: Icons.cancel_outlined,
        sousChemin: 'annuler',
        couleur: Couleurs.echec,
        confirmation: "L'avance sera annulée.",
      ),
    ],
  );

  // --------------------------------------------------- Vue d'ensemble

  static const annonces = ActionsRessource(
    nomSingulier: 'Annonce',
    chemin: 'annonces',
    // L'API ne permet pas de modifier une annonce publiée, seulement de la
    // retirer : une annonce déjà lue ne se réécrit pas dans le dos du lecteur.
    peutModifier: false,
    champs: [
      Champ(cle: 'titre', libelle: 'Titre', requis: true),
      Champ(cle: 'contenu', libelle: 'Contenu', type: TypeChamp.texteLong, requis: true),
    ],
  );

  // -------------------------------------------- Personnel & structure

  static const departements = ActionsRessource(
    nomSingulier: 'Département',
    chemin: 'departements',
    champs: [
      Champ(cle: 'nom', libelle: 'Nom', requis: true),
      Champ(
        cle: 'responsable_id',
        libelle: 'Responsable',
        type: TypeChamp.choix,
        optionsDepuis: ChoixDistants('personnels'),
      ),
    ],
  );

  static const sousSystemes = ActionsRessource(
    nomSingulier: 'Sous-système',
    chemin: 'sous-systemes',
    champs: [
      Champ(cle: 'code', libelle: 'Code', requis: true, indication: 'Ex. FR, EN, BI'),
      Champ(cle: 'nom', libelle: 'Nom', requis: true),
      Champ(cle: 'description', libelle: 'Description', type: TypeChamp.texteLong),
    ],
  );

  // -------------------------------------------------------- Résultats

  static const revendications = ActionsRessource(
    nomSingulier: 'Réclamation',
    chemin: 'revendications',
    champs: [
      Champ(
        cle: 'eleve_id',
        libelle: 'Élève',
        type: TypeChamp.choix,
        requis: true,
        optionsDepuis: ChoixDistants('eleves'),
      ),
      Champ(
        cle: 'type',
        libelle: 'Type',
        type: TypeChamp.choix,
        requis: true,
        options: {
          'note': 'Contestation de note',
          'decision': 'Contestation de décision',
          'autre': 'Autre',
        },
      ),
      Champ(cle: 'objet', libelle: 'Objet', requis: true),
      Champ(cle: 'motif', libelle: 'Motif', type: TypeChamp.texteLong, requis: true),
      Champ(cle: 'date_reception', libelle: 'Date de réception', type: TypeChamp.date, requis: true),
    ],
  );

  // --------------------------------------------------- Administration

  static const anneesScolaires = ActionsRessource(
    nomSingulier: 'Année scolaire',
    chemin: 'annees-scolaires',
    peutModifier: false,
    peutSupprimer: false,
    champs: [
      Champ(cle: 'libelle', libelle: 'Libellé', requis: true, indication: 'Ex. 2026-2027'),
      Champ(cle: 'date_debut', libelle: 'Début', type: TypeChamp.date, requis: true),
      Champ(cle: 'date_fin', libelle: 'Fin', type: TypeChamp.date, requis: true),
    ],
    actionsSupplementaires: [
      ActionMetier(
        libelle: 'Activer',
        icone: Icons.play_circle_outline,
        sousChemin: 'activer',
        couleur: Couleurs.synchro,
        confirmation: 'Toute la plateforme basculera sur cette année scolaire.',
      ),
    ],
  );
}
