import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/theme.dart';

/// Codes QR des salles, à imprimer et afficher au mur.
///
/// Le contenu encodé reprend exactement celui du web (`{origine}/qr/{jeton}`) :
/// un code imprimé depuis le mobile et un code imprimé depuis le portail
/// doivent être interchangeables, sans quoi une salle se retrouverait avec
/// deux affiches incompatibles.
class CodesQrPage extends StatelessWidget {
  const CodesQrPage({super.key});

  /// Origine du portail web, d'où proviennent les codes déjà imprimés.
  /// Dérivée de l'API en retirant le suffixe `/api/v1`.
  static String get _origineWeb {
    final base = Uri.parse(kApiUrl);
    final segments = base.pathSegments.where((s) => s != 'api' && s != 'v1');
    return Uri(
      scheme: base.scheme,
      host: base.host,
      port: base.hasPort ? base.port : null,
      pathSegments: segments,
    ).toString();
  }

  static String urlDe(String jeton) => '$_origineWeb/qr/$jeton';

  @override
  Widget build(BuildContext context) {
    return EcranListeApi(
      titre: 'Codes QR',
      chemin: 'classes',
      messageVide: 'Aucune classe.',
      construireLigne: (context, c) {
        final jeton = c['qr_token'] as String?;

        return LigneRessource(
          titre: '${c['nom'] ?? '—'}',
          sousTitre: jeton == null ? 'Aucun code généré' : 'Prêt à imprimer',
          icone: Icons.qr_code_2,
          onTap: jeton == null ? null : () => _afficher(context, c, jeton),
        );
      },
    );
  }

  void _afficher(BuildContext context, Map<String, dynamic> classe, String jeton) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 4, 24, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('${classe['nom']}',
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              const Text(
                'À afficher au mur de la salle',
                style: TextStyle(fontSize: 12.5, color: Couleurs.texteSecondaire),
              ),
              const SizedBox(height: 20),

              // Fond blanc explicite : un QR sur fond coloré devient illisible
              // pour beaucoup de lecteurs, et celui-ci sera photocopié.
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Couleurs.separateur),
                ),
                child: QrImageView(
                  data: urlDe(jeton),
                  size: 240,
                  backgroundColor: Colors.white,
                  errorCorrectionLevel: QrErrorCorrectLevel.M,
                ),
              ),

              const SizedBox(height: 18),
              const Text(
                "L'enseignant le scanne en entrant en classe : l'appel de la "
                'séance du jour s\'ouvre directement, même sans réseau.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12.5, color: Couleurs.texteSecondaire),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
