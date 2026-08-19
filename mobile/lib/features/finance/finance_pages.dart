import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/gestes_modules.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/format.dart';
import '../../core/ui/theme.dart';

/// Groupe « Finances », pendant des pages web du même nom.
///
/// Aucune écriture ici pour l'instant : encaisser ou arrêter une paie engage
/// une comptabilité, et se tromper depuis un téléphone coûte plus cher que le
/// temps gagné. La consultation, elle, a tout son sens en mobilité — un
/// directeur qui veut connaître l'état de la caisse depuis son bureau ou en
/// déplacement.

class CaissePage extends StatelessWidget {
  const CaissePage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Caisse',
      chemin: 'scolarite/situation',
      cleListe: 'eleves',
      messageVide: 'Aucun dossier de scolarité.',
      enTete: (lignes) {
        final du = lignes.fold<num>(0, (t, l) => t + ((l['montant_du'] as num?) ?? 0));
        final paye = lignes.fold<num>(0, (t, l) => t + ((l['montant_paye'] as num?) ?? 0));
        return _BandeauTotaux(entrees: [
          ('Attendu', formaterMontant(du)),
          ('Encaissé', formaterMontant(paye)),
          ('Reste', formaterMontant(du - paye)),
        ]);
      },
      construireLigne: (context, e) {
        final du = (e['montant_du'] as num?) ?? 0;
        final paye = (e['montant_paye'] as num?) ?? 0;
        final solde = du - paye;

        return LigneRessource(
          titre: e['eleve'] is Map ? '${e['eleve']['nom_complet']}' : '${e['nom_complet'] ?? '—'}',
          sousTitre: e['classe'] is Map ? '${e['classe']['nom']}' : e['classe'] as String?,
          badge: Text(
            formaterMontant(solde),
            style: TextStyle(
              fontWeight: FontWeight.w800,
              // Un solde nul est une bonne nouvelle : il doit se distinguer
              // au premier coup d'œil dans une liste de plusieurs centaines.
              color: solde <= 0 ? Couleurs.synchro : Couleurs.echec,
            ),
          ),
        );
      },
    );
  }
}

class TarifsPage extends StatelessWidget {
  const TarifsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Tarifs',
      chemin: 'tarifs',
      cleListe: 'classes',
      messageVide: 'Aucun tarif défini.',
      construireLigne: (context, c) => LigneRessource(
        titre: '${c['nom'] ?? '—'}',
        sousTitre: c['niveau'] as String?,
        icone: Icons.sell_outlined,
        valeur: c['tarif'] == null ? '—' : formaterMontant(c['tarif']),
      ),
    );
  }
}

class DepensesPage extends StatelessWidget {
  const DepensesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Dépenses',
      chemin: 'depenses',
      gestes: Gestes.depenses,
      peutEcrire: peutEcrire(context, 'finance.depenses'),
      cleListe: 'depenses',
      champsRecherche: const ['libelle', 'fournisseur'],
      messageVide: 'Aucune dépense enregistrée.',
      construireLigne: (context, d) => LigneRessource(
        titre: '${d['libelle'] ?? '—'}',
        sousTitre: [
          d['fournisseur'],
          formaterDate(d['date_depense']),
        ].where((e) => e != null).join(' · '),
        icone: Icons.receipt_long_outlined,
        valeur: formaterMontant(d['montant']),
      ),
    );
  }
}

class SalairesPage extends StatelessWidget {
  const SalairesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Salaires',
      chemin: 'remunerations',
      cleListe: 'personnels',
      messageVide: 'Aucune rémunération paramétrée.',
      construireLigne: (context, p) => LigneRessource(
        titre: '${p['nom_complet'] ?? '—'}',
        sousTitre: p['fonction'] as String?,
        valeur: p['salaire_base'] == null ? '—' : formaterMontant(p['salaire_base']),
      ),
    );
  }
}

class PaiePage extends StatelessWidget {
  const PaiePage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Paie',
      chemin: 'paie',
      cleListe: 'bulletins',
      messageVide: 'Aucun bulletin de paie pour cette période.',
      construireLigne: (context, b) => LigneRessource(
        titre: b['personnel'] is Map ? '${b['personnel']['nom_complet']}' : '—',
        sousTitre: b['statut'] as String?,
        valeur: b['net_a_payer'] == null ? null : formaterMontant(b['net_a_payer']),
      ),
    );
  }
}

class AvancesSalairePage extends StatelessWidget {
  const AvancesSalairePage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Avances sur salaire',
      chemin: 'avances-salaire',
      gestes: Gestes.avances,
      peutEcrire: peutEcrire(context, 'finance.paie'),
      cleListe: 'avances',
      messageVide: 'Aucune avance en cours.',
      construireLigne: (context, a) => LigneRessource(
        titre: a['personnel'] is Map ? '${a['personnel']['nom_complet']}' : '—',
        sousTitre: formaterDate(a['date_avance']),
        icone: Icons.savings_outlined,
        valeur: formaterMontant(a['montant']),
      ),
    );
  }
}

/// Rapports financiers : quatre états distincts, réunis en onglets plutôt
/// qu'en quatre entrées de menu — c'est la même question posée sous quatre
/// angles, et l'utilisateur les compare.
class RapportsFinanciersPage extends ConsumerWidget {
  const RapportsFinanciersPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Rapports financiers'),
          bottom: const TabBar(
            isScrollable: true,
            tabs: [
              Tab(text: 'Tableau de bord'),
              Tab(text: 'Trésorerie'),
              Tab(text: 'Résultat'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _Rapport(chemin: 'rapports/tableau-de-bord'),
            _Rapport(chemin: 'rapports/tresorerie'),
            _Rapport(chemin: 'rapports/resultat'),
          ],
        ),
      ),
    );
  }
}

final _rapportProvider = FutureProvider.family<Map<String, dynamic>, String>((ref, chemin) async {
  final reponse = await ref.watch(apiClientProvider).get(chemin);
  final data = reponse['data'];
  return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
});

class _Rapport extends ConsumerWidget {
  const _Rapport({required this.chemin});

  final String chemin;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_rapportProvider(chemin));

    return async.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => EtatErreur(
        message: e is ErreurApi ? e.message : '$e',
        onReessayer: () => ref.invalidate(_rapportProvider(chemin)),
      ),
      data: (rapport) {
        if (rapport.isEmpty) {
          return const EtatVide(message: 'Aucune donnée pour cette période.');
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(_rapportProvider(chemin)),
          child: ListView(
            padding: const EdgeInsets.all(14),
            children: [for (final entree in rapport.entries) _Bloc(cle: entree.key, valeur: entree.value)],
          ),
        );
      },
    );
  }
}

/// Rend une entrée de rapport sans connaître sa forme : les états financiers
/// varient d'un exercice à l'autre, et coder chaque champ en dur ferait
/// disparaître silencieusement toute nouvelle rubrique ajoutée au serveur.
class _Bloc extends StatelessWidget {
  const _Bloc({required this.cle, required this.valeur});

  final String cle;
  final dynamic valeur;

  @override
  Widget build(BuildContext context) {
    final titre = cle.replaceAll('_', ' ');

    if (valeur is Map) {
      return Card(
        margin: const EdgeInsets.only(bottom: 10),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(_capitaliser(titre),
                  style: const TextStyle(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              for (final e in (valeur as Map).entries)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 3),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(_capitaliser(e.key.toString().replaceAll('_', ' ')),
                            style: const TextStyle(fontSize: 13)),
                      ),
                      Text(
                        e.value is num ? formaterMontant(e.value) : '${e.value}',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      );
    }

    if (valeur is num) {
      return Card(
        margin: const EdgeInsets.only(bottom: 10),
        child: ListTile(
          title: Text(_capitaliser(titre)),
          trailing: Text(formaterMontant(valeur),
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        ),
      );
    }

    return const SizedBox.shrink();
  }

  static String _capitaliser(String texte) =>
      texte.isEmpty ? texte : texte[0].toUpperCase() + texte.substring(1);
}

class _BandeauTotaux extends StatelessWidget {
  const _BandeauTotaux({required this.entrees});

  final List<(String, String)> entrees;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      color: Couleurs.navy900.withValues(alpha: 0.04),
      child: Row(
        children: [
          for (final (libelle, valeur) in entrees)
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(libelle,
                      style: const TextStyle(fontSize: 11, color: Couleurs.texteSecondaire)),
                  const SizedBox(height: 2),
                  Text(valeur,
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
