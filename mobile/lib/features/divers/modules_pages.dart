import 'package:flutter/material.dart';

import '../../core/ui/ecran_liste.dart';
import '../../core/ui/gestes_modules.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/format.dart';
import '../../core/ui/theme.dart';

// --------------------------------------------------------------- Santé

class InfirmeriePage extends StatelessWidget {
  const InfirmeriePage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Infirmerie',
      chemin: 'infirmerie/visites',
      gestes: Gestes.infirmerie,
      peutEcrire: peutEcrire(context, 'infirmerie.manage'),
      champsRecherche: const ['raison'],
      messageVide: 'Aucune visite enregistrée.',
      construireLigne: (context, v) => LigneRessource(
        titre: v['eleve'] is Map ? '${v['eleve']['nom_complet']}' : '—',
        sousTitre: [
          v['classe'] is Map ? '${v['classe']['nom']}' : null,
          v['raison'],
        ].where((e) => e != null).join(' · '),
        icone: Icons.medical_services_outlined,
        valeur: formaterDateCourte(v['date_visite']),
        onTap: () => _ouvrirVisite(context, v),
      ),
    );
  }

  void _ouvrirVisite(BuildContext context, Map<String, dynamic> visite) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 10),
              child: Text(
                visite['eleve'] is Map ? '${visite['eleve']['nom_complet']}' : 'Visite',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
              ),
            ),
            _Detail('Date', formaterDate(visite['date_visite'])),
            _Detail('Motif', visite['raison']),
            _Detail('Soins prodigués', visite['soins_prodiges']),
            _Detail('Coût', visite['cout_soins'] == null ? null : formaterMontant(visite['cout_soins'])),
            _Detail('Observations', visite['observations']),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail(this.libelle, this.valeur);

  final String libelle;
  final dynamic valeur;

  @override
  Widget build(BuildContext context) {
    if (valeur == null || '$valeur'.isEmpty) return const SizedBox.shrink();

    return ListTile(
      dense: true,
      title: Text(libelle,
          style: const TextStyle(fontSize: 12, color: Couleurs.texteSecondaire)),
      subtitle: Text('$valeur', style: const TextStyle(fontSize: 14.5)),
    );
  }
}

// ----------------------------------------------------------- Inventaire

class InventairePage extends StatelessWidget {
  const InventairePage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Inventaire',
      chemin: 'inventaire',
      gestes: Gestes.inventaire,
      peutEcrire: peutEcrire(context, 'inventaire.manage'),
      cleListe: 'articles',
      champsRecherche: const ['nom', 'categorie', 'localisation'],
      messageVide: 'Aucun article inventorié.',
      construireLigne: (context, a) => LigneRessource(
        titre: '${a['nom'] ?? '—'}',
        sousTitre: [
          a['categorie'],
          a['localisation'],
          if (a['etat'] != null) 'État : ${a['etat']}',
        ].where((e) => e != null).join(' · '),
        icone: Icons.inventory_2_outlined,
        valeur: a['quantite'] != null ? '${a['quantite']}' : null,
      ),
    );
  }
}

// ------------------------------------------------------------ Pédagogie

class MatieresPage extends StatelessWidget {
  const MatieresPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Matières',
      chemin: 'matieres',
      champsRecherche: const ['nom', 'nom_en', 'abbreviation'],
      messageVide: 'Aucune matière.',
      construireLigne: (context, m) => LigneRessource(
        titre: '${m['nom'] ?? '—'}',
        sousTitre: [
          m['abbreviation'],
          m['departement'] is Map ? '${m['departement']['nom']}' : null,
        ].where((e) => e != null).join(' · '),
        icone: Icons.menu_book_outlined,
        valeur: m['classes_count'] != null ? '${m['classes_count']} cl.' : null,
      ),
    );
  }
}

class SousSystemesPage extends StatelessWidget {
  const SousSystemesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Sous-systèmes',
      chemin: 'sous-systemes',
      gestes: Gestes.sousSystemes,
      peutEcrire: peutEcrire(context, 'classes.manage'),
      champsRecherche: const ['nom', 'code'],
      messageVide: 'Aucun sous-système.',
      construireLigne: (context, s) => LigneRessource(
        titre: '${s['nom'] ?? '—'}',
        sousTitre: s['description'] as String?,
        icone: Icons.layers_outlined,
        valeur: s['code'] as String?,
      ),
    );
  }
}

class ProgressionPage extends StatelessWidget {
  const ProgressionPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Progression',
      chemin: 'progression',
      champsRecherche: const ['titre', 'type'],
      messageVide: 'Aucune progression enregistrée.',
      construireLigne: (context, p) => LigneRessource(
        titre: '${p['titre'] ?? '—'}',
        sousTitre: p['description'] as String?,
        icone: Icons.account_tree_outlined,
        valeur: p['type'] as String?,
      ),
    );
  }
}

// ------------------------------------------------------------- Résultats

class PalmaresPage extends StatelessWidget {
  const PalmaresPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Palmarès',
      chemin: 'palmares',
      cleListe: 'eleves',
      messageVide: 'Aucun élève au palmarès pour ce trimestre.',
      construireLigne: (context, e) => LigneRessource(
        titre: '${e['nom_complet'] ?? '—'}',
        sousTitre: e['classe'] as String?,
        valeur: e['moyenne'] == null
            ? null
            : (e['moyenne'] as num).toStringAsFixed(2),
      ),
    );
  }
}

class RevendicationsPage extends StatelessWidget {
  const RevendicationsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Réclamations',
      chemin: 'revendications',
      gestes: Gestes.revendications,
      peutEcrire: peutEcrire(context, 'revendications.manage'),
      champsRecherche: const ['objet', 'motif'],
      messageVide: 'Aucune réclamation.',
      construireLigne: (context, r) => LigneRessource(
        titre: '${r['objet'] ?? '—'}',
        sousTitre: r['eleve'] is Map ? '${r['eleve']['nom_complet']}' : null,
        badge: _BadgeStatut(statut: '${r['statut']}'),
      ),
    );
  }
}

/// Statuts d'une réclamation, avec les mêmes couleurs sémantiques que le web.
class _BadgeStatut extends StatelessWidget {
  const _BadgeStatut({required this.statut});

  final String statut;

  @override
  Widget build(BuildContext context) {
    final (libelle, couleur) = switch (statut) {
      'en_attente' => ('En attente', Couleurs.enAttente),
      'en_cours' => ('En cours', Couleurs.navy800),
      'resolue' => ('Résolue', Couleurs.synchro),
      'rejetee' => ('Rejetée', Couleurs.echec),
      _ => (statut, Couleurs.texteSecondaire),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: couleur.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        libelle,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: couleur),
      ),
    );
  }
}
