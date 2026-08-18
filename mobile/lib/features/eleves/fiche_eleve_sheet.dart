import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

/// Fiche élève en feuille à onglets.
///
/// Une feuille et non une page : l'utilisateur garde sa liste visible derrière
/// et referme d'un geste, ce qui correspond à la consultation rapide qu'on
/// fait d'un élève au milieu d'autre chose (cf. conception).
class FicheEleveSheet extends ConsumerWidget {
  const FicheEleveSheet({super.key, required this.eleve});

  final Eleve eleve;

  static Future<void> ouvrir(BuildContext context, Eleve eleve) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => FicheEleveSheet(eleve: eleve),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.75,
      maxChildSize: 0.95,
      builder: (context, controleur) => DefaultTabController(
        length: 3,
        child: Column(
          children: [
            _Entete(eleve: eleve),
            const TabBar(tabs: [
              Tab(text: 'Identité'),
              Tab(text: 'Notes'),
              Tab(text: 'Discipline'),
            ]),
            Expanded(
              child: TabBarView(
                children: [
                  _Identite(eleve: eleve, controleur: controleur),
                  _Notes(eleve: eleve),
                  _Discipline(eleve: eleve),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Entete extends ConsumerWidget {
  const _Entete({required this.eleve});

  final Eleve eleve;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 14),
      child: Row(
        children: [
          CircleAvatar(radius: 26, child: Text(_initiales(eleve.nomComplet))),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  eleve.nomComplet,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
                // `ClassesData` et non `Classe` : Drift ne sait pas singulariser
                // « Classes », il suffixe donc la classe de données.
                StreamBuilder<ClassesData?>(
                  stream: (db.select(db.classes)
                        ..where((c) => c.id.equals(eleve.classeId ?? -1)))
                      .watchSingleOrNull(),
                  builder: (context, snapshot) => Text(
                    <String?>[
                      snapshot.data?.nom,
                      eleve.matricule,
                    ].whereType<String>().where((v) => v.isNotEmpty).join(' · '),
                    style: TextStyle(
                      fontSize: 12.5,
                      color: Theme.of(context).colorScheme.outline,
                    ),
                  ),
                ),
              ],
            ),
          ),
          PastilleSync(etat: eleve.etatSync),
        ],
      ),
    );
  }
}

class _Identite extends StatelessWidget {
  const _Identite({required this.eleve, required this.controleur});

  final Eleve eleve;
  final ScrollController controleur;

  @override
  Widget build(BuildContext context) {
    return ListView(
      controller: controleur,
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        _Champ('Matricule', eleve.matricule),
        _Champ('Sexe', switch (eleve.sexe) { 'M' => 'Masculin', 'F' => 'Féminin', _ => null }),
        _Champ('Né(e) le', eleve.dateNaissance?.split('T').first),
        _Champ('Lieu de naissance', eleve.lieuNaissance),
        _Champ('Nationalité', eleve.nationalite),
        _Champ('Statut', eleve.redoublant ? 'Redoublant(e)' : 'Passant(e)'),
      ],
    );
  }
}

class _Champ extends StatelessWidget {
  const _Champ(this.libelle, this.valeur);

  final String libelle;
  final String? valeur;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      title: Text(libelle, style: TextStyle(
        fontSize: 12.5,
        color: Theme.of(context).colorScheme.outline,
      )),
      subtitle: Text(
        valeur == null || valeur!.isEmpty ? '—' : valeur!,
        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
      ),
    );
  }
}

class _Notes extends ConsumerWidget {
  const _Notes({required this.eleve});

  final Eleve eleve;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder<List<Note>>(
      stream: (db.select(db.notes)..where((n) => n.eleveId.equals(eleve.id))).watch(),
      builder: (context, snapshot) {
        final notes = (snapshot.data ?? const <Note>[]).where((n) => n.valeur != null).toList();

        if (notes.isEmpty) {
          return const EtatVide(
            message: 'Aucune note synchronisée pour cet élève.',
            icone: Icons.grade_outlined,
          );
        }

        return ListView.separated(
          itemCount: notes.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (_, i) {
            final note = notes[i];
            return StreamBuilder<TypedResult?>(
              // La matière se retrouve via l'affectation : le mobile fait ses
              // jointures en local, l'API ne renvoyant que des lignes plates.
              stream: (db.select(db.classeMatieres)
                    ..where((cm) => cm.id.equals(note.classeMatiereId)))
                  .join([
                    innerJoin(db.matieres, db.matieres.id.equalsExp(db.classeMatieres.matiereId)),
                  ])
                  .watchSingleOrNull(),
              builder: (context, snapshotMatiere) => ListTile(
                title: Text(
                  snapshotMatiere.data?.readTableOrNull(db.matieres)?.nom ?? 'Matière',
                ),
                trailing: Text(
                  note.valeur!.toStringAsFixed(2),
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                    color: note.valeur! >= 10 ? Couleurs.synchro : Couleurs.echec,
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _Discipline extends ConsumerWidget {
  const _Discipline({required this.eleve});

  final Eleve eleve;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder<List<Sanction>>(
      stream: (db.select(db.sanctions)
            ..where((s) => s.eleveId.equals(eleve.id))
            ..orderBy([(s) => OrderingTerm(expression: s.dateSanction, mode: OrderingMode.desc)]))
          .watch(),
      builder: (context, snapshot) {
        final sanctions = snapshot.data ?? const <Sanction>[];

        if (sanctions.isEmpty) {
          return const EtatVide(
            message: 'Aucune sanction enregistrée.',
            icone: Icons.verified_outlined,
          );
        }

        return ListView.separated(
          itemCount: sanctions.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (_, i) {
            final s = sanctions[i];
            return ListTile(
              leading: const Icon(Icons.gavel_outlined, color: Couleurs.enAttente),
              title: Text(_libelleType(s.type)),
              subtitle: Text(
                s.motif ?? '—',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12.5),
              ),
              trailing: Text(
                s.dateSanction?.split('T').first ?? '',
                style: const TextStyle(fontSize: 11.5),
              ),
            );
          },
        );
      },
    );
  }

  String _libelleType(String type) => switch (type) {
        'avertissement' => 'Avertissement',
        'blame' => 'Blâme',
        'corvee' => 'Corvée',
        'exclusion_temporaire' => 'Exclusion temporaire',
        'exclusion_definitive' => 'Exclusion définitive',
        _ => 'Autre',
      };
}

String _initiales(String nom) {
  final mots = nom.trim().split(RegExp(r'\s+'));
  return mots.take(2).map((m) => m.isEmpty ? '' : m[0]).join().toUpperCase();
}
