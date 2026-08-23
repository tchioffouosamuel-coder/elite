import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/gestes_modules.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/format.dart';
import '../../core/ui/theme.dart';
import 'encaissement_sheet.dart';

/// Groupe « Finances », pendant des pages web du même nom.
///
/// Aucune écriture ici pour l'instant : encaisser ou arrêter une paie engage
/// une comptabilité, et se tromper depuis un téléphone coûte plus cher que le
/// temps gagné. La consultation, elle, a tout son sens en mobilité — un
/// directeur qui veut connaître l'état de la caisse depuis son bureau ou en
/// déplacement.

class CaissePage extends ConsumerWidget {
  const CaissePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // `dossiers`, pas `eleves` : c'est la clé que renvoie réellement
    // `ScolariteController::situation`.
    const requete = RequeteListe('scolarite/situation', cleListe: 'dossiers');

    return EcranListeApi(
      titre: 'Caisse',
      chemin: 'scolarite/situation',
      cleListe: 'dossiers',
      champsRecherche: const ['nom_complet', 'matricule'],
      messageVide: 'Aucun dossier de scolarité.',
      enTete: (lignes) => _BandeauCaisse(dossiers: lignes),
      construireLigne: (context, d) {
        final eleve = d['eleve'];
        final reste = (d['reste_a_payer'] as num?) ?? 0;
        final solde = reste <= 0;

        return LigneRessource(
          titre: eleve is Map ? '${eleve['nom_complet']}' : '—',
          sousTitre: [
            eleve is Map ? eleve['classe'] as String? : null,
            'Payé ${formaterMontant(d['total_paye'])} / ${formaterMontant(d['total_du'])}',
          ].where((e) => e != null).join(' · '),
          badge: Text(
            solde ? 'Soldé' : formaterMontant(reste),
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 13,
              // Un solde nul est une bonne nouvelle : il doit se distinguer au
              // premier coup d'œil dans une liste de plusieurs centaines.
              color: solde ? Couleurs.synchro : Couleurs.echec,
            ),
          ),
          onTap: () => HistoriqueVersementsSheet.ouvrir(context, d, requete),
        );
      },
      // L'encaissement n'est pas une création de ressource mais un geste sur
      // un dossier existant : il vit sur la ligne, pas dans un bouton flottant
      // qui ne saurait pas de quel élève il s'agit.
      bouton: peutEcrire(context, 'finance.encaisser')
          ? _BoutonEncaisser(requete: requete)
          : null,
    );
  }
}

/// Choisit l'élève puis ouvre l'encaissement.
///
/// Deux étapes plutôt qu'une : au guichet, l'économe part du nom de l'élève
/// qui se présente, pas d'une liste qu'il faudrait faire défiler.
class _BoutonEncaisser extends ConsumerWidget {
  const _BoutonEncaisser({required this.requete});

  final RequeteListe requete;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FloatingActionButton.extended(
      onPressed: () async {
        final dossiers = ref.read(listeApiProvider(requete)).valueOrNull ?? const [];
        final choisi = await _choisirDossier(context, dossiers);
        if (choisi == null || !context.mounted) return;

        /*
         * Le dossier doit être ouvert avant d'encaisser : la situation liste
         * tous les élèves, mais leur dossier n'est créé qu'à la demande depuis
         * la grille tarifaire — un seul sur deux cents en possédait un ici, et
         * encaisser sur les autres échouait faute d'identifiant.
         */
        final dossier = await _ouvrirDossier(context, ref, choisi);
        if (dossier == null || !context.mounted) return;

        final encaisse = await EncaissementSheet.ouvrir(context, dossier);
        if (encaisse) ref.invalidate(listeApiProvider(requete));
      },
      icon: const Icon(Icons.payments_outlined),
      label: const Text('Encaisser'),
    );
  }

  /// Ouvre (ou récupère) le dossier de scolarité de l'élève choisi.
  Future<Map<String, dynamic>?> _ouvrirDossier(
    BuildContext context,
    WidgetRef ref,
    Map<String, dynamic> choisi,
  ) async {
    final eleve = choisi['eleve'];
    final eleveId = eleve is Map ? eleve['id'] : null;
    if (eleveId == null) return choisi;

    try {
      final reponse = await ref.read(apiClientProvider).get('eleves/$eleveId/scolarite');
      final data = reponse['data'];
      return data is Map ? Map<String, dynamic>.from(data) : choisi;
    } on ErreurApi catch (e) {
      if (!context.mounted) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Couleurs.echec),
      );
      return null;
    }
  }

  Future<Map<String, dynamic>?> _choisirDossier(
    BuildContext context,
    List<Map<String, dynamic>> dossiers,
  ) {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _SelecteurEleve(dossiers: dossiers),
    );
  }
}

class _SelecteurEleve extends StatefulWidget {
  const _SelecteurEleve({required this.dossiers});

  final List<Map<String, dynamic>> dossiers;

  @override
  State<_SelecteurEleve> createState() => _SelecteurEleveState();
}

class _SelecteurEleveState extends State<_SelecteurEleve> {
  String _recherche = '';

  @override
  Widget build(BuildContext context) {
    final filtres = widget.dossiers.where((d) {
      if (_recherche.trim().isEmpty) return true;
      final eleve = d['eleve'];
      final nom = eleve is Map ? '${eleve['nom_complet']}'.toLowerCase() : '';
      return nom.contains(_recherche.toLowerCase());
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
                hintText: 'Rechercher un élève…',
                prefixIcon: Icon(Icons.search, size: 20),
                isDense: true,
              ),
              onChanged: (v) => setState(() => _recherche = v),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: ListView.separated(
              controller: controleur,
              itemCount: filtres.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (_, i) {
                final d = filtres[i];
                final eleve = d['eleve'];
                final reste = (d['reste_a_payer'] as num?) ?? 0;

                return ListTile(
                  title: Text(eleve is Map ? '${eleve['nom_complet']}' : '—'),
                  subtitle: Text(eleve is Map ? '${eleve['classe'] ?? ''}' : ''),
                  trailing: Text(
                    reste <= 0 ? 'Soldé' : formaterMontant(reste),
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: reste <= 0 ? Couleurs.synchro : Couleurs.echec,
                    ),
                  ),
                  onTap: () => Navigator.pop(context, d),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _BandeauCaisse extends StatelessWidget {
  const _BandeauCaisse({required this.dossiers});

  final List<Map<String, dynamic>> dossiers;

  @override
  Widget build(BuildContext context) {
    final du = dossiers.fold<num>(0, (t, d) => t + ((d['total_du'] as num?) ?? 0));
    final paye = dossiers.fold<num>(0, (t, d) => t + ((d['total_paye'] as num?) ?? 0));
    final impayes = dossiers.where((d) => ((d['reste_a_payer'] as num?) ?? 0) > 0).length;

    return _BandeauTotaux(entrees: [
      ('Attendu', formaterMontant(du)),
      ('Encaissé', formaterMontant(paye)),
      ('Impayés', '$impayes'),
    ]);
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
