import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/nav/barre_app.dart';
import '../../core/network/api_client.dart';
import '../../core/network/documents.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/theme.dart';

/// Documents d'une classe : bulletins, PV de conseil, palmarès, cartes.
///
/// Réunis sur un même écran parce qu'ils répondent au même moment de l'année —
/// la fin de trimestre — et se produisent depuis la même sélection de classe
/// et de trimestre. Les éparpiller obligerait à refaire ce choix à chaque fois.
class BulletinsPage extends ConsumerStatefulWidget {
  const BulletinsPage({super.key});

  @override
  ConsumerState<BulletinsPage> createState() => _BulletinsPageState();
}

class _BulletinsPageState extends ConsumerState<BulletinsPage> {
  Map<String, dynamic>? _classe;
  int? _trimestreId;
  String? _enCours;

  @override
  Widget build(BuildContext context) {
    final classes = ref.watch(listeApiProvider(const RequeteListe('classes')));
    final trimestres = ref.watch(listeApiProvider(const RequeteListe('trimestres')));

    return Scaffold(
      appBar: BarreApp(titre: 'Bulletins & documents'),
      body: classes.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(listeApiProvider(const RequeteListe('classes'))),
        ),
        data: (listeClasses) {
          if (listeClasses.isEmpty) return const EtatVide(message: 'Aucune classe.');

          final classe = _classe ?? listeClasses.first;
          final listeTrimestres = trimestres.valueOrNull ?? const [];
          final trimestreId = _trimestreId ??
              (listeTrimestres.firstWhere(
                (t) => t['is_active'] == true,
                orElse: () => listeTrimestres.isEmpty ? const {} : listeTrimestres.first,
              )['id'] as int?);

          return ListView(
            padding: const EdgeInsets.all(14),
            children: [
              DropdownButtonFormField<int>(
                initialValue: classe['id'] as int?,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Classe'),
                items: [
                  for (final c in listeClasses)
                    DropdownMenuItem(value: c['id'] as int?, child: Text('${c['nom']}')),
                ],
                onChanged: (id) =>
                    setState(() => _classe = listeClasses.firstWhere((c) => c['id'] == id)),
              ),
              const SizedBox(height: 14),

              if (listeTrimestres.isNotEmpty)
                DropdownButtonFormField<int>(
                  initialValue: trimestreId,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Trimestre'),
                  items: [
                    for (final t in listeTrimestres)
                      DropdownMenuItem(value: t['id'] as int?, child: Text('${t['libelle']}')),
                  ],
                  onChanged: (id) => setState(() => _trimestreId = id),
                ),

              const SizedBox(height: 22),
              _Section('Toute la classe'),

              _Document(
                titre: 'Bulletins de la classe',
                description: 'Un élève par page, prêt à imprimer',
                icone: Icons.description_outlined,
                enCours: _enCours == 'bulletins',
                onOuvrir: () => _ouvrir(
                  cle: 'bulletins',
                  chemin: 'classes/${classe['id']}/bulletins',
                  nom: 'bulletins-${classe['nom']}.pdf',
                  trimestreId: trimestreId,
                ),
              ),
              _Document(
                titre: 'PV du conseil de classe',
                description: 'Moyennes, rangs et mentions, à compléter en séance',
                icone: Icons.groups_outlined,
                enCours: _enCours == 'pv',
                onOuvrir: () => _ouvrir(
                  cle: 'pv',
                  chemin: 'classes/${classe['id']}/pv-conseil/pdf',
                  nom: 'pv-conseil-${classe['nom']}.pdf',
                  trimestreId: trimestreId,
                ),
              ),
              _Document(
                titre: 'Cartes scolaires',
                description: 'Cartes de tous les élèves de la classe',
                icone: Icons.badge_outlined,
                enCours: _enCours == 'cartes',
                onOuvrir: () => _ouvrir(
                  cle: 'cartes',
                  chemin: 'classes/${classe['id']}/cartes-scolaires',
                  nom: 'cartes-${classe['nom']}.pdf',
                ),
              ),

              const SizedBox(height: 18),
              _Section('Établissement'),

              _Document(
                titre: 'Palmarès',
                description: 'Les meilleurs élèves du trimestre',
                icone: Icons.emoji_events_outlined,
                enCours: _enCours == 'palmares',
                onOuvrir: () => _ouvrir(
                  cle: 'palmares',
                  chemin: 'palmares/pdf',
                  nom: 'palmares.pdf',
                  trimestreId: trimestreId,
                ),
              ),

              const SizedBox(height: 18),
              _Section('Un élève'),
              _BulletinIndividuel(
                classeId: classe['id'] as int,
                trimestreId: trimestreId,
                onOuvrir: (eleve) => _ouvrir(
                  cle: 'eleve-${eleve['id']}',
                  chemin: 'eleves/${eleve['id']}/bulletin',
                  nom: 'bulletin-${eleve['nom_complet']}.pdf',
                  trimestreId: trimestreId,
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _ouvrir({
    required String cle,
    required String chemin,
    required String nom,
    int? trimestreId,
  }) async {
    setState(() => _enCours = cle);

    final erreur = await ref.read(documentsProvider).ouvrir(
          chemin,
          // Les caractères interdits d'un nom de fichier viennent des noms de
          // classe et d'élève : les remplacer évite un échec d'écriture.
          nomFichier: nom.replaceAll(RegExp(r'[^\w\-. ]'), '_'),
          params: trimestreId == null ? null : {'trimestre_id': trimestreId},
        );

    if (!mounted) return;
    setState(() => _enCours = null);

    if (erreur != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(erreur), backgroundColor: Couleurs.echec),
      );
    }
  }
}

class _Section extends StatelessWidget {
  const _Section(this.titre);

  final String titre;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        titre.toUpperCase(),
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.8,
          color: Couleurs.texteSecondaire,
        ),
      ),
    );
  }
}

class _Document extends StatelessWidget {
  const _Document({
    required this.titre,
    required this.description,
    required this.icone,
    required this.enCours,
    required this.onOuvrir,
  });

  final String titre;
  final String description;
  final IconData icone;
  final bool enCours;
  final VoidCallback onOuvrir;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        onTap: enCours ? null : onOuvrir,
        leading: CircleAvatar(
          backgroundColor: Couleurs.navy900.withValues(alpha: 0.06),
          child: Icon(icone, size: 19, color: Couleurs.navy800),
        ),
        title: Text(titre, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text(description, style: const TextStyle(fontSize: 12.5)),
        trailing: enCours
            ? const SizedBox(
                height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
            : const Icon(Icons.open_in_new, size: 18),
      ),
    );
  }
}

/// Le bulletin d'un élève précis, après recherche.
///
/// Une liste déroulante de deux cents noms serait inutilisable : on part de la
/// recherche, comme partout ailleurs dans l'app.
class _BulletinIndividuel extends ConsumerWidget {
  const _BulletinIndividuel({
    required this.classeId,
    required this.trimestreId,
    required this.onOuvrir,
  });

  final int classeId;
  final int? trimestreId;
  final void Function(Map<String, dynamic>) onOuvrir;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Couleurs.gold100,
          child: const Icon(Icons.person_search_outlined, size: 19, color: Couleurs.gold500),
        ),
        title: const Text('Bulletin individuel',
            style: TextStyle(fontWeight: FontWeight.w700)),
        subtitle: const Text('Choisir un élève de la classe',
            style: TextStyle(fontSize: 12.5)),
        trailing: const Icon(Icons.chevron_right),
        onTap: () async {
          final eleves = ref.read(listeApiProvider(const RequeteListe('eleves'))).valueOrNull;

          if (eleves == null) {
            ref.invalidate(listeApiProvider(const RequeteListe('eleves')));
            return;
          }

          final deLaClasse = eleves.where((e) {
            final classe = e['classe'];
            return classe is Map && classe['id'] == classeId;
          }).toList();

          final choisi = await showModalBottomSheet<Map<String, dynamic>>(
            context: context,
            isScrollControlled: true,
            builder: (_) => _SelecteurEleve(eleves: deLaClasse),
          );

          if (choisi != null) onOuvrir(choisi);
        },
      ),
    );
  }
}

class _SelecteurEleve extends StatefulWidget {
  const _SelecteurEleve({required this.eleves});

  final List<Map<String, dynamic>> eleves;

  @override
  State<_SelecteurEleve> createState() => _SelecteurEleveState();
}

class _SelecteurEleveState extends State<_SelecteurEleve> {
  String _recherche = '';

  @override
  Widget build(BuildContext context) {
    final filtres = widget.eleves.where((e) {
      if (_recherche.trim().isEmpty) return true;
      return '${e['nom_complet']}'.toLowerCase().contains(_recherche.toLowerCase());
    }).toList();

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.8,
      maxChildSize: 0.95,
      builder: (context, controleur) => Column(
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 4, 20, 10),
            child: Text('Quel élève ?',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              autofocus: true,
              decoration: const InputDecoration(
                hintText: 'Rechercher…',
                prefixIcon: Icon(Icons.search, size: 20),
                isDense: true,
              ),
              onChanged: (v) => setState(() => _recherche = v),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: filtres.isEmpty
                ? const EtatVide(message: 'Aucun élève.')
                : ListView.separated(
                    controller: controleur,
                    itemCount: filtres.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (_, i) => ListTile(
                      title: Text('${filtres[i]['nom_complet']}'),
                      subtitle: Text('${filtres[i]['matricule'] ?? ''}'),
                      onTap: () => Navigator.pop(context, filtres[i]),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
