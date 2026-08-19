import 'package:flutter/material.dart';

import '../../core/ui/ecran_liste.dart';
import '../../core/ui/gestes_modules.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/format.dart';
import '../../core/ui/theme.dart';

/// Groupe « Transport scolaire ». Consulté depuis l'administration, jamais en
/// mobilité : en ligne directe, sans réplication locale.

class BusVehiculesPage extends StatelessWidget {
  const BusVehiculesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Véhicules',
      chemin: 'bus/vehicules',
      gestes: Gestes.vehicules,
      peutEcrire: peutEcrire(context, 'bus.manage'),
      champsRecherche: const ['immatriculation', 'marque'],
      messageVide: 'Aucun véhicule enregistré.',
      construireLigne: (context, v) => LigneRessource(
        titre: '${v['immatriculation'] ?? '—'}',
        sousTitre: [
          v['marque'],
          v['chauffeur'] is Map ? v['chauffeur']['nom_complet'] : null,
        ].where((e) => e != null).join(' · '),
        icone: Icons.directions_bus_outlined,
        valeur: v['capacite'] != null ? '${v['capacite']} pl.' : null,
      ),
    );
  }
}

class BusTrajetsPage extends StatelessWidget {
  const BusTrajetsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Trajets',
      chemin: 'bus/trajets',
      gestes: Gestes.trajets,
      peutEcrire: peutEcrire(context, 'bus.manage'),
      messageVide: 'Aucun trajet défini.',
      construireLigne: (context, t) {
        final arrets = (t['arrets'] as List?)?.length ?? 0;
        return LigneRessource(
          titre: '${t['nom'] ?? '—'}',
          sousTitre: [
            if (arrets > 0) '$arrets arrêt${arrets > 1 ? 's' : ''}',
            if (t['vehicule'] is Map) '${t['vehicule']['immatriculation']}',
          ].join(' · '),
          icone: Icons.route_outlined,
          valeur: t['effectif'] != null ? '${t['effectif']}' : null,
          onTap: () => _ouvrirTrajet(context, t),
        );
      },
    );
  }

  /// Le détail d'un trajet tient dans une feuille : ses arrêts et ses trois
  /// tarifs se lisent d'un bloc, sans changer d'écran.
  void _ouvrirTrajet(BuildContext context, Map<String, dynamic> trajet) {
    final arrets = (trajet['arrets'] as List?) ?? const [];

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        maxChildSize: 0.9,
        builder: (context, controleur) => ListView(
          controller: controleur,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
              child: Text('${trajet['nom'] ?? '—'}',
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
            ),
            _LigneTarif('Aller simple', trajet['tarif_aller_simple']),
            _LigneTarif('Retour simple', trajet['tarif_retour_simple']),
            _LigneTarif('Aller-retour', trajet['tarif_aller_retour']),
            const Divider(),
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 8, 20, 4),
              child: Text('Arrêts', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
            if (arrets.isEmpty)
              const Padding(
                padding: EdgeInsets.fromLTRB(20, 6, 20, 20),
                child: Text('Aucun arrêt sur ce trajet.',
                    style: TextStyle(color: Couleurs.texteSecondaire)),
              ),
            for (final arret in arrets)
              ListTile(
                dense: true,
                leading: const Icon(Icons.location_on_outlined, size: 19),
                title: Text('${arret['nom'] ?? '—'}'),
                subtitle: arret['heure_passage'] == null
                    ? null
                    : Text('${arret['heure_passage']}'),
              ),
          ],
        ),
      ),
    );
  }
}

class _LigneTarif extends StatelessWidget {
  const _LigneTarif(this.libelle, this.montant);

  final String libelle;
  final dynamic montant;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      title: Text(libelle, style: const TextStyle(fontSize: 13)),
      trailing: Text(
        montant == null ? '—' : formaterMontant(montant),
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
    );
  }
}

class BusArretsPage extends StatelessWidget {
  const BusArretsPage({super.key});

  @override
  Widget build(BuildContext context) {
    // Les arrêts n'ont pas d'endpoint propre : ils appartiennent aux trajets,
    // comme au web. On les aplatit ici pour offrir la même vue d'ensemble.
    return EcranListeApi(
      titre: 'Arrêts',
      chemin: 'bus/trajets',
      messageVide: 'Aucun arrêt défini.',
      construireLigne: (context, t) {
        final arrets = (t['arrets'] as List?) ?? const [];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Text('${t['nom'] ?? '—'}',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
            ),
            if (arrets.isEmpty)
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: Text('Aucun arrêt.',
                    style: TextStyle(color: Couleurs.texteSecondaire, fontSize: 12.5)),
              ),
            for (final arret in arrets)
              ListTile(
                dense: true,
                leading: const Icon(Icons.location_on_outlined, size: 18),
                title: Text('${arret['nom'] ?? '—'}', style: const TextStyle(fontSize: 13.5)),
                trailing: arret['heure_passage'] == null
                    ? null
                    : Text('${arret['heure_passage']}', style: const TextStyle(fontSize: 12)),
              ),
          ],
        );
      },
    );
  }
}

class BusAffectationsPage extends StatelessWidget {
  const BusAffectationsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Élèves du bus',
      chemin: 'bus/eleves',
      messageVide: 'Aucun élève inscrit au transport.',
      construireLigne: (context, a) => LigneRessource(
        titre: a['eleve'] is Map ? '${a['eleve']['nom_complet']}' : '—',
        sousTitre: [
          a['trajet'] is Map ? '${a['trajet']['nom']}' : null,
          a['option'],
        ].where((e) => e != null).join(' · '),
        valeur: a['montant'] != null ? formaterMontant(a['montant']) : null,
      ),
    );
  }
}
