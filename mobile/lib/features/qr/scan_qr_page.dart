import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/providers.dart';
import '../../core/ui/theme.dart';
import '../appel/appel_page.dart';

/// Scan du QR code affiché au mur de la salle.
///
/// Contrairement à l'endpoint serveur `/ma-journee/qr/{token}`, la résolution
/// se fait **entièrement en local** : le jeton identifie une classe déjà
/// répliquée, et la séance du jour est cherchée dans la base du téléphone.
/// C'est indispensable — le geste a lieu en entrant en classe, précisément là
/// où le réseau manque.
class ScanQrPage extends ConsumerStatefulWidget {
  const ScanQrPage({super.key});

  @override
  ConsumerState<ScanQrPage> createState() => _ScanQrPageState();
}

class _ScanQrPageState extends ConsumerState<ScanQrPage> {
  final _controleur = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [BarcodeFormat.qrCode],
  );

  /// Verrou : sans lui, la caméra enchaîne les détections et empile plusieurs
  /// fois le même écran avant même la première transition.
  bool _traitement = false;
  String? _message;

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  Future<void> _surDetection(BarcodeCapture capture) async {
    if (_traitement) return;

    final jeton = capture.barcodes.firstOrNull?.rawValue?.trim();
    if (jeton == null || jeton.isEmpty) return;

    setState(() {
      _traitement = true;
      _message = null;
    });

    final db = ref.read(dbProvider);

    final classe = await (db.select(db.classes)
          ..where((c) => c.qrToken.equals(jeton))
          ..limit(1))
        .getSingleOrNull();

    if (classe == null) {
      _echouer('Ce QR code ne correspond à aucune salle connue.');
      return;
    }

    // Séance du jour pour cette classe. On ne filtre pas sur l'heure exacte
    // comme le fait le serveur : un enseignant en retard doit pouvoir faire
    // son appel, et lui refuser au motif que le créneau est passé serait
    // absurde une fois qu'il est devant sa classe.
    final aujourdhui = DateTime.now().toIso8601String().substring(0, 10);
    final seance = await (db.select(db.seances)
          ..where((s) => s.classeId.equals(classe.id) & s.dateSeance.like('$aujourdhui%'))
          ..orderBy([(s) => OrderingTerm(expression: s.heureDebut)])
          ..limit(1))
        .getSingleOrNull();

    if (seance == null) {
      _echouer("Aucune séance n'est enregistrée aujourd'hui pour ${classe.nom}.");
      return;
    }

    if (!mounted) return;
    await Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(builder: (_) => AppelPage(seance: seance)),
    );
  }

  void _echouer(String message) {
    if (!mounted) return;
    setState(() {
      _message = message;
      _traitement = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scanner la salle'),
        actions: [
          IconButton(
            icon: const Icon(Icons.flashlight_on_outlined),
            tooltip: 'Torche',
            onPressed: () => _controleur.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(controller: _controleur, onDetect: _surDetection),
          // Viseur : sans repère, l'utilisateur ne sait pas où présenter le
          // code et balaye la pièce.
          Center(
            child: Container(
              width: 230,
              height: 230,
              decoration: BoxDecoration(
                border: Border.all(color: Couleurs.gold500, width: 3),
                borderRadius: BorderRadius.circular(18),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: SafeArea(
              child: Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.72),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    Icon(
                      _message == null ? Icons.qr_code_scanner : Icons.error_outline,
                      color: _message == null ? Colors.white : Couleurs.echec,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        _message ??
                            'Présentez le QR code affiché au mur de la salle. '
                                'Fonctionne sans réseau.',
                        style: const TextStyle(color: Colors.white, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
