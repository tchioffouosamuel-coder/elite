import 'package:drift/native.dart';
import 'package:elites_mobile/core/db/database.dart';
import 'package:flutter_test/flutter_test.dart';

/// Vérifie que le delta serveur atterrit réellement en base.
///
/// Ce test existe à cause d'un bug précis : les valeurs étaient insérées via
/// des `Variable<Object>`, un type que Drift ne sait pas convertir en SQL.
/// Le code compilait et `flutter analyze` ne signalait rien — l'insertion
/// levait à l'exécution, l'exception était avalée, et l'application affichait
/// « à jour » avec zéro donnée. Seul un test d'exécution pouvait l'attraper.
void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.pourTests(NativeDatabase.memory()));
  tearDown(() => db.close());

  test('applique un delta aux formes de valeurs que renvoie vraiment l\'API', () async {
    await db.appliquerDelta(
      {
        'eleves': [
          {
            'id': 4601,
            'school_id': 1,
            'classe_id': 170,
            'matricule': '25SEC79',
            'nom_complet': 'ASSIMBA NDJAGA ROGER TRESOR',
            'sexe': 'M',
            // Date ISO complète, telle que Carbon la sérialise.
            'date_naissance': '2012-11-13T00:00:00.000000Z',
            'lieu_naissance': 'MAROUA',
            'nationalite': null,
            // Booléen JSON, pas 0/1.
            'redoublant': false,
            'statut': 'actif',
            'photo_path': null,
          },
        ],
        'classes': [
          {'id': 170, 'school_id': 1, 'nom': 'ELECTRICITY 4', 'capacite': 40},
        ],
        'matieres': [
          {
            'id': 7,
            'school_id': 1,
            'nom': 'Chimie',
            'evalue_pratique': true,
            // Objet JSON imbriqué, stocké en texte.
            'repartition_volets': {'oral': 5, 'ecrit': 15},
          },
        ],
        'classe_matieres': [
          // `coefficient` est réel côté Drift mais arrive en entier depuis JSON.
          {'id': 12, 'classe_id': 170, 'matiere_id': 7, 'coefficient': 2},
        ],
      },
      const [],
    );

    final eleves = await db.select(db.eleves).get();
    expect(eleves, hasLength(1), reason: 'la ligne élève doit être écrite');
    expect(eleves.single.nomComplet, 'ASSIMBA NDJAGA ROGER TRESOR');
    expect(eleves.single.redoublant, isFalse);
    expect(eleves.single.etatSync, 'synchro');

    expect(await db.select(db.classes).get(), hasLength(1));

    final matiere = (await db.select(db.matieres).get()).single;
    expect(matiere.evaluePratique, isTrue);
    expect(matiere.repartitionVolets, contains('oral'));

    final affectation = (await db.select(db.classeMatieres).get()).single;
    expect(affectation.coefficient, 2.0, reason: 'un entier JSON doit tenir dans une colonne réelle');
  });

  test('un second delta met à jour la ligne au lieu de la dupliquer', () async {
    Future<void> envoyer(String nom) => db.appliquerDelta({
          'eleves': [
            {'id': 1, 'school_id': 1, 'nom_complet': nom, 'redoublant': false},
          ],
        }, const []);

    await envoyer('AVANT');
    await envoyer('APRES');

    final eleves = await db.select(db.eleves).get();
    expect(eleves, hasLength(1));
    expect(eleves.single.nomComplet, 'APRES');
  });

  test('une pierre tombale supprime la ligne locale', () async {
    await db.appliquerDelta({
      'eleves': [
        {'id': 42, 'school_id': 1, 'nom_complet': 'PARTI', 'redoublant': false},
      ],
    }, const []);
    expect(await db.select(db.eleves).get(), hasLength(1));

    await db.appliquerDelta(const {}, const [(entite: 'eleves', id: 42)]);
    expect(await db.select(db.eleves).get(), isEmpty);
  });
}
