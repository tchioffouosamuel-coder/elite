import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:drift/drift.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../db/database.dart';
import '../network/api_client.dart';
import '../providers.dart';

/// Ce que l'interface affiche du moteur : l'équivalent honnête des ✓✓.
class EtatSync {
  const EtatSync({
    this.enCours = false,
    this.enAttente = 0,
    this.echecs = 0,
    this.derniereReussite,
    this.horsLigne = false,
    this.panne,
  });

  final bool enCours;
  final int enAttente;
  final int echecs;
  final DateTime? derniereReussite;
  final bool horsLigne;

  /// Dernière panne du moteur lui-même, distincte d'un refus serveur sur une
  /// opération. Sans ce champ, un défaut d'écriture locale reste invisible et
  /// l'app affiche « à jour » alors qu'elle n'a rien enregistré — exactement
  /// ce qui s'était produit avec les variables Drift mal typées.
  final String? panne;

  EtatSync copie({
    bool? enCours,
    int? enAttente,
    int? echecs,
    DateTime? derniereReussite,
    bool? horsLigne,
    String? panne,
    bool effacerPanne = false,
  }) =>
      EtatSync(
        enCours: enCours ?? this.enCours,
        enAttente: enAttente ?? this.enAttente,
        echecs: echecs ?? this.echecs,
        derniereReussite: derniereReussite ?? this.derniereReussite,
        horsLigne: horsLigne ?? this.horsLigne,
        panne: effacerPanne ? null : (panne ?? this.panne),
      );
}

/// Moteur de synchronisation : vide l'outbox, puis récupère le delta serveur.
///
/// L'ordre compte. Pousser d'abord évite qu'un delta entrant écrase une
/// écriture locale pas encore partie — le serveur ne peut arbitrer que ce
/// qu'il connaît.
class SyncService extends StateNotifier<EtatSync> {
  SyncService(this._db, this._api) : super(const EtatSync()) {
    _surveillerReseau();
  }

  final AppDatabase _db;
  final ApiClient _api;
  static const _cleCurseur = 'curseur';
  static const _cleDerniereReussite = 'derniere_reussite';
  static const _uuid = Uuid();

  StreamSubscription? _abonnementReseau;
  bool _enCours = false;

  @override
  void dispose() {
    _abonnementReseau?.cancel();
    super.dispose();
  }

  void _surveillerReseau() {
    _abonnementReseau = Connectivity().onConnectivityChanged.listen((resultats) {
      final connecte = !resultats.contains(ConnectivityResult.none);
      state = state.copie(horsLigne: !connecte);

      // Le retour du réseau est le déclencheur principal : c'est là que la
      // saisie faite en classe part enfin.
      if (connecte) unawaited(synchroniser());
    });
  }

  /// Met une écriture en file et la reflète immédiatement en local.
  ///
  /// L'identifiant généré ici sert de clé d'idempotence côté serveur : il est
  /// créé au moment du geste, pas au moment de l'envoi, pour qu'un rejeu
  /// porte bien la même clé que la tentative d'origine.
  Future<String> enfiler({
    required String methode,
    required String chemin,
    required Map<String, dynamic> corps,
    String? entite,
    int? entiteId,
  }) async {
    final id = _uuid.v4();

    await _db.into(_db.outboxOperations).insert(OutboxOperationsCompanion.insert(
          id: id,
          methode: methode,
          chemin: chemin,
          corps: jsonEncode(corps),
          entite: Value(entite),
          entiteId: Value(entiteId),
          creeLe: DateTime.now(),
        ));

    await _rafraichirCompteurs();
    unawaited(synchroniser());

    return id;
  }

  /// Un cycle complet. Réentrant : deux déclencheurs simultanés (retour du
  /// réseau + reprise au premier plan) ne doivent pas pousser deux fois.
  Future<void> synchroniser() async {
    if (_enCours) return;
    _enCours = true;
    state = state.copie(enCours: true);

    try {
      await _viderOutbox();
      await _recupererDelta();

      final maintenant = DateTime.now();
      await _ecrireEtat(_cleDerniereReussite, maintenant.toIso8601String());
      state = state.copie(
        derniereReussite: maintenant,
        horsLigne: false,
        effacerPanne: true,
      );
    } on ErreurApi catch (e) {
      state = state.copie(horsLigne: e.horsLigne, panne: e.horsLigne ? null : e.message);
    } catch (e, pile) {
      /*
       * Tout le reste : écriture locale refusée, charge utile inattendue,
       * migration ratée. Ces pannes-là ne doivent surtout pas disparaître —
       * `synchroniser()` est appelé sans `await` un peu partout, donc une
       * exception non rattrapée ici serait silencieuse et l'app afficherait
       * « à jour » sans avoir rien écrit.
       */
      debugPrintStack(stackTrace: pile, label: 'Synchronisation: $e');
      state = state.copie(panne: e.toString());
    } finally {
      _enCours = false;
      state = state.copie(enCours: false);
      await _rafraichirCompteurs();
    }
  }

  Future<void> _viderOutbox() async {
    final maintenant = DateTime.now();

    final operations = await (_db.select(_db.outboxOperations)
          ..where((o) => o.prochainEssai.isSmallerOrEqualValue(maintenant) | o.prochainEssai.isNull())
          ..orderBy([(o) => OrderingTerm(expression: o.creeLe)])
          ..limit(50))
        .get();

    if (operations.isEmpty) return;

    final reponse = await _api.post('/sync', {
      'operations': operations
          .map((o) => {
                'id': o.id,
                'methode': o.methode,
                'chemin': o.chemin,
                'corps': jsonDecode(o.corps),
              })
          .toList(),
    });

    final resultats = (reponse['data']?['resultats'] as List? ?? const []);

    for (final resultat in resultats.cast<Map<String, dynamic>>()) {
      final id = resultat['id'] as String;
      final statut = resultat['statut'] as int? ?? 0;

      if (statut >= 200 && statut < 300) {
        await (_db.delete(_db.outboxOperations)..where((o) => o.id.equals(id))).go();
        continue;
      }

      /*
       * 4xx : le serveur a compris et refusé (validation, séquence
       * verrouillée…). Réessayer ne changerait rien — l'opération reste en
       * file, marquée en échec, pour que l'utilisateur tranche depuis le
       * centre de synchro. Le reste (5xx) est retenté avec un back-off.
       */
      final definitif = statut >= 400 && statut < 500;
      final operation = operations.firstWhere((o) => o.id == id);
      final tentatives = operation.tentatives + 1;

      await (_db.update(_db.outboxOperations)..where((o) => o.id.equals(id))).write(
        OutboxOperationsCompanion(
          tentatives: Value(tentatives),
          derniereErreur: Value(_messageDe(resultat['reponse'])),
          prochainEssai: Value(definitif ? null : maintenant.add(_backoff(tentatives))),
        ),
      );
    }
  }

  /// 2 s, 4 s, 8 s… plafonné à 5 min : assez réactif au retour du réseau, sans
  /// marteler un serveur qui redémarre.
  Duration _backoff(int tentatives) {
    final secondes = 2 << (tentatives.clamp(1, 8) - 1);
    return Duration(seconds: secondes.clamp(2, 300));
  }

  String _messageDe(Object? reponse) {
    if (reponse is Map && reponse['message'] is String) return reponse['message'] as String;
    return 'Refusé par le serveur.';
  }

  /// Boucle tant que le serveur annonce un delta incomplet : une première
  /// synchronisation se fait ainsi en plusieurs passes sans intervention.
  Future<void> _recupererDelta() async {
    var complet = false;
    var passes = 0;

    while (!complet && passes < 20) {
      final curseur = await _lireEtat(_cleCurseur);
      final reponse = await _api.get('/sync', params: {
        if (curseur != null) 'depuis': curseur,
      });

      final data = reponse['data'] as Map<String, dynamic>;
      final donnees = (data['donnees'] as Map?)?.cast<String, dynamic>() ?? {};

      await _db.appliquerDelta(
        donnees.map((cle, valeur) => MapEntry(
              cle,
              (valeur as List).cast<Map<String, dynamic>>(),
            )),
        ((data['suppressions'] as List?) ?? const [])
            .cast<Map<String, dynamic>>()
            .map((s) => (entite: s['entite'] as String, id: s['id'] as int))
            .toList(),
      );

      await _ecrireEtat(_cleCurseur, data['curseur'] as String);
      complet = data['complet'] as bool? ?? true;
      passes++;
    }
  }

  Future<void> _rafraichirCompteurs() async {
    final toutes = await _db.select(_db.outboxOperations).get();
    state = state.copie(
      enAttente: toutes.where((o) => o.prochainEssai != null || o.tentatives == 0).length,
      echecs: toutes.where((o) => o.prochainEssai == null && o.tentatives > 0).length,
    );
  }

  Future<String?> _lireEtat(String cle) async {
    final ligne = await (_db.select(_db.syncEtat)..where((e) => e.cle.equals(cle)))
        .getSingleOrNull();
    return ligne?.valeur;
  }

  Future<void> _ecrireEtat(String cle, String valeur) async {
    await _db.into(_db.syncEtat).insertOnConflictUpdate(
          SyncEtatCompanion.insert(cle: cle, valeur: Value(valeur)),
        );
  }
}

final syncServiceProvider = StateNotifierProvider<SyncService, EtatSync>(
  (ref) => SyncService(ref.watch(dbProvider), ref.watch(apiClientProvider)),
);
