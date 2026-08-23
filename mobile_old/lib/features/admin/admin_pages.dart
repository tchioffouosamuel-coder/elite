import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/gestes_modules.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/format.dart';
import '../../core/ui/theme.dart';

// ------------------------------------------------------- Administration

class AnneeScolairePage extends StatelessWidget {
  const AnneeScolairePage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Année scolaire',
      chemin: 'annees-scolaires',
      gestes: Gestes.anneesScolaires,
      peutEcrire: peutEcrire(context, 'ecoles.manage'),
      messageVide: 'Aucune année scolaire.',
      construireLigne: (context, a) => LigneRessource(
        titre: '${a['libelle'] ?? '—'}',
        sousTitre: '${formaterDate(a['date_debut'])} → ${formaterDate(a['date_fin'])}',
        icone: Icons.date_range_outlined,
        badge: a['is_active'] == true
            ? const _Puce(libelle: 'Active', couleur: Couleurs.synchro)
            : null,
      ),
    );
  }
}

class PrivilegesPage extends StatelessWidget {
  const PrivilegesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Privilèges',
      chemin: 'permissions',
      champsRecherche: const ['libelle', 'code'],
      messageVide: 'Aucun privilège au catalogue.',
      construireLigne: (context, module) {
        final permissions = (module['permissions'] as List?) ?? const [];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
              child: Text(
                '${module['libelle'] ?? module['code'] ?? '—'}'.toUpperCase(),
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.6,
                  color: Couleurs.texteSecondaire,
                ),
              ),
            ),
            for (final p in permissions)
              ListTile(
                dense: true,
                leading: const Icon(Icons.verified_user_outlined, size: 18),
                title: Text('${p['libelle'] ?? p['code']}',
                    style: const TextStyle(fontSize: 13.5)),
                subtitle: Text('${p['code']}',
                    style: const TextStyle(fontSize: 11, color: Couleurs.texteSecondaire)),
              ),
          ],
        );
      },
    );
  }
}

final _ecoleProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final reponse = await ref.watch(apiClientProvider).get('ecole');
  final data = reponse['data'];
  return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
});

/// Paramètres de l'établissement, en lecture seule.
///
/// Le web permet de les modifier ; ici on se contente de les montrer. Changer
/// l'en-tête bilingue d'un bulletin ou le barème d'une mention depuis un
/// téléphone est un geste à haut risque pour un gain nul — cela se fait
/// posément, depuis un bureau.
class ParametresPage extends ConsumerWidget {
  const ParametresPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_ecoleProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Paramètres')),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(_ecoleProvider),
        ),
        data: (ecole) => ListView(
          children: [
            _Champ('Établissement', ecole['name']),
            _Champ('Code', ecole['code']),
            _Champ('Type', ecole['type']),
            _Champ('Adresse', ecole['address']),
            _Champ('Téléphone', ecole['phone']),
            _Champ('Courriel', ecole['email']),
            const Divider(),
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 14, 16, 6),
              child: Text(
                'Modification depuis le portail web',
                style: TextStyle(fontSize: 12, color: Couleurs.texteSecondaire),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Champ extends StatelessWidget {
  const _Champ(this.libelle, this.valeur);

  final String libelle;
  final dynamic valeur;

  @override
  Widget build(BuildContext context) {
    if (valeur == null || '$valeur'.isEmpty) return const SizedBox.shrink();

    return ListTile(
      title: Text(libelle,
          style: const TextStyle(fontSize: 12, color: Couleurs.texteSecondaire)),
      subtitle: Text('$valeur',
          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
    );
  }
}

class _Puce extends StatelessWidget {
  const _Puce({required this.libelle, required this.couleur});

  final String libelle;
  final Color couleur;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: couleur.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(libelle,
          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: couleur)),
    );
  }
}

// -------------------------------------------------------- Statistiques

final _statsProvider = FutureProvider.family<Map<String, dynamic>, String>((ref, chemin) async {
  final reponse = await ref.watch(apiClientProvider).get(chemin);
  final data = reponse['data'];
  return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
});

class StatistiquesPage extends ConsumerWidget {
  const StatistiquesPage({
    super.key,
    required this.titre,
    required this.chemin,
  });

  final String titre;
  final String chemin;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_statsProvider(chemin));

    return Scaffold(
      appBar: AppBar(title: Text(titre)),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(_statsProvider(chemin)),
        ),
        data: (stats) {
          if (stats.isEmpty) {
            return const EtatVide(message: 'Aucune statistique disponible.');
          }

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(_statsProvider(chemin)),
            child: ListView(
              padding: const EdgeInsets.all(14),
              children: [
                for (final entree in stats.entries)
                  _BlocStat(cle: entree.key, valeur: entree.value),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Même parti pris que pour les rapports financiers : on rend ce que le
/// serveur envoie, sans figer la liste des rubriques. Une statistique ajoutée
/// au web apparaît ici sans modification de l'app.
class _BlocStat extends StatelessWidget {
  const _BlocStat({required this.cle, required this.valeur});

  final String cle;
  final dynamic valeur;

  @override
  Widget build(BuildContext context) {
    final titre = _capitaliser(cle.replaceAll('_', ' '));

    if (valeur is num) {
      return Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(
          title: Text(titre),
          trailing: Text('$valeur',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ),
      );
    }

    if (valeur is Map) {
      return Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(titre, style: const TextStyle(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              for (final e in (valeur as Map).entries)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 3),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          _capitaliser('${e.key}'.replaceAll('_', ' ')),
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                      Text('${e.value}',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                    ],
                  ),
                ),
            ],
          ),
        ),
      );
    }

    if (valeur is List && valeur.isNotEmpty && valeur.first is Map) {
      return Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 4),
                child: Text(titre, style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
              for (final ligne in valeur.take(20))
                ListTile(
                  dense: true,
                  title: Text(
                    '${(ligne as Map).values.first}',
                    style: const TextStyle(fontSize: 13),
                  ),
                  trailing: ligne.values.length > 1
                      ? Text('${ligne.values.elementAt(1)}',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))
                      : null,
                ),
            ],
          ),
        ),
      );
    }

    return const SizedBox.shrink();
  }

  static String _capitaliser(String texte) =>
      texte.isEmpty ? texte : texte[0].toUpperCase() + texte.substring(1);
}
