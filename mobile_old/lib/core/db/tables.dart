import 'package:drift/drift.dart';

/// Colonnes communes à toute table répliquée depuis le serveur.
///
/// `etatSync` porte l'équivalent des ✓ de WhatsApp : une ligne écrite hors
/// connexion est `enAttente` jusqu'à ce que le serveur la confirme. L'interface
/// s'en sert pour afficher un badge — jamais pour masquer la ligne, qui doit
/// rester visible et utilisable immédiatement.
mixin SyncColumns on Table {
  IntColumn get id => integer()();

  /// `synchro` | `enAttente` | `echoue`
  TextColumn get etatSync => text().withDefault(const Constant('synchro'))();

  @override
  Set<Column> get primaryKey => {id};
}

// ---------------------------------------------------------------- référentiel

class AnneeScolaires extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  TextColumn get libelle => text()();
  TextColumn get dateDebut => text().nullable()();
  TextColumn get dateFin => text().nullable()();
  BoolColumn get isActive => boolean().withDefault(const Constant(false))();
}

class Trimestres extends Table with SyncColumns {
  IntColumn get anneeScolaireId => integer()();
  TextColumn get libelle => text()();
  IntColumn get ordre => integer().withDefault(const Constant(0))();
  TextColumn get dateDebut => text().nullable()();
  TextColumn get dateFin => text().nullable()();
  BoolColumn get isActive => boolean().withDefault(const Constant(false))();
}

class Sequences extends Table with SyncColumns {
  IntColumn get trimestreId => integer()();
  IntColumn get ordre => integer().withDefault(const Constant(0))();
  TextColumn get libelle => text()();
}

class Niveaux extends Table with SyncColumns {
  TextColumn get code => text().nullable()();
  TextColumn get nameFr => text().nullable()();
  TextColumn get nameEn => text().nullable()();
  IntColumn get sousSystemId => integer().nullable()();
  IntColumn get schoolId => integer().nullable()();
  IntColumn get ordre => integer().withDefault(const Constant(0))();
}

class Matieres extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get departementId => integer().nullable()();
  TextColumn get nom => text()();
  TextColumn get nomEn => text().nullable()();
  TextColumn get abbreviation => text().nullable()();
  IntColumn get notation => integer().nullable()();
  BoolColumn get evaluePratique => boolean().withDefault(const Constant(false))();
  TextColumn get repartitionVolets => text().nullable()();
  TextColumn get statut => text().nullable()();
}

// ------------------------------------------------------------------ structure

class Classes extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get niveauId => integer().nullable()();
  IntColumn get niveauScolaireId => integer().nullable()();
  IntColumn get anneeScolaireId => integer().nullable()();
  IntColumn get professeurPrincipalId => integer().nullable()();
  IntColumn get titulaireId => integer().nullable()();
  IntColumn get surveillantGeneralId => integer().nullable()();
  TextColumn get nom => text()();
  TextColumn get sigle => text().nullable()();
  IntColumn get sousSystemeId => integer().nullable()();
  TextColumn get niveauClasse => text().nullable()();
  TextColumn get filiere => text().nullable()();
  IntColumn get capacite => integer().nullable()();
  TextColumn get qrToken => text().nullable()();
}

class ClasseMatieres extends Table with SyncColumns {
  IntColumn get classeId => integer()();
  IntColumn get matiereId => integer()();
  IntColumn get personnelId => integer().nullable()();
  RealColumn get coefficient => real().withDefault(const Constant(1))();
  IntColumn get quotaHoraire => integer().nullable()();
  IntColumn get groupe => integer().withDefault(const Constant(0))();
  TextColumn get competences => text().nullable()();
  TextColumn get statut => text().nullable()();
}

class EmploisDuTemps extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get classeId => integer()();
  IntColumn get classeMatiereId => integer().nullable()();
  TextColumn get jour => text().nullable()();
  TextColumn get heureDebut => text().nullable()();
  TextColumn get heureFin => text().nullable()();
  TextColumn get salle => text().nullable()();
}

class ProgressionItems extends Table with SyncColumns {
  IntColumn get classeMatiereId => integer()();
  IntColumn get parentId => integer().nullable()();
  TextColumn get type => text().nullable()();
  TextColumn get titre => text()();
  TextColumn get description => text().nullable()();
  TextColumn get objectifs => text().nullable()();
  TextColumn get materiel => text().nullable()();
  TextColumn get activites => text().nullable()();
  TextColumn get devoirs => text().nullable()();
  IntColumn get ordre => integer().withDefault(const Constant(0))();
  IntColumn get sequenceId => integer().nullable()();
  IntColumn get dureePrevue => integer().nullable()();
}

// ------------------------------------------------------------------ personnes

class Eleves extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get classeId => integer().nullable()();
  TextColumn get matricule => text().nullable()();
  TextColumn get nomComplet => text()();
  TextColumn get sexe => text().nullable()();
  TextColumn get dateNaissance => text().nullable()();
  TextColumn get lieuNaissance => text().nullable()();
  TextColumn get nationalite => text().nullable()();
  BoolColumn get redoublant => boolean().withDefault(const Constant(false))();
  TextColumn get statut => text().nullable()();
  TextColumn get photoPath => text().nullable()();
}

class Personnels extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get departementId => integer().nullable()();
  IntColumn get fonctionId => integer().nullable()();
  TextColumn get matricule => text().nullable()();
  TextColumn get nomComplet => text()();
  TextColumn get civilite => text().nullable()();
  TextColumn get sexe => text().nullable()();
  TextColumn get telephone => text().nullable()();
  TextColumn get email => text().nullable()();
  TextColumn get statut => text().nullable()();
  TextColumn get photoPath => text().nullable()();
}

// ------------------------------------------------ écritures du quotidien

class Seances extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get classeId => integer()();
  IntColumn get classeMatiereId => integer().nullable()();
  IntColumn get trimestreId => integer().nullable()();
  IntColumn get emploiDuTempsId => integer().nullable()();
  TextColumn get dateSeance => text().nullable()();
  TextColumn get heureDebut => text().nullable()();
  TextColumn get heureFin => text().nullable()();
  TextColumn get salle => text().nullable()();
  TextColumn get contenu => text().nullable()();
  TextColumn get observations => text().nullable()();
  TextColumn get donneesPersonnalisees => text().nullable()();
  TextColumn get statut => text().nullable()();
}

class Presences extends Table with SyncColumns {
  IntColumn get seanceId => integer()();
  IntColumn get eleveId => integer()();
  TextColumn get statut => text().nullable()();
  TextColumn get motif => text().nullable()();
  BoolColumn get justifie => boolean().withDefault(const Constant(false))();
  TextColumn get remarque => text().nullable()();
}

class Notes extends Table with SyncColumns {
  IntColumn get eleveId => integer()();
  IntColumn get classeMatiereId => integer()();
  IntColumn get sequenceId => integer().nullable()();
  TextColumn get composante => text().nullable()();
  RealColumn get valeur => real().nullable()();
  IntColumn get saisiPar => integer().nullable()();
}

class Sanctions extends Table with SyncColumns {
  IntColumn get eleveId => integer()();
  IntColumn get classeId => integer().nullable()();
  IntColumn get trimestreId => integer().nullable()();
  TextColumn get type => text()();
  IntColumn get dureeJours => integer().nullable()();
  TextColumn get dateDebut => text().nullable()();
  TextColumn get dateFin => text().nullable()();
  TextColumn get motif => text().nullable()();
  TextColumn get commentaire => text().nullable()();
  TextColumn get dateSanction => text().nullable()();
  TextColumn get statut => text().nullable()();
  BoolColumn get impacteBulletin => boolean().withDefault(const Constant(false))();
  IntColumn get enregistrePar => integer().nullable()();
}

// --------------------------------------------------------- communication

class Annonces extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  TextColumn get titre => text()();
  TextColumn get contenu => text().nullable()();
  IntColumn get publiePar => integer().nullable()();
  TextColumn get publieeLe => text().nullable()();
}

class NotificationsInternes extends Table with SyncColumns {
  IntColumn get schoolId => integer()();
  IntColumn get userId => integer()();
  TextColumn get type => text().nullable()();
  TextColumn get titre => text()();
  TextColumn get message => text().nullable()();
  TextColumn get lien => text().nullable()();
  BoolColumn get lu => boolean().withDefault(const Constant(false))();
  TextColumn get luLe => text().nullable()();
}

// ------------------------------------------------------- tables locales

/// File d'attente des écritures, persistée : elle survit à la fermeture de
/// l'app, sans quoi une saisie faite dans le train serait perdue au premier
/// redémarrage.
class OutboxOperations extends Table {
  TextColumn get id => text()(); // UUID, sert aussi de clé d'idempotence
  TextColumn get methode => text()();
  TextColumn get chemin => text()();
  TextColumn get corps => text()(); // JSON
  TextColumn get entite => text().nullable()(); // pour rattacher la ligne locale
  IntColumn get entiteId => integer().nullable()();
  IntColumn get tentatives => integer().withDefault(const Constant(0))();
  TextColumn get derniereErreur => text().nullable()();
  DateTimeColumn get creeLe => dateTime()();
  /// Report du prochain essai — porte le back-off exponentiel.
  DateTimeColumn get prochainEssai => dateTime().nullable()();

  @override
  Set<Column> get primaryKey => {id};
}

/// Curseur de synchronisation et horodatage du dernier succès, pour pouvoir
/// afficher honnêtement « données du 18 août à 14 h 03 » sur les écrans en
/// lecture seule.
class SyncEtat extends Table {
  TextColumn get cle => text()();
  TextColumn get valeur => text().nullable()();

  @override
  Set<Column> get primaryKey => {cle};
}
