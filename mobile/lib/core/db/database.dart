import 'dart:convert';
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
    Annonces,
    NotificationsInternes,
    OutboxOperations,
    SyncEtat,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_ouvrir());

  AppDatabase.pourTests(super.executor);

  @override
  int get schemaVersion => 2;

  /// Une base locale n'est qu'un cache : on pourrait la vider à chaque montée
  /// de version. On ne le fait pas — l'outbox y vit aussi, et l'effacer
  /// perdrait les écritures pas encore parties.
  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (m) => m.createAll(),
        onUpgrade: (m, depuis, vers) async {
          if (depuis < 2) {
            await m.createTable(annonces);
            await m.createTable(notificationsInternes);
          }
        },
      );

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
        'annonces' => annonces,
        'notifications_internes' => notificationsInternes,
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
    final valeurs = <String, Variable>{};

    // Les clés de l'API sont déjà en snake_case, comme les colonnes générées
    // par Drift depuis le camelCase des tables : la correspondance est directe.
    ligne.forEach((cle, valeur) {
      final colonne = colonnes[cle];
      if (colonne != null) valeurs[colonne.name] = _variable(colonne, valeur);
    });

    valeurs['etat_sync'] = const Variable<String>('synchro');

    /*
     * SQL brut plutôt que `into(table).insertOnConflictUpdate(...)` : cette
     * dernière exige un `Insertable<D>` du type exact de la table, que le
     * moteur de synchronisation ne connaît pas — il travaille sur des
     * `TableInfo` génériques résolus depuis une clé d'entité. Le passage par
     * un insertable non typé compile mais échoue à l'exécution.
     *
     * `updates:` est indispensable : c'est lui qui réveille les `watch()` des
     * écrans. Sans cette déclaration, les données arriveraient en base sans
     * qu'aucune interface ne s'en aperçoive.
     */
    final noms = valeurs.keys.toList();
    final marqueurs = List.filled(noms.length, '?').join(', ');

    await customInsert(
      'INSERT OR REPLACE INTO ${table.actualTableName} '
      '(${noms.join(', ')}) VALUES ($marqueurs)',
      variables: valeurs.values.toList(),
      updates: {table},
    );
  }

  /// Convertit une valeur JSON en variable SQL **typée**.
  ///
  /// Le type générique compte : `Variable` est déclaré `<T extends Object>` et
  /// Drift résout le type SQL depuis ce `T`. Construire un `Variable(valeur)`
  /// à partir d'un `Object?` donne un `Variable<Object>`, que Drift ne sait pas
  /// convertir — l'insertion lève alors à l'exécution, alors même que le code
  /// compile sans avertissement. On aiguille donc explicitement sur le type
  /// déclaré de la colonne.
  Variable _variable(GeneratedColumn colonne, Object? valeur) {
    if (valeur == null) return const Variable<String>(null);

    return switch (colonne.type) {
      DriftSqlType.bool => Variable<bool>(
          valeur is bool ? valeur : valeur == 1 || valeur == '1' || valeur == 'true',
        ),
      // JSON ne distingue pas 3 de 3.0 : un coefficient entier arrive en `int`
      // là où la colonne est réelle, et inversement pour un identifiant.
      DriftSqlType.int => Variable<int>(
          valeur is int ? valeur : (valeur is num ? valeur.toInt() : int.tryParse('$valeur')),
        ),
      DriftSqlType.double => Variable<double>(
          valeur is double ? valeur : (valeur is num ? valeur.toDouble() : double.tryParse('$valeur')),
        ),
      DriftSqlType.dateTime => Variable<DateTime>(DateTime.tryParse('$valeur')),
      // Tout le reste est stocké en texte, y compris les objets JSON
      // (`repartition_volets`) que l'app relit tels quels.
      _ => Variable<String>(valeur is String ? valeur : jsonEncode(valeur)),
    };
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
