// `hide Column` : Drift et Flutter exposent chacun un `Column`, et c'est
// évidemment celui de Flutter qu'un fichier d'interface veut.
import 'dart:async';

import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/session/session.dart';
import '../../core/sync/sync_service.dart';
import '../../core/sync/tache_fond.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';
import '../annonces/annonces_page.dart';
import '../appel/appel_page.dart';
import '../eleves/fiche_eleve_sheet.dart';
import '../notes/saisie_notes_page.dart';
import '../qr/scan_qr_page.dart';
import '../sync/centre_sync_sheet.dart';

/// Coquille de navigation.
///
/// Téléphone : barre inférieure. Tablette : rail latéral — pas la même barre
/// agrandie. Les destinations restent identiques, seule leur présentation
/// change (cf. conception).
class AccueilPage extends ConsumerStatefulWidget {
  const AccueilPage({super.key});

  @override
  ConsumerState<AccueilPage> createState() => _AccueilPageState();
}

class _AccueilPageState extends ConsumerState<AccueilPage> with WidgetsBindingObserver {
  int _onglet = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    /*
     * Synchronisation à l'ouverture. Sans elle, un utilisateur déjà connecté
     * — le cas de tous les jours — ouvrait l'app sur des données figées : la
     * synchro ne partait qu'à la connexion, au retour du réseau ou à la
     * demande, trois évènements qui ne se produisent pas en ouvrant l'app le
     * matin. C'est ce qui donnait une app vide sans le moindre message
     * d'erreur.
     *
     * Après le premier rendu : la liste s'affiche tout de suite avec ce que
     * la base locale contient déjà, et se met à jour quand le delta arrive.
     */
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(ref.read(syncServiceProvider.notifier).synchroniser());
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Retour au premier plan : l'app a pu rester ouverte des heures en poche,
  /// pendant lesquelles l'administration a inscrit des élèves ou modifié
  /// l'emploi du temps.
  @override
  void didChangeAppLifecycleState(AppLifecycleState etat) {
    if (etat == AppLifecycleState.resumed) {
      unawaited(ref.read(syncServiceProvider.notifier).synchroniser());
    }
  }

  // Cinq destinations au maximum : au-delà, les libellés se tronquent et les
  // cibles tactiles deviennent trop étroites (cf. conception).
  static const _destinations = [
    (icone: Icons.today_outlined, actif: Icons.today, libelle: 'Ma journée'),
    (icone: Icons.groups_outlined, actif: Icons.groups, libelle: 'Classes'),
    (icone: Icons.person_outline, actif: Icons.person, libelle: 'Élèves'),
    (icone: Icons.notifications_none, actif: Icons.notifications, libelle: 'Actualités'),
    (icone: Icons.more_horiz, actif: Icons.more_horiz, libelle: 'Plus'),
  ];

  @override
  Widget build(BuildContext context) {
    final large = !Ruptures.estTelephone(context);
    final corps = _corps();

    return Scaffold(
      appBar: AppBar(
        title: Text(_destinations[_onglet].libelle),
        actions: const [IndicateurSync()],
      ),
      body: large
          ? Row(
              children: [
                NavigationRail(
                  selectedIndex: _onglet,
                  onDestinationSelected: (i) => setState(() => _onglet = i),
                  labelType: NavigationRailLabelType.all,
                  destinations: [
                    for (final d in _destinations)
                      NavigationRailDestination(
                        icon: Icon(d.icone),
                        selectedIcon: Icon(d.actif),
                        label: Text(d.libelle),
                      ),
                  ],
                ),
                const VerticalDivider(width: 1),
                Expanded(child: corps),
              ],
            )
          : corps,
      // Le scan n'est pas une destination mais un geste ponctuel, fait debout
      // en entrant en classe : un bouton flottant, pas un onglet.
      floatingActionButton: _onglet == 0
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const ScanQrPage()),
              ),
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scanner'),
            )
          : null,
      bottomNavigationBar: large
          ? null
          : NavigationBar(
              selectedIndex: _onglet,
              onDestinationSelected: (i) => setState(() => _onglet = i),
              destinations: [
                for (final d in _destinations)
                  NavigationDestination(
                    icon: Icon(d.icone),
                    selectedIcon: Icon(d.actif),
                    label: d.libelle,
                  ),
              ],
            ),
    );
  }

  Widget _corps() => switch (_onglet) {
        1 => const ListeClassesVue(),
        2 => const ListeElevesVue(),
        3 => const AnnoncesPage(),
        4 => const _Plus(),
        _ => const MaJourneeVue(),
      };
}

/// Badge de synchronisation, toujours visible : l'utilisateur doit pouvoir
/// savoir d'un coup d'œil si ce qu'il a saisi est parti.
class IndicateurSync extends ConsumerWidget {
  const IndicateurSync({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final etat = ref.watch(syncServiceProvider);

    // Une panne du moteur passe avant tout le reste : afficher « à jour »
    // alors que rien n'a été écrit est le pire des états possibles.
    final (icone, couleur) = switch (etat) {
      _ when etat.panne != null => (Icons.sync_problem, Couleurs.echec),
      _ when etat.echecs > 0 => (Icons.error_outline, Couleurs.echec),
      _ when etat.enAttente > 0 => (Icons.schedule, Couleurs.enAttente),
      _ when etat.horsLigne => (Icons.cloud_off_outlined, Couleurs.navy400),
      _ => (Icons.cloud_done_outlined, Couleurs.synchro),
    };

    return IconButton(
      icon: Badge(
        isLabelVisible: etat.enAttente > 0 || etat.echecs > 0,
        label: Text('${etat.enAttente + etat.echecs}'),
        child: etat.enCours
            ? const SizedBox(
                height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
            : Icon(icone, color: couleur),
      ),
      tooltip: 'Synchronisation',
      onPressed: () => showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (_) => const CentreSyncSheet(),
      ),
    );
  }
}

/// Écran d'accueil de l'enseignant : ses séances du jour, lues depuis la base
/// locale — donc disponibles sans réseau.
class MaJourneeVue extends ConsumerWidget {
  const MaJourneeVue({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);
    final aujourdhui = DateTime.now().toIso8601String().substring(0, 10);

    return StreamBuilder(
      stream: (db.select(db.seances)
            ..where((s) => s.dateSeance.like('$aujourdhui%')))
          .watch(),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        final seances = snapshot.data!;
        if (seances.isEmpty) {
          return const EtatVide(
            message: 'Aucune séance prévue aujourd\'hui.',
            icone: Icons.event_available_outlined,
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: seances.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (_, i) {
            final s = seances[i];
            return Card(
              child: ListTile(
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => AppelPage(seance: s)),
                ),
                leading: CircleAvatar(
                  backgroundColor: Couleurs.gold100,
                  child: Text(
                    s.heureDebut?.substring(0, 5) ?? '—',
                    style: const TextStyle(fontSize: 11, color: Couleurs.navy900),
                  ),
                ),
                title: Text(s.salle ?? 'Séance'),
                subtitle: Text(s.contenu ?? 'Contenu à renseigner'),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    PastilleSync(etat: s.etatSync),
                    const SizedBox(width: 4),
                    const Icon(Icons.chevron_right),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

/// État vide d'une liste alimentée par la synchronisation.
///
/// « Aucun élève synchronisé » sans plus d'explication laissait l'utilisateur
/// devant un cul-de-sac, alors que la cause est presque toujours une synchro
/// qui n'a pas encore tourné — et qu'un bouton suffit à réparer.
class VideNonSynchronise extends ConsumerWidget {
  const VideNonSynchronise({
    super.key,
    required this.message,
    required this.icone,
    required this.ref,
  });

  final String message;
  final IconData icone;
  final WidgetRef ref;

  @override
  Widget build(BuildContext context, WidgetRef _) {
    final etat = ref.watch(syncServiceProvider);

    return EtatVide(
      message: message,
      icone: icone,
      indication: etat.enCours
          ? 'Synchronisation en cours…'
          : etat.panne ??
              "Les données n'ont pas encore été téléchargées depuis le serveur.",
      action: etat.enCours
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            )
          : FilledButton.icon(
              onPressed: () => ref.read(syncServiceProvider.notifier).synchroniser(),
              icon: const Icon(Icons.sync),
              label: const Text('Synchroniser maintenant'),
            ),
    );
  }
}

class ListeClassesVue extends ConsumerWidget {
  const ListeClassesVue({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder(
      stream: db.select(db.classes).watch(),
      builder: (context, snapshot) {
        final lignes = snapshot.data ?? const [];
        if (lignes.isEmpty) {
          return VideNonSynchronise(
            message: 'Aucune classe',
            icone: Icons.meeting_room_outlined,
            ref: ref,
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
          itemCount: lignes.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => CarteListe(
            icone: Icons.meeting_room_outlined,
            titre: lignes[i].nom,
            sousTitre: lignes[i].filiere,
            onTap: () => _ouvrirMatieres(context, ref, lignes[i]),
          ),
        );
      },
    );
  }
}

/// Matières de la classe, en feuille : choisir laquelle noter est une étape
/// intermédiaire, pas une destination — elle n'a pas à occuper une page.
Future<void> _ouvrirMatieres(BuildContext context, WidgetRef ref, ClassesData classe) async {
  final db = ref.read(dbProvider);

  final affectations = await (db.select(db.classeMatieres)
        ..where((cm) => cm.classeId.equals(classe.id)))
      .join([innerJoin(db.matieres, db.matieres.id.equalsExp(db.classeMatieres.matiereId))])
      .get();

  if (!context.mounted) return;

  await showModalBottomSheet<void>(
    context: context,
    builder: (_) => SafeArea(
      child: affectations.isEmpty
          ? const Padding(
              padding: EdgeInsets.all(28),
              child: Text('Aucune matière affectée à cette classe.'),
            )
          : ListView(
              shrinkWrap: true,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
                  child: Text(classe.nom, style: const TextStyle(fontWeight: FontWeight.bold)),
                ),
                for (final ligne in affectations)
                  ListTile(
                    leading: const Icon(Icons.edit_note),
                    title: Text(ligne.readTable(db.matieres).nom),
                    subtitle: const Text('Saisir les notes'),
                    onTap: () {
                      final cm = ligne.readTable(db.classeMatieres);
                      Navigator.pop(context);
                      Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => SaisieNotesPage(
                          classeMatiereId: cm.id,
                          classeId: classe.id,
                          titre: '${ligne.readTable(db.matieres).nom} · ${classe.nom}',
                        ),
                      ));
                    },
                  ),
              ],
            ),
    ),
  );
}

class ListeElevesVue extends ConsumerWidget {
  const ListeElevesVue({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder(
      stream: (db.select(db.eleves)..limit(200)).watch(),
      builder: (context, snapshot) {
        final lignes = snapshot.data ?? const [];
        if (lignes.isEmpty) {
          return VideNonSynchronise(
            message: 'Aucun élève',
            icone: Icons.person_outline,
            ref: ref,
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
          itemCount: lignes.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) {
            final e = lignes[i];
            return CarteListe(
              onTap: () => FicheEleveSheet.ouvrir(context, e),
              avatar: CircleAvatar(
                radius: 21,
                backgroundColor: Couleurs.navy800,
                child: Text(
                  _initiales(e.nomComplet),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              titre: e.nomComplet,
              sousTitre: e.matricule,
              fin: PastilleSync(etat: e.etatSync),
            );
          },
        );
      },
    );
  }

  String _initiales(String nom) {
    final mots = nom.trim().split(RegExp(r'\s+'));
    return mots.take(2).map((m) => m.isEmpty ? '' : m[0]).join().toUpperCase();
  }
}

class _Plus extends ConsumerWidget {
  const _Plus();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);

    return ListView(
      children: [
        ListTile(
          leading: const CircleAvatar(child: Icon(Icons.person)),
          title: Text(session?.nom ?? '—'),
          subtitle: Text('${session?.permissions.length ?? 0} privilèges'),
        ),
        const Divider(),
        ListTile(
          leading: const Icon(Icons.sync),
          title: const Text('Centre de synchronisation'),
          onTap: () => showModalBottomSheet<void>(
            context: context,
            isScrollControlled: true,
            builder: (_) => const CentreSyncSheet(),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.logout, color: Couleurs.echec),
          title: const Text('Déconnexion', style: TextStyle(color: Couleurs.echec)),
          onTap: () async {
            // La tâche de fond est annulée AVANT de fermer la session : elle
            // ne doit plus rien synchroniser au nom de l'utilisateur sortant,
            // cas réel sur un téléphone partagé entre deux surveillants.
            await annulerSyncFond();
            await ref.read(sessionProvider.notifier).fermer();
          },
        ),
      ],
    );
  }
}
