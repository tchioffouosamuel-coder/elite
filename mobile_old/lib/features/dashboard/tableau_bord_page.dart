import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

final _statsProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final reponse = await ref.watch(apiClientProvider).get('dashboard');
  return Map<String, dynamic>.from(reponse['data'] as Map);
});

/// Tableau de bord, pendant du `DashboardPage` du web.
///
/// L'API renvoie deux formes selon le profil (`scope`) : la vue
/// établissement pour l'administration, ou celle d'une seule classe pour un
/// titulaire. On respecte cette distinction plutôt que d'imposer la vue
/// globale à un enseignant, à qui elle ne dirait rien.
class TableauBordPage extends ConsumerWidget {
  const TableauBordPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_statsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Tableau de bord')),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(_statsProvider),
        ),
        data: (stats) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(_statsProvider),
          child: ListView(
            padding: const EdgeInsets.all(14),
            children: [
              if (stats['annee_scolaire_active'] != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    'Année scolaire ${stats['annee_scolaire_active']}',
                    style: const TextStyle(
                      color: Couleurs.texteSecondaire,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              _Cartes(stats: stats),
              const SizedBox(height: 8),
              _RepartitionGenre(stats: stats),
              _TopClasses(stats: stats),
              _Activite(stats: stats),
            ],
          ),
        ),
      ),
    );
  }
}

class _Cartes extends StatelessWidget {
  const _Cartes({required this.stats});

  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    final effectifs = stats['effectifs'] as Map? ?? const {};

    final cartes = [
      ('Élèves', effectifs['eleves'], Icons.person_outline, Couleurs.navy800),
      ('Classes', effectifs['classes'], Icons.meeting_room_outlined, Couleurs.gold500),
      ('Personnel', effectifs['personnel'], Icons.badge_outlined, Couleurs.synchro),
      ('Enseignants', effectifs['enseignants'], Icons.school_outlined, Couleurs.enAttente),
    ].where((c) => c.$2 != null).toList();

    // Deux colonnes sur téléphone, quatre dès qu'il y a la place : une
    // tablette doit montrer *plus*, pas *plus gros*.
    final colonnes = Ruptures.estTelephone(context) ? 2 : 4;

    return GridView.count(
      crossAxisCount: colonnes,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 1.5,
      children: [
        for (final (libelle, valeur, icone, couleur) in cartes)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(icone, size: 18, color: couleur),
                      const Spacer(),
                    ],
                  ),
                  const Spacer(),
                  Text(
                    '$valeur',
                    style: TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      color: couleur,
                    ),
                  ),
                  Text(
                    libelle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Couleurs.texteSecondaire,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _RepartitionGenre extends StatelessWidget {
  const _RepartitionGenre({required this.stats});

  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    final genre = stats['repartition_genre'] as Map?;
    if (genre == null) return const SizedBox.shrink();

    final garcons = (genre['garcons'] as num?)?.toInt() ?? 0;
    final filles = (genre['filles'] as num?)?.toInt() ?? 0;
    final total = garcons + filles;
    if (total == 0) return const SizedBox.shrink();

    final indicateurs = stats['indicateurs'] as Map? ?? const {};

    return Card(
      margin: const EdgeInsets.only(top: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Répartition par genre',
                style: TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 14),
            // Une barre proportionnelle plutôt qu'un camembert : elle reste
            // lisible sur 300 px de large et se compare d'un coup d'œil.
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: SizedBox(
                height: 12,
                child: Row(
                  children: [
                    Expanded(flex: garcons, child: Container(color: Couleurs.navy800)),
                    Expanded(flex: filles, child: Container(color: Couleurs.gold500)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const _Pastille(couleur: Couleurs.navy800, libelle: 'Garçons'),
                Text(' $garcons', style: const TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(width: 18),
                const _Pastille(couleur: Couleurs.gold500, libelle: 'Filles'),
                Text(' $filles', style: const TextStyle(fontWeight: FontWeight.w700)),
                const Spacer(),
                if (indicateurs['taux_filles'] != null)
                  Text(
                    '${indicateurs['taux_filles']}%',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Couleurs.gold500,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Pastille extends StatelessWidget {
  const _Pastille({required this.couleur, required this.libelle});

  final Color couleur;
  final String libelle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 9,
          height: 9,
          decoration: BoxDecoration(color: couleur, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(libelle, style: const TextStyle(fontSize: 12.5)),
      ],
    );
  }
}

class _TopClasses extends StatelessWidget {
  const _TopClasses({required this.stats});

  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    final classes = stats['top_classes'] as List? ?? const [];
    if (classes.isEmpty) return const SizedBox.shrink();

    final maximum = classes
        .map((c) => (c['effectif'] as num?)?.toInt() ?? 0)
        .fold<int>(1, (a, b) => a > b ? a : b);

    return Card(
      margin: const EdgeInsets.only(top: 10),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Classes les plus nombreuses',
                style: TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            for (final classe in classes) ...[
              Row(
                children: [
                  SizedBox(
                    width: 96,
                    child: Text('${classe['classe']}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12.5)),
                  ),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: ((classe['effectif'] as num?)?.toInt() ?? 0) / maximum,
                        minHeight: 8,
                        backgroundColor: Couleurs.navy900.withValues(alpha: 0.06),
                        valueColor: const AlwaysStoppedAnimation(Couleurs.navy800),
                      ),
                    ),
                  ),
                  SizedBox(
                    width: 34,
                    child: Text('  ${classe['effectif']}',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
                  ),
                ],
              ),
              const SizedBox(height: 8),
            ],
          ],
        ),
      ),
    );
  }
}

class _Activite extends StatelessWidget {
  const _Activite({required this.stats});

  final Map<String, dynamic> stats;

  @override
  Widget build(BuildContext context) {
    final activites = stats['activite_recente'] as List? ?? const [];
    if (activites.isEmpty) return const SizedBox.shrink();

    return Card(
      margin: const EdgeInsets.only(top: 10),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 10, 16, 4),
              child: Text('Activité récente',
                  style: TextStyle(fontWeight: FontWeight.w700)),
            ),
            for (final activite in activites)
              ListTile(
                dense: true,
                leading: const Icon(Icons.history, size: 18),
                title: Text('${activite['libelle']}',
                    style: const TextStyle(fontSize: 12.5)),
                subtitle: activite['date'] == null
                    ? null
                    : Text('${activite['date']}'.split('T').first,
                        style: const TextStyle(fontSize: 11)),
              ),
          ],
        ),
      ),
    );
  }
}
