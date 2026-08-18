import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../session/session.dart';

/// Adresse de l'API. Surchargée au build pour viser un autre environnement :
/// `flutter run --dart-define=API_URL=http://10.0.2.2:8000/api/v1`
/// (`10.0.2.2` est l'alias de la machine hôte vu depuis l'émulateur Android ;
/// `localhost` y désignerait le téléphone lui-même).
const kApiUrl = String.fromEnvironment(
  'API_URL',
  defaultValue: 'https://elite-g0k9.onrender.com/api/v1',
);

/// L'hébergement met le service en veille après inactivité, et son réveil
/// mesuré prend une quarantaine de secondes — la première requête du matin,
/// typiquement. Le délai de *réception* doit donc largement dépasser ce
/// réveil, sinon cette requête échoue toujours.
const _delaiReception = Duration(seconds: 90);

/// Le délai de *connexion* reste court : l'établissement du lien TCP aboutit
/// vite même quand l'application derrière démarre encore. C'est lui qui
/// distingue un vrai « hors réseau » d'un simple serveur lent, et il ne doit
/// pas faire patienter 90 secondes un téléphone en mode avion.
const _delaiConnexion = Duration(seconds: 15);

/// Erreur d'API déjà traduite : les écrans n'ont jamais à manipuler un
/// `DioException` ni à deviner ce qu'un code HTTP veut dire pour l'utilisateur.
class ErreurApi implements Exception {
  const ErreurApi(this.message, this.statut, {this.erreurs, this.horsLigne = false});

  final String message;
  final int statut;
  final Map<String, dynamic>? erreurs;
  final bool horsLigne;

  /// Une erreur réseau n'est pas un échec : l'opération reste en file
  /// d'attente. L'interface doit le dire ainsi, pas afficher « erreur ».
  bool get estRejeuPossible => horsLigne || statut >= 500;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient(this._ref) {
    dio = Dio(BaseOptions(
      baseUrl: kApiUrl,
      connectTimeout: _delaiConnexion,
      receiveTimeout: _delaiReception,
      headers: {'Accept': 'application/json'},
      // On gère nous-mêmes les codes d'erreur pour les traduire une seule
      // fois ici, plutôt que d'attraper des exceptions dans chaque écran.
      validateStatus: (_) => true,
    ));

    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      final session = _ref.read(sessionProvider);
      if (session != null) {
        options.headers['Authorization'] = 'Bearer ${session.jeton}';
        options.headers['X-School-Id'] = '${session.schoolId}';
      }
      handler.next(options);
    }));
  }

  final Ref _ref;
  late final Dio dio;

  Future<Map<String, dynamic>> get(String chemin, {Map<String, dynamic>? params}) async {
    return _envelopper(() async => _traiter(await dio.get(chemin, queryParameters: params)));
  }

  /// [idempotence] : identifiant d'opération de l'outbox. Sa présence rend le
  /// rejeu inoffensif côté serveur — c'est ce qui autorise le moteur de
  /// synchronisation à retenter sans risque de doublon.
  Future<Map<String, dynamic>> post(
    String chemin,
    Map<String, dynamic> corps, {
    String? idempotence,
  }) async {
    return _envelopper(() async => _traiter(await dio.post(
          chemin,
          data: corps,
          options: idempotence == null
              ? null
              : Options(headers: {'Idempotency-Key': idempotence}),
        )));
  }

  /// Traduit une panne de transport en `ErreurApi(horsLigne: true)`.
  ///
  /// Sans ça, une simple absence de réseau remonterait comme une exception
  /// Dio jusqu'aux écrans, qui l'afficheraient comme une erreur alors que
  /// l'opération est simplement en attente.
  Future<Map<String, dynamic>> _envelopper(
    Future<Map<String, dynamic>> Function() appel,
  ) async {
    try {
      return await appel();
    } on DioException {
      throw const ErreurApi(
        'Pas de connexion — la synchronisation reprendra automatiquement.',
        0,
        horsLigne: true,
      );
    }
  }

  Map<String, dynamic> _traiter(Response reponse) {
    final donnees = reponse.data;
    final statut = reponse.statusCode ?? 0;

    if (statut >= 200 && statut < 300) {
      if (donnees is Map<String, dynamic>) return donnees;
      throw const ErreurApi('Réponse inattendue du serveur.', 0);
    }

    final message = donnees is Map && donnees['message'] is String
        ? donnees['message'] as String
        : _messageParDefaut(statut);

    throw ErreurApi(
      message,
      statut,
      erreurs: donnees is Map && donnees['errors'] is Map
          ? Map<String, dynamic>.from(donnees['errors'] as Map)
          : null,
    );
  }

  String _messageParDefaut(int statut) => switch (statut) {
        401 => 'Session expirée, reconnectez-vous.',
        403 => "Vous n'avez pas la permission d'effectuer cette action.",
        404 => 'Ressource introuvable.',
        422 => 'Les données envoyées ne sont pas valides.',
        >= 500 => 'Le serveur est momentanément indisponible.',
        _ => 'Une erreur est survenue.',
      };
}

final apiClientProvider = Provider(ApiClient.new);
