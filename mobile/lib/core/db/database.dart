import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:sqlite3_flutter_libs/sqlite3_flutter_libs.dart';

import 'tables.dart';

part 'database.g.dart';

/// Base locale — source de vérité de l'interface.
///
/// Aucun écran n'appelle l'API : tout lit ici, en `watch()`, et le moteur de
/// synchronisation alimente ces tables en arrière-plan. C'est ce qui rend le
/// mode hors-ligne normal plutôt qu'exceptionnel (cf. conception).
@DriftDatabase(
  tables: [
    AnneeScolaires,
    Trimestres,
    Sequences,
    Niveaux,
    Matieres,
    Classes,
    ClasseMatieres,
    EmploisDuTemps,
    ProgressionItems,
    Eleves,
    Personnels,
    Seances,
    Presences,
    Notes,
    Sanctions,
    OutboxOperations,
    SyncEtat,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_ouvrir());

  AppDatabase.pourTests(super.executor);

  @override
  int get schemaVersion => 1;

  /// Table serveur correspondant à chaque clé d'entité du `RegistreSync`.
  /// C'est le seul endroit à compléter quand une entité est ajoutée côté API.
  TableInfo<Table, dynamic>? tablePourEntite(String entite) => switch (entite) {
        'annee_scolaires' => anneeScolaires,
        'trimestres' => trimestres,
        'sequences' => sequences,
        'niveaux' => niveaux,
        'matieres' => matieres,
        'classes' => classes,
        'classe_matieres' => classeMatieres,
        'emplois_du_temps' => emploisDuTemps,
        'progression_items' => progressionItems,
        'eleves' => eleves,
        'personnels' => personnels,
        'seances' => seances,
        'presences' => presences,
        'notes' => notes,
        'sanctions' => sanctions,
        _ => null,
      };

  /// Applique un delta serveur en une seule transaction.
  ///
  /// Tout ou rien : une coupure au milieu ne doit pas laisser la base dans un
  /// état à moitié synchronisé, que le curseur déclarerait pourtant complet.
  Future<void> appliquerDelta(
    Map<String, List<Map<String, dynamic>>> donnees,
    List<({String entite, int id})> suppressions,
  ) async {
    await transaction(() async {
      for (final entree in donnees.entries) {
        final table = tablePourEntite(entree.key);
        if (table == null) continue;

        for (final ligne in entree.value) {
          await _upsert(table, ligne);
        }
      }

      for (final suppression in suppressions) {
        final table = tablePourEntite(suppression.entite);
        if (table == null) continue;

        await (delete(table)..where((t) => (t as dynamic).id.equals(suppression.id))).go();
      }
    });
  }

  /// Insère ou remplace une ligne venue du serveur.
  ///
  /// Le serveur fait autorité : une ligne modifiée localement puis rapatriée
  /// repasse en `synchro`, ce qui matérialise la règle « le serveur gagne »
  /// décrite dans la conception.
  Future<void> _upsert(TableInfo table, Map<String, dynamic> ligne) async {
    final colonnes = {for (final c in table.$columns) c.name: c};
    final valeurs = <String, Expression>{};

    // Les clés de l'API sont déjà en snake_case, comme les colonnes générées
    // par Drift depuis le camelCase des tables : la correspondance est directe.
    ligne.forEach((cle, valeur) {
      final colonne = colonnes[cle];
      if (colonne != null) valeurs[colonne.name] = Variable(_normaliser(colonne, valeur));
    });

    valeurs['etat_sync'] = const Variable('synchro');

    await into(table).insertOnConflictUpdate(RawValuesInsertable(valeurs));
  }

  /// L'API renvoie des dates ISO et des booléens JSON ; SQLite stocke du texte
  /// et des entiers. Sans cette conversion, un `true` ferait échouer l'insert.
  Object? _normaliser(GeneratedColumn colonne, Object? valeur) {
    if (valeur == null) return null;
    if (colonne.type == DriftSqlType.bool) {
      return valeur is bool ? valeur : valeur.toString() == '1' || valeur == 'true';
    }
    if (colonne.type == DriftSqlType.string) return valeur.toString();
    if (colonne.type == DriftSqlType.int && valeur is bool) return valeur ? 1 : 0;
    return valeur;
  }
}

LazyDatabase _ouvrir() {
  return LazyDatabase(() async {
    final dossier = await getApplicationDocumentsDirectory();
    final fichier = File(p.join(dossier.path, 'elites.sqlite'));

    // Requis par sqlite3_flutter_libs sur Android pour éviter les crashs de
    // certaines versions système de SQLite.
    await applyWorkaroundToOpenSqlite3OnOldAndroidVersions();

    return NativeDatabase.createInBackground(fichier);
  });
}
