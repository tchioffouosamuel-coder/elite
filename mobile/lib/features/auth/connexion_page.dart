import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/session/session.dart';
import '../../core/sync/sync_service.dart';
import '../../core/sync/tache_fond.dart';
import '../../core/ui/theme.dart';

class ConnexionPage extends ConsumerStatefulWidget {
  const ConnexionPage({super.key});

  @override
  ConsumerState<ConnexionPage> createState() => _ConnexionPageState();
}

class _ConnexionPageState extends ConsumerState<ConnexionPage> {
  final _email = TextEditingController();
  final _motDePasse = TextEditingController();
  final _formulaire = GlobalKey<FormState>();
  bool _envoi = false;
  String? _erreur;
  String? _attente;
  Timer? _minuteurReveil;

  @override
  void dispose() {
    _minuteurReveil?.cancel();
    _email.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _connecter() async {
    if (!_formulaire.currentState!.validate()) return;

    setState(() {
      _envoi = true;
      _erreur = null;
      _attente = null;
    });

    /*
     * L'hébergement met le service en veille : le premier appel de la journée
     * met une quarantaine de secondes. Un spinner muet aussi longtemps
     * passerait pour un plantage — au bout de cinq secondes, on explique.
     */
    _minuteurReveil = Timer(const Duration(seconds: 5), () {
      if (mounted && _envoi) {
        setState(() => _attente = 'Réveil du serveur, cela peut prendre une minute…');
      }
    });

    try {
      final api = ref.read(apiClientProvider);
      final reponse = await api.post('/auth/login', {
        'email': _email.text.trim(),
        'password': _motDePasse.text,
      });

      final data = reponse['data'] as Map<String, dynamic>;
      final utilisateur = Map<String, dynamic>.from(data['user'] as Map);

      await ref.read(sessionProvider.notifier).ouvrir(Session(
            jeton: data['token'] as String,
            utilisateur: utilisateur,
            schoolId: (utilisateur['school_id'] as num?)?.toInt() ?? 0,
            permissions: List<String>.from(
              (utilisateur['permissions'] as List?) ?? const [],
            ),
          ));

      // Première synchronisation lancée sans attendre : l'utilisateur entre
      // dans l'app pendant que les données descendent.
      unawaited(ref.read(syncServiceProvider.notifier).synchroniser());
      unawaited(programmerSyncFond());
    } on ErreurApi catch (e) {
      setState(() => _erreur = e.message);
    } finally {
      _minuteurReveil?.cancel();
      if (mounted) {
        setState(() {
          _envoi = false;
          _attente = null;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              // Sur tablette, un formulaire étiré sur 1 000 px est illisible :
              // on le borne, centré, plutôt que de le laisser s'étaler.
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formulaire,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Icon(Icons.school, size: 52, color: Couleurs.gold500),
                    const SizedBox(height: 16),
                    Text(
                      'Fondation ELITES',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Accédez à votre espace de gestion scolaire',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Theme.of(context).colorScheme.outline),
                    ),
                    const SizedBox(height: 28),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(labelText: 'Adresse e-mail'),
                      validator: (v) =>
                          (v == null || !v.contains('@')) ? 'Adresse e-mail invalide' : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _motDePasse,
                      obscureText: true,
                      autofillHints: const [AutofillHints.password],
                      decoration: const InputDecoration(labelText: 'Mot de passe'),
                      onFieldSubmitted: (_) => _connecter(),
                      validator: (v) =>
                          (v == null || v.isEmpty) ? 'Saisissez votre mot de passe' : null,
                    ),
                    if (_erreur != null) ...[
                      const SizedBox(height: 14),
                      Text(_erreur!, style: const TextStyle(color: Couleurs.echec)),
                    ],
                    if (_attente != null) ...[
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          const Icon(Icons.hourglass_top, size: 16, color: Couleurs.enAttente),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _attente!,
                              style: const TextStyle(color: Couleurs.enAttente, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 22),
                    FilledButton(
                      onPressed: _envoi ? null : _connecter,
                      child: _envoi
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Se connecter'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
