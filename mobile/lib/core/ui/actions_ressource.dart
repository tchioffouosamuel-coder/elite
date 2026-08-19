import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import 'ecran_liste.dart';
import 'formulaire.dart';
import 'theme.dart';

/// Décrit ce qu'un module permet de faire sur sa ressource.
///
/// Un seul objet par module : c'est lui qui rend la parité avec le web
/// vérifiable action par action, au lieu de la disperser dans des boutons
/// écrits au cas par cas.
class ActionsRessource {
  const ActionsRessource({
    required this.nomSingulier,
    required this.chemin,
    required this.champs,
    this.cheminCreation,
    this.peutModifier = true,
    this.peutSupprimer = true,
    this.actionsSupplementaires,
  });

  final String nomSingulier;

  /// Chemin de collection (`bus/vehicules`). L'identifiant y est ajouté pour
  /// modifier ou supprimer.
  final String chemin;

  /// Chemin de création s'il diffère de la collection.
  final String? cheminCreation;

  final List<Champ> champs;
  final bool peutModifier;
  final bool peutSupprimer;

  /// Actions propres au module (payer, annuler, notifier…), au-delà du
  /// modifier/supprimer commun.
  final List<ActionMetier>? actionsSupplementaires;

  String get cheminEcriture => cheminCreation ?? chemin;
}

/// Action métier sur une ligne : un appel POST sur un sous-chemin.
class ActionMetier {
  const ActionMetier({
    required this.libelle,
    required this.icone,
    required this.sousChemin,
    this.confirmation,
    this.couleur,
    this.visible,
  });

  final String libelle;
  final IconData icone;

  /// Sous-chemin ajouté après l'identifiant : `payer`, `annuler`, `activer`…
  final String sousChemin;

  /// Texte de confirmation, dès que l'action engage quelque chose
  /// d'irréversible — un encaissement annulé, une paie arrêtée.
  final String? confirmation;

  final Color? couleur;

  /// Décide si l'action s'applique à cette ligne : annuler une dépense déjà
  /// annulée n'a pas de sens, et proposer le geste laisse croire l'inverse.
  final bool Function(Map<String, dynamic>)? visible;
}

/// Bouton de création, posé en bouton flottant d'une liste.
class BoutonCreer extends ConsumerWidget {
  const BoutonCreer({super.key, required this.actions, required this.requete});

  final ActionsRessource actions;
  final RequeteListe requete;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return FloatingActionButton.extended(
      onPressed: () async {
        final ecrit = await FormulaireSheet.ouvrir(
          context,
          titre: 'Nouveau — ${actions.nomSingulier}',
          champs: actions.champs,
          chemin: actions.cheminEcriture,
          messageSucces: '${actions.nomSingulier} enregistré.',
        );
        if (ecrit) ref.invalidate(listeApiProvider(requete));
      },
      icon: const Icon(Icons.add),
      label: const Text('Ajouter'),
    );
  }
}

/// Menu d'actions d'une ligne : modifier, supprimer, plus les actions métier.
class MenuActionsLigne extends ConsumerWidget {
  const MenuActionsLigne({
    super.key,
    required this.actions,
    required this.ligne,
    required this.requete,
  });

  final ActionsRessource actions;
  final Map<String, dynamic> ligne;
  final RequeteListe requete;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final metier = (actions.actionsSupplementaires ?? const <ActionMetier>[])
        .where((a) => a.visible?.call(ligne) ?? true)
        .toList();

    if (!actions.peutModifier && !actions.peutSupprimer && metier.isEmpty) {
      return const SizedBox.shrink();
    }

    return PopupMenuButton<String>(
      icon: const Icon(Icons.more_vert, size: 20),
      tooltip: 'Actions',
      itemBuilder: (_) => [
        if (actions.peutModifier)
          const PopupMenuItem(
            value: '__modifier',
            child: ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.edit_outlined, size: 19),
              title: Text('Modifier'),
            ),
          ),
        for (final a in metier)
          PopupMenuItem(
            value: a.sousChemin,
            child: ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: Icon(a.icone, size: 19, color: a.couleur),
              title: Text(a.libelle, style: TextStyle(color: a.couleur)),
            ),
          ),
        if (actions.peutSupprimer)
          const PopupMenuItem(
            value: '__supprimer',
            child: ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.delete_outline, size: 19, color: Couleurs.echec),
              title: Text('Supprimer', style: TextStyle(color: Couleurs.echec)),
            ),
          ),
      ],
      onSelected: (choix) => _executer(context, ref, choix, metier),
    );
  }

  Future<void> _executer(
    BuildContext context,
    WidgetRef ref,
    String choix,
    List<ActionMetier> metier,
  ) async {
    final id = ligne['id'];

    if (choix == '__modifier') {
      final ecrit = await FormulaireSheet.ouvrir(
        context,
        titre: 'Modifier — ${actions.nomSingulier}',
        champs: actions.champs,
        chemin: '${actions.chemin}/$id',
        methode: 'PUT',
        // Sans les valeurs existantes, « modifier » exigerait de tout resaisir.
        valeursInitiales: _valeursInitiales(),
        messageSucces: '${actions.nomSingulier} mis à jour.',
      );
      if (ecrit) ref.invalidate(listeApiProvider(requete));
      return;
    }

    if (choix == '__supprimer') {
      final confirme = await _confirmer(
        context,
        'Supprimer ce ${actions.nomSingulier.toLowerCase()} ?',
        'Cette action est définitive.',
      );
      if (!confirme || !context.mounted) return;
      await _appeler(context, ref, '${actions.chemin}/$id', methode: 'DELETE');
      return;
    }

    final action = metier.firstWhere((a) => a.sousChemin == choix);
    if (action.confirmation != null) {
      final confirme = await _confirmer(context, action.libelle, action.confirmation!);
      if (!confirme || !context.mounted) return;
    }
    await _appeler(context, ref, '${actions.chemin}/$id/${action.sousChemin}');
  }

  /// Aplatit la ligne pour pré-remplir le formulaire : l'API renvoie des
  /// relations imbriquées (`{classe: {id, nom}}`) alors que la saisie attend
  /// l'identifiant (`classe_id`).
  Map<String, dynamic> _valeursInitiales() {
    final valeurs = <String, dynamic>{};

    for (final champ in actions.champs) {
      if (ligne.containsKey(champ.cle)) {
        valeurs[champ.cle] = ligne[champ.cle];
        continue;
      }
      if (champ.cle.endsWith('_id')) {
        final relation = champ.cle.substring(0, champ.cle.length - 3);
        final valeur = ligne[relation];
        if (valeur is Map && valeur['id'] != null) valeurs[champ.cle] = valeur['id'];
      }
    }

    return valeurs;
  }

  Future<bool> _confirmer(BuildContext context, String titre, String message) async {
    final reponse = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(titre),
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Confirmer')),
        ],
      ),
    );
    return reponse ?? false;
  }

  Future<void> _appeler(
    BuildContext context,
    WidgetRef ref,
    String chemin, {
    String methode = 'POST',
  }) async {
    try {
      await ref.read(apiClientProvider).post(
            chemin,
            methode == 'POST' ? const {} : {'_method': methode},
          );
      ref.invalidate(listeApiProvider(requete));
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Action effectuée.')),
      );
    } on ErreurApi catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Couleurs.echec),
      );
    }
  }
}
