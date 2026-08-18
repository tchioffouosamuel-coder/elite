// `hide Column` : Drift et Flutter exposent chacun un `Column`.
import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

/// Motifs d'absence acceptés par l'API (`Presence::MOTIFS`).
const _motifs = {
  'maladie': 'Maladie',
  'permission': 'Permission',
  'scolarite': 'Scolarité',
  'inconnu': 'Non justifiée',
};

/// Feuille d'appel d'une séance.
///
/// Pensée pour être utilisable debout, d'une seule main : un tap bascule
/// présent/absent, un appui long ouvre le motif. C'est l'écran le plus
/// dépendant du hors-ligne — une salle de classe est souvent le pire endroit
/// du bâtiment pour le réseau.
class AppelPage extends ConsumerWidget {
  const AppelPage({super.key, required this.seance});

  final Seance seance;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Appel'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(20),
          child: Padding(
            padding: const EdgeInsets.only(left: 16, bottom: 10),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                '${seance.salle ?? 'Séance'} · ${seance.heureDebut?.substring(0, 5) ?? ''}',
                style: TextStyle(fontSize: 12.5, color: Theme.of(context).colorScheme.outline),
              ),
            ),
          ),
        ),
      ),
      body: StreamBuilder<List<Eleve>>(
        stream: (db.select(db.eleves)
              ..where((e) => e.classeId.equals(seance.classeId) & e.statut.equals('actif'))
              ..orderBy([(e) => OrderingTerm(expression: e.nomComplet)]))
            .watch(),
        builder: (context, snapshotEleves) {
          if (!snapshotEleves.hasData) {
            return const Center(child: CircularProgressIndicator());
          }

          final eleves = snapshotEleves.data!;
          if (eleves.isEmpty) {
            return const EtatVide(message: 'Aucun élève actif dans cette classe.');
          }

          return StreamBuilder<List<Presence>>(
            stream: (db.select(db.presences)..where((p) => p.seanceId.equals(seance.id))).watch(),
            builder: (context, snapshotPresences) {
              final presences = {
                for (final p in snapshotPresences.data ?? const <Presence>[]) p.eleveId: p,
              };

              return _Liste(
                seance: seance,
                eleves: eleves,
                presences: presences,
              );
            },
          );
        },
      ),
    );
  }
}

class _Liste extends ConsumerWidget {
  const _Liste({required this.seance, required this.eleves, required this.presences});

  final Seance seance;
  final List<Eleve> eleves;
  final Map<int, Presence> presences;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final absents = presences.values.where((p) => p.statut == 'absent').length;
    final pointes = presences.length;

    return Column(
      children: [
        _Bandeau(total: eleves.length, pointes: pointes, absents: absents),
        Expanded(
          child: ListView.separated(
            itemCount: eleves.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final eleve = eleves[i];
              final presence = presences[eleve.id];
              return _LigneEleve(
                eleve: eleve,
                presence: presence,
                onBasculer: () => _basculer(ref, eleve, presence),
                onMotif: () => _choisirMotif(context, ref, eleve, presence),
              );
            },
          ),
        ),
        _BarreValidation(
          seance: seance,
          eleves: eleves,
          presences: presences,
        ),
      ],
    );
  }

  /// Un tap bascule présent → absent → présent. Le premier tap sur un élève
  /// non pointé le marque présent : c'est le cas majoritaire, donc le geste
  /// le plus court.
  Future<void> _basculer(WidgetRef ref, Eleve eleve, Presence? presence) async {
    final db = ref.read(dbProvider);
    final nouveau = presence?.statut == 'present' ? 'absent' : 'present';

    await db.into(db.presences).insertOnConflictUpdate(
          PresencesCompanion.insert(
            id: Value(presence?.id ?? _idLocal(seance.id, eleve.id)),
            seanceId: seance.id,
            eleveId: eleve.id,
            statut: Value(nouveau),
            // Une absence sans motif est refusée par l'API : on pose le motif
            // par défaut « non justifiée », modifiable en appui long.
            motif: Value(nouveau == 'absent' ? (presence?.motif ?? 'inconnu') : null),
            etatSync: const Value('enAttente'),
          ),
        );
  }

  Future<void> _choisirMotif(
    BuildContext context,
    WidgetRef ref,
    Eleve eleve,
    Presence? presence,
  ) async {
    final choix = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
              child: Text(
                eleve.nomComplet,
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
            for (final entree in _motifs.entries)
              ListTile(
                leading: const Icon(Icons.label_outline),
                title: Text(entree.value),
                selected: presence?.motif == entree.key,
                onTap: () => Navigator.pop(context, entree.key),
              ),
          ],
        ),
      ),
    );

    if (choix == null) return;

    final db = ref.read(dbProvider);
    await db.into(db.presences).insertOnConflictUpdate(
          PresencesCompanion.insert(
            id: Value(presence?.id ?? _idLocal(seance.id, eleve.id)),
            seanceId: seance.id,
            eleveId: eleve.id,
            // Choisir un motif implique l'absence : inutile de demander deux
            // gestes pour une seule intention.
            statut: const Value('absent'),
            motif: Value(choix),
            etatSync: const Value('enAttente'),
          ),
        );
  }
}

/// Identifiant local déterministe, en attendant celui du serveur.
///
/// Dérivé de (séance, élève) plutôt qu'aléatoire : rejouer l'appel deux fois
/// hors connexion doit écraser la même ligne, pas en créer une seconde. Le
/// négatif évite toute collision avec les identifiants serveur.
int _idLocal(int seanceId, int eleveId) => -((seanceId * 100000) + eleveId);

class _Bandeau extends StatelessWidget {
  const _Bandeau({required this.total, required this.pointes, required this.absents});

  final int total;
  final int pointes;
  final int absents;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: Row(
        children: [
          Expanded(child: Text('$pointes / $total pointés')),
          if (absents > 0)
            Text(
              '$absents absent${absents > 1 ? 's' : ''}',
              style: const TextStyle(color: Couleurs.echec, fontWeight: FontWeight.bold),
            ),
        ],
      ),
    );
  }
}

class _LigneEleve extends StatelessWidget {
  const _LigneEleve({
    required this.eleve,
    required this.presence,
    required this.onBasculer,
    required this.onMotif,
  });

  final Eleve eleve;
  final Presence? presence;
  final VoidCallback onBasculer;
  final VoidCallback onMotif;

  @override
  Widget build(BuildContext context) {
    final present = presence?.statut == 'present';
    final absent = presence?.statut == 'absent';

    return ListTile(
      onTap: onBasculer,
      onLongPress: onMotif,
      leading: CircleAvatar(
        backgroundColor: absent
            ? Couleurs.echec.withValues(alpha: 0.15)
            : present
                ? Couleurs.synchro.withValues(alpha: 0.15)
                : null,
        child: Icon(
          absent ? Icons.close : (present ? Icons.check : Icons.person_outline),
          color: absent ? Couleurs.echec : (present ? Couleurs.synchro : null),
        ),
      ),
      title: Text(eleve.nomComplet),
      subtitle: absent && presence?.motif != null
          ? Text(_motifs[presence!.motif] ?? presence!.motif!,
              style: const TextStyle(color: Couleurs.echec, fontSize: 12.5))
          : Text(eleve.matricule ?? '—', style: const TextStyle(fontSize: 12.5)),
      trailing: presence == null ? null : PastilleSync(etat: presence!.etatSync),
    );
  }
}

class _BarreValidation extends ConsumerStatefulWidget {
  const _BarreValidation({
    required this.seance,
    required this.eleves,
    required this.presences,
  });

  final Seance seance;
  final List<Eleve> eleves;
  final Map<int, Presence> presences;

  @override
  ConsumerState<_BarreValidation> createState() => _BarreValidationState();
}

class _BarreValidationState extends ConsumerState<_BarreValidation> {
  bool _envoi = false;

  /// Met l'appel en file. L'écran se ferme immédiatement : l'enseignant n'a
  /// pas à attendre le réseau pour passer à autre chose — c'est tout le
  /// principe (cf. conception).
  Future<void> _valider() async {
    setState(() => _envoi = true);

    final db = ref.read(dbProvider);

    // Les élèves non pointés sont comptés présents : c'est la convention de
    // l'appel papier, et ça évite d'exiger 40 taps pour une classe complète.
    final lignes = widget.eleves.map((eleve) {
      final presence = widget.presences[eleve.id];
      final absent = presence?.statut == 'absent';
      return {
        'eleve_id': eleve.id,
        'statut': absent ? 'absent' : 'present',
        if (absent) 'motif': presence?.motif ?? 'inconnu',
        if (presence?.remarque != null) 'remarque': presence!.remarque,
      };
    }).toList();

    await ref.read(syncServiceProvider.notifier).enfiler(
          methode: 'POST',
          chemin: 'seances/${widget.seance.id}/appel',
          corps: {'lignes': lignes},
          entite: 'presences',
          entiteId: widget.seance.id,
        );

    // Les lignes non pointées sont matérialisées après coup, pour que l'écran
    // reflète exactement ce qui a été envoyé.
    await db.batch((lot) {
      for (final eleve in widget.eleves) {
        if (widget.presences.containsKey(eleve.id)) continue;
        lot.insert(
          db.presences,
          PresencesCompanion.insert(
            id: Value(_idLocal(widget.seance.id, eleve.id)),
            seanceId: widget.seance.id,
            eleveId: eleve.id,
            statut: const Value('present'),
            etatSync: const Value('enAttente'),
          ),
          mode: InsertMode.insertOrReplace,
        );
      }
    });

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Appel enregistré — envoi dès que le réseau revient.')),
    );
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: FilledButton.icon(
          onPressed: _envoi ? null : _valider,
          icon: const Icon(Icons.check_circle_outline),
          label: const Text("Valider l'appel"),
        ),
      ),
    );
  }
}
