import 'package:flutter/material.dart';

import '../../core/ui/ecran_liste.dart';
import '../../core/ui/theme.dart';

/// Groupe « Personnel & structure », pendant des pages web du même nom.
///
/// Ces modules sont consultés depuis un bureau, jamais en salle de classe :
/// ils interrogent l'API directement au lieu de peser sur la base locale,
/// réservée à ce qui doit survivre à une coupure.

class PersonnelPage extends StatelessWidget {
  const PersonnelPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Personnel',
      chemin: 'personnels',
      messageVide: 'Aucun membre du personnel.',
      construireLigne: (context, p) => LigneRessource(
        titre: '${p['nom_complet'] ?? '—'}',
        sousTitre: [
          p['fonction'],
          p['departement'] is Map ? p['departement']['nom'] : null,
        ].where((v) => v != null).join(' · '),
        badge: p['statut'] == 'actif'
            ? null
            : const Badge(label: Text('Inactif'), backgroundColor: Couleurs.texteSecondaire),
        onTap: () => FichePersonnelSheet.ouvrir(context, p),
      ),
    );
  }
}

/// Fiche détaillée en feuille, comme la fiche élève : on consulte un membre
/// du personnel au milieu d'autre chose, sans perdre la liste derrière.
class FichePersonnelSheet extends StatelessWidget {
  const FichePersonnelSheet({super.key, required this.personnel});

  final Map<String, dynamic> personnel;

  static Future<void> ouvrir(BuildContext context, Map<String, dynamic> personnel) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => FichePersonnelSheet(personnel: personnel),
    );
  }

  @override
  Widget build(BuildContext context) {
    final champs = <(String, dynamic)>[
      ('Matricule', personnel['matricule']),
      ('Fonction', personnel['fonction']),
      ('Département', personnel['departement'] is Map ? personnel['departement']['nom'] : null),
      ('Téléphone', personnel['telephone']),
      ('Courriel', personnel['email']),
      ('Sexe', switch (personnel['sexe']) { 'M' => 'Masculin', 'F' => 'Féminin', _ => null }),
      ('Statut', personnel['statut']),
    ];

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.6,
      maxChildSize: 0.9,
      builder: (context, controleur) => ListView(
        controller: controleur,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 16),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 25,
                  backgroundColor: Couleurs.navy900.withValues(alpha: 0.07),
                  child: Text(
                    LigneRessource.initiales('${personnel['nom_complet'] ?? '?'}'),
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Couleurs.navy800,
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    '${personnel['nom_complet'] ?? '—'}',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          for (final (libelle, valeur) in champs)
            if (valeur != null && '$valeur'.isNotEmpty)
              ListTile(
                dense: true,
                title: Text(libelle,
                    style: const TextStyle(fontSize: 12, color: Couleurs.texteSecondaire)),
                subtitle: Text('$valeur',
                    style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600)),
              ),
        ],
      ),
    );
  }
}

class DepartementsPage extends StatelessWidget {
  const DepartementsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Départements',
      chemin: 'departements',
      messageVide: 'Aucun département.',
      construireLigne: (context, d) => LigneRessource(
        titre: '${d['nom'] ?? '—'}',
        sousTitre: d['responsable'] is Map ? '${d['responsable']['nom_complet']}' : null,
        icone: Icons.account_tree_outlined,
        valeur: d['matieres_count'] != null ? '${d['matieres_count']}' : null,
      ),
    );
  }
}

class FonctionsPage extends StatelessWidget {
  const FonctionsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Fonctions',
      chemin: 'fonctions-referentiel',
      champsRecherche: const ['label_fr', 'label_en', 'code'],
      messageVide: 'Aucune fonction au référentiel.',
      construireLigne: (context, f) => LigneRessource(
        titre: '${f['label_fr'] ?? f['code'] ?? '—'}',
        sousTitre: f['label_en'] as String?,
        icone: Icons.work_outline,
      ),
    );
  }
}

class NiveauxScolairesPage extends StatelessWidget {
  const NiveauxScolairesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Niveaux',
      chemin: 'niveaux-scolaires',
      messageVide: 'Aucun niveau.',
      construireLigne: (context, n) => LigneRessource(
        titre: '${n['nom'] ?? n['libelle'] ?? '—'}',
        sousTitre: n['responsable'] is Map ? '${n['responsable']['nom_complet']}' : null,
        icone: Icons.stairs_outlined,
      ),
    );
  }
}

class NiveauxGlobauxPage extends StatelessWidget {
  const NiveauxGlobauxPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Niveaux globaux',
      chemin: 'niveaux',
      champsRecherche: const ['name_fr', 'name_en', 'code'],
      messageVide: 'Aucun niveau global.',
      construireLigne: (context, n) => LigneRessource(
        titre: '${n['name_fr'] ?? n['code'] ?? '—'}',
        sousTitre: n['name_en'] as String?,
        icone: Icons.layers_outlined,
      ),
    );
  }
}
