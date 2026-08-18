import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import 'etats.dart';
import 'theme.dart';

/// Charge une liste depuis l'API. Le `.family` porte le chemin, ce qui donne
/// un cache par ressource et permet à Riverpod de dédupliquer les appels
/// quand deux écrans consultent la même.
final listeApiProvider =
    FutureProvider.family<List<Map<String, dynamic>>, RequeteListe>((ref, requete) async {
  final api = ref.watch(apiClientProvider);
  final reponse = await api.get(requete.chemin, params: requete.params);

  final data = reponse['data'];
  if (data is List) return data.cast<Map<String, dynamic>>();

  if (data is Map) {
    // Beaucoup d'endpoints renvoient la liste accompagnée de totaux ou de
    // filtres (`{articles: [...], stats: {...}}`) : l'appelant nomme alors la
    // clé qui l'intéresse. `data` couvre en plus le cas paginé.
    final cle = requete.cleListe ?? 'data';
    if (data[cle] is List) return (data[cle] as List).cast<Map<String, dynamic>>();
  }

  return const [];
});

/// Clé de cache d'une liste. `==` et `hashCode` sont redéfinis car Riverpod
/// compare les arguments de famille par valeur : sans ça, chaque
/// reconstruction relancerait la requête.
class RequeteListe {
  const RequeteListe(this.chemin, {this.params, this.cleListe});

  final String chemin;
  final Map<String, dynamic>? params;

  /// Clé sous laquelle trouver la liste quand la réponse l'accompagne de
  /// totaux ou de filtres.
  final String? cleListe;

  @override
  bool operator ==(Object other) =>
      other is RequeteListe &&
      other.chemin == chemin &&
      other.cleListe == cleListe &&
      other.params.toString() == params.toString();

  @override
  int get hashCode => Object.hash(chemin, cleListe, params.toString());
}

/// Écran de liste générique adossé à l'API.
///
/// Les trois quarts des modules du web sont une liste consultable avec une
/// recherche : les réécrire un par un multiplierait les divergences de
/// comportement (une page qui rafraîchit au tiré, une autre non). Ce socle
/// impose le même contrat partout — chargement, erreur avec réessai, vide,
/// recherche locale, tiré-pour-rafraîchir.
class EcranListeApi extends ConsumerStatefulWidget {
  const EcranListeApi({
    super.key,
    required this.titre,
    required this.chemin,
    required this.construireLigne,
    this.params,
    this.cleListe,
    this.champsRecherche = const ['nom', 'nom_complet', 'libelle', 'titre'],
    this.messageVide = 'Aucun élément.',
    this.actions,
    this.enTete,
    this.bouton,
  });

  final String titre;
  final String chemin;
  final Map<String, dynamic>? params;

  /// Clé de la liste dans la réponse, quand elle est encapsulée.
  final String? cleListe;

  /// Rendu d'une ligne. Reçoit la donnée brute de l'API : les modules en
  /// ligne n'ont pas de modèle typé, la forme suivant celle des Resources
  /// Laravel dont ils dépendent déjà.
  final Widget Function(BuildContext, Map<String, dynamic>) construireLigne;

  /// Champs sur lesquels porte la recherche, dans l'ordre de préférence.
  final List<String> champsRecherche;
  final String messageVide;
  final List<Widget>? actions;

  /// Bandeau optionnel au-dessus de la liste (totaux, filtres…).
  final Widget Function(List<Map<String, dynamic>>)? enTete;
  final Widget? bouton;

  @override
  ConsumerState<EcranListeApi> createState() => _EcranListeApiState();
}

class _EcranListeApiState extends ConsumerState<EcranListeApi> {
  String _recherche = '';

  @override
  Widget build(BuildContext context) {
    final requete = RequeteListe(widget.chemin, params: widget.params, cleListe: widget.cleListe);
    final asyncListe = ref.watch(listeApiProvider(requete));

    return Scaffold(
      appBar: AppBar(title: Text(widget.titre), actions: widget.actions),
      floatingActionButton: widget.bouton,
      body: asyncListe.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (erreur, _) => EtatErreur(
          message: erreur is ErreurApi ? erreur.message : '$erreur',
          onReessayer: () => ref.invalidate(listeApiProvider(requete)),
        ),
        data: (lignes) {
          final filtrees = _filtrer(lignes);

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(listeApiProvider(requete)),
            child: Column(
              children: [
                if (lignes.length > 8) _ChampRecherche(onChange: (v) => setState(() => _recherche = v)),
                if (widget.enTete != null) widget.enTete!(lignes),
                Expanded(
                  child: filtrees.isEmpty
                      ? EtatVide(
                          message: _recherche.isEmpty ? widget.messageVide : 'Aucun résultat.',
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.only(bottom: 88),
                          itemCount: filtrees.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (c, i) => widget.construireLigne(c, filtrees[i]),
                        ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  /// Recherche locale plutôt que côté serveur : les listes d'un établissement
  /// tiennent en mémoire (quelques centaines de lignes), et filtrer sans
  /// aller-retour reste fluide même en zone à faible débit.
  List<Map<String, dynamic>> _filtrer(List<Map<String, dynamic>> lignes) {
    if (_recherche.trim().isEmpty) return lignes;
    final terme = _recherche.toLowerCase();

    return lignes.where((ligne) {
      for (final champ in widget.champsRecherche) {
        final valeur = ligne[champ];
        if (valeur != null && '$valeur'.toLowerCase().contains(terme)) return true;
      }
      return false;
    }).toList();
  }
}

class _ChampRecherche extends StatelessWidget {
  const _ChampRecherche({required this.onChange});

  final ValueChanged<String> onChange;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
      child: TextField(
        onChanged: onChange,
        textInputAction: TextInputAction.search,
        decoration: const InputDecoration(
          hintText: 'Rechercher…',
          prefixIcon: Icon(Icons.search, size: 20),
          isDense: true,
        ),
      ),
    );
  }
}

/// Ligne standard des listes en ligne : un cercle d'initiales ou une icône,
/// un titre, un sous-titre et une valeur à droite.
class LigneRessource extends StatelessWidget {
  const LigneRessource({
    super.key,
    required this.titre,
    this.sousTitre,
    this.valeur,
    this.icone,
    this.badge,
    this.onTap,
  });

  final String titre;
  final String? sousTitre;
  final String? valeur;
  final IconData? icone;
  final Widget? badge;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        backgroundColor: Couleurs.navy900.withValues(alpha: 0.06),
        child: icone != null
            ? Icon(icone, size: 19, color: Couleurs.navy800)
            : Text(
                initiales(titre),
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: Couleurs.navy800,
                ),
              ),
      ),
      title: Text(titre, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: sousTitre == null
          ? null
          : Text(sousTitre!, maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: badge ??
          (valeur == null
              ? (onTap == null ? null : const Icon(Icons.chevron_right, size: 20))
              : Text(valeur!, style: const TextStyle(fontWeight: FontWeight.w700))),
    );
  }

  static String initiales(String texte) {
    final mots = texte.trim().split(RegExp(r'\s+'));
    return mots.take(2).map((m) => m.isEmpty ? '' : m[0]).join().toUpperCase();
  }
}
