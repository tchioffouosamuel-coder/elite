import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../session/session.dart';
import '../ui/theme.dart';
import 'destinations.dart';

/// Filtre les destinations selon la session, avec **les mêmes règles que le
/// web** : privilège requis, réservé au super administrateur, masqué pour un
/// titulaire, réservé aux enseignants, ou limité à certains types
/// d'établissement.
List<GroupeDestinations> destinationsVisibles(Session session) {
  final groupes = <GroupeDestinations>[];

  for (final groupe in groupesNavigation) {
    final visibles = groupe.destinations.where((d) {
      if (d.superAdminSeul && !session.estSuperAdmin) return false;
      if (d.enseignantSeul && !session.estEnseignant) return false;
      if (d.masquerPourTitulaire && session.estTitulaire) return false;
      if (d.avecAttribution && session.attributions.isEmpty) return false;
      if (d.types != null && !d.types!.contains(session.typeEcole)) return false;
      // Le super administrateur passe outre les privilèges, comme au serveur
      // (`Gate::before`) : sans ça, son menu serait vide faute de privilèges
      // nominatifs.
      if (d.permission != null && !session.estSuperAdmin && !session.peut(d.permission!)) {
        return false;
      }
      return true;
    }).toList();

    if (visibles.isNotEmpty) {
      groupes.add(GroupeDestinations(libelle: groupe.libelle, destinations: visibles));
    }
  }

  return groupes;
}

/// Tiroir de navigation, pendant de la barre latérale du web.
///
/// Un tiroir plutôt qu'une barre d'onglets : quarante-cinq destinations
/// réparties en douze groupes ne tiennent pas dans cinq onglets, et les
/// aplatir ferait perdre le regroupement par métier qui structure le web.
/// Les quelques destinations quotidiennes restent accessibles en un geste via
/// la barre du bas.
class TiroirNavigation extends ConsumerWidget {
  const TiroirNavigation({
    super.key,
    required this.cheminActif,
    required this.onNaviguer,
  });

  final String cheminActif;
  final void Function(Destination) onNaviguer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    if (session == null) return const Drawer();

    final groupes = destinationsVisibles(session);

    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            _EnteteUtilisateur(session: session),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  for (final groupe in groupes) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 14, 20, 6),
                      child: Text(
                        groupe.libelle.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.8,
                          color: Couleurs.texteSecondaire,
                        ),
                      ),
                    ),
                    for (final destination in groupe.destinations)
                      _LigneDestination(
                        destination: destination,
                        actif: destination.chemin == cheminActif,
                        onTap: () => onNaviguer(destination),
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EnteteUtilisateur extends StatelessWidget {
  const _EnteteUtilisateur({required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
      color: Couleurs.navy900,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.school, color: Couleurs.gold500, size: 26),
              const SizedBox(width: 10),
              Text(
                'Elite School',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            session.nom,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 2),
          Text(
            (session.utilisateur['fonction'] as String?) ??
                (session.estSuperAdmin ? 'Super administrateur' : 'Personnel'),
            style: const TextStyle(color: Couleurs.gold100, fontSize: 12.5),
          ),
        ],
      ),
    );
  }
}

class _LigneDestination extends StatelessWidget {
  const _LigneDestination({
    required this.destination,
    required this.actif,
    required this.onTap,
  });

  final Destination destination;
  final bool actif;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 1),
      child: Material(
        color: actif ? Couleurs.gold100.withValues(alpha: 0.6) : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
            child: Row(
              children: [
                Icon(
                  destination.icone,
                  size: 20,
                  color: actif ? Couleurs.gold500 : Couleurs.texteSecondaire,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    destination.libelle,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: actif ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ),
                // Signale ce qui reste consultable sans réseau : l'utilisateur
                // sait avant de partir en zone blanche ce qu'il pourra ouvrir.
                if (destination.horsLigne)
                  const Icon(Icons.offline_pin_outlined,
                      size: 15, color: Couleurs.synchro),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
