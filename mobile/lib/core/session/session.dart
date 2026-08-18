import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Compte connecté et établissement actif.
///
/// Le jeton Sanctum va au stockage sécurisé (Keychain / Keystore) et jamais
/// dans les préférences : sur un téléphone rooté ou une sauvegarde cloud, un
/// jeton en clair vaut un accès complet aux données de l'établissement.
class Session {
  const Session({
    required this.jeton,
    required this.utilisateur,
    required this.schoolId,
    required this.permissions,
  });

  final String jeton;
  final Map<String, dynamic> utilisateur;
  final int schoolId;
  final List<String> permissions;

  String get nom => (utilisateur['name'] ?? utilisateur['email'] ?? '') as String;

  /// Mêmes noms de privilèges que le web (`notes.create`, `discipline.view`…) :
  /// une règle d'accès s'exprime à l'identique des deux côtés.
  bool peut(String permission) => permissions.contains(permission);

  Map<String, dynamic> versJson() => {
        'jeton': jeton,
        'utilisateur': utilisateur,
        'schoolId': schoolId,
        'permissions': permissions,
      };

  static Session depuisJson(Map<String, dynamic> json) => Session(
        jeton: json['jeton'] as String,
        utilisateur: Map<String, dynamic>.from(json['utilisateur'] as Map),
        schoolId: json['schoolId'] as int,
        permissions: List<String>.from(json['permissions'] as List? ?? const []),
      );
}

class SessionNotifier extends StateNotifier<Session?> {
  SessionNotifier(this._coffre) : super(null);

  final FlutterSecureStorage _coffre;
  static const _cle = 'session';

  /// Restaure la session au démarrage. Une app hors-ligne doit pouvoir
  /// s'ouvrir sur ses données sans joindre le serveur pour se ré-authentifier.
  Future<void> restaurer() async {
    final brut = await _coffre.read(key: _cle);
    if (brut == null) return;

    try {
      state = Session.depuisJson(jsonDecode(brut) as Map<String, dynamic>);
    } catch (_) {
      // Session illisible (format changé entre deux versions) : on repart
      // proprement plutôt que de bloquer le démarrage.
      await _coffre.delete(key: _cle);
    }
  }

  Future<void> ouvrir(Session session) async {
    state = session;
    await _coffre.write(key: _cle, value: jsonEncode(session.versJson()));
  }

  Future<void> fermer() async {
    state = null;
    await _coffre.delete(key: _cle);
  }
}

final coffreProvider = Provider((_) => const FlutterSecureStorage());

final sessionProvider = StateNotifierProvider<SessionNotifier, Session?>(
  (ref) => SessionNotifier(ref.watch(coffreProvider)),
);
