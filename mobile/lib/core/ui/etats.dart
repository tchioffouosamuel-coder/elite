import 'package:flutter/material.dart';

import 'theme.dart';

/// Les quatre états que tout écran doit savoir rendre.
///
/// Le quatrième — donnée périmée — est propre au hors-ligne et le plus souvent
/// oublié : une donnée ancienne s'affiche normalement, avec sa date, jamais
/// masquée derrière un écran d'erreur (cf. conception).
class EtatVide extends StatelessWidget {
  const EtatVide({super.key, required this.message, this.icone = Icons.inbox_outlined});

  final String message;
  final IconData icone;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icone, size: 44, color: Theme.of(context).colorScheme.outline),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Theme.of(context).colorScheme.outline),
            ),
          ],
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
    return Center(
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
