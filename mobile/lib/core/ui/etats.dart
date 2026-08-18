import 'package:flutter/material.dart';

import 'theme.dart';

/// Les quatre états que tout écran doit savoir rendre.
///
/// Le quatrième — donnée périmée — est propre au hors-ligne et le plus souvent
/// oublié : une donnée ancienne s'affiche normalement, avec sa date, jamais
/// masquée derrière un écran d'erreur (cf. conception).
class EtatVide extends StatelessWidget {
  const EtatVide({
    super.key,
    required this.message,
    this.icone = Icons.inbox_outlined,
    this.indication,
    this.action,
  });

  final String message;
  final IconData icone;

  /// Ce qu'il faut faire pour sortir de cet état. Un écran vide qui se
  /// contente de constater le vide laisse l'utilisateur sans issue — surtout
  /// ici, où « aucune donnée » signifie presque toujours « pas encore
  /// synchronisé », ce qui se répare d'un geste.
  final String? indication;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final attenue = Theme.of(context).colorScheme.outline;

    // Défilable : ces états s'affichent aussi dans des espaces contraints — la
    // feuille de synchronisation, par exemple, où un long message d'erreur
    // au-dessus ne laisse que quelques dizaines de pixels. Sans ça, Flutter
    // barre l'écran d'un avertissement de débordement.
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                color: Couleurs.gold100.withValues(alpha: 0.55),
                shape: BoxShape.circle,
              ),
              child: Icon(icone, size: 38, color: Couleurs.gold500),
            ),
            const SizedBox(height: 18),
            Text(
              message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
            ),
            if (indication != null) ...[
              const SizedBox(height: 8),
              Text(
                indication!,
                textAlign: TextAlign.center,
                style: TextStyle(color: attenue, fontSize: 13.5, height: 1.4),
              ),
            ],
            if (action != null) ...[const SizedBox(height: 20), action!],
          ],
        ),
      ),
    );
  }
}

/// Ligne de liste en carte, reprise du parti pris de _smapp : une carte
/// blanche détachée du fond, plutôt qu'une ligne collée à ses voisines.
/// L'écran respire et chaque élément se touche plus facilement.
class CarteListe extends StatelessWidget {
  const CarteListe({
    super.key,
    required this.titre,
    this.sousTitre,
    this.icone,
    this.avatar,
    this.fin,
    this.onTap,
  });

  final String titre;
  final String? sousTitre;
  final IconData? icone;
  final Widget? avatar;
  final Widget? fin;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          child: Row(
            children: [
              if (avatar != null)
                avatar!
              else if (icone != null)
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Couleurs.gold100.withValues(alpha: 0.6),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icone, size: 21, color: Couleurs.gold500),
                ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      titre,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                    ),
                    if (sousTitre != null && sousTitre!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        sousTitre!,
                        style: const TextStyle(
                          fontSize: 12.5,
                          color: Couleurs.texteSecondaire,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (fin != null) fin!,
              if (onTap != null) ...[
                const SizedBox(width: 4),
                const Icon(Icons.chevron_right, size: 20, color: Couleurs.texteSecondaire),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class EtatErreur extends StatelessWidget {
  const EtatErreur({super.key, required this.message, this.onReessayer});

  final String message;
  final VoidCallback? onReessayer;

  @override
  Widget build(BuildContext context) {
    // Défilable : ces états s'affichent aussi dans des espaces contraints — la
    // feuille de synchronisation, par exemple, où un long message d'erreur
    // au-dessus ne laisse que quelques dizaines de pixels. Sans ça, Flutter
    // barre l'écran d'un avertissement de débordement.
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 44, color: Couleurs.echec),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (onReessayer != null) ...[
              const SizedBox(height: 16),
              FilledButton.tonal(onPressed: onReessayer, child: const Text('Réessayer')),
            ],
          ],
        ),
      ),
    );
  }
}

/// Bandeau de fraîcheur, à poser en tête d'un écran en lecture seule.
class BandeauPerime extends StatelessWidget {
  const BandeauPerime({super.key, required this.depuis});

  final DateTime? depuis;

  @override
  Widget build(BuildContext context) {
    final texte = depuis == null
        ? 'Données jamais synchronisées'
        : 'Données du ${_format(depuis!)}';

    return Container(
      width: double.infinity,
      color: Couleurs.enAttente.withValues(alpha: 0.12),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          const Icon(Icons.cloud_off_outlined, size: 16, color: Couleurs.enAttente),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              texte,
              style: const TextStyle(fontSize: 12.5, color: Couleurs.enAttente),
            ),
          ),
        ],
      ),
    );
  }

  String _format(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')} à '
      '${d.hour.toString().padLeft(2, '0')}h${d.minute.toString().padLeft(2, '0')}';
}

/// Pastille d'état d'une ligne : l'équivalent des ✓ de WhatsApp.
class PastilleSync extends StatelessWidget {
  const PastilleSync({super.key, required this.etat});

  final String etat;

  @override
  Widget build(BuildContext context) {
    final (icone, couleur, libelle) = switch (etat) {
      'enAttente' => (Icons.schedule, Couleurs.enAttente, 'En attente'),
      'echoue' => (Icons.error_outline, Couleurs.echec, 'Échec'),
      _ => (Icons.check, Couleurs.synchro, 'Synchronisé'),
    };

    return Tooltip(
      message: libelle,
      child: Icon(icone, size: 15, color: couleur),
    );
  }
}
