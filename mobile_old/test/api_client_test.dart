import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:elites_mobile/core/network/api_client.dart';

/// Intercepteur qui court-circuite le réseau et retient l'URL réellement
/// appelée : c'est elle qu'on veut vérifier, pas la réponse.
class _EspionUrl extends Interceptor {
  String? urlAppelee;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    urlAppelee = options.uri.toString();
    handler.resolve(Response(
      requestOptions: options,
      statusCode: 200,
      data: const <String, dynamic>{'data': []},
    ));
  }
}

void main() {
  late ProviderContainer conteneur;
  late ApiClient api;
  late _EspionUrl espion;

  setUp(() {
    conteneur = ProviderContainer();
    api = conteneur.read(apiClientProvider);
    espion = _EspionUrl();
    api.dio.interceptors.add(espion);
  });

  tearDown(() => conteneur.dispose());

  /*
   * Régression : la base se termine par `/api/v1` sans barre oblique, et Dio
   * concatène tel quel un chemin qui n'en commence pas par une. `ecole`
   * donnait `…/api/v1ecole`, et le serveur répondait « route introuvable » —
   * sur la plupart des écrans, puisque presque tous les appels s'écrivent
   * sans barre initiale.
   */
  test('un chemin sans barre initiale reste séparé de la base', () async {
    await api.get('ecole');

    expect(espion.urlAppelee, contains('/api/v1/ecole'));
    expect(espion.urlAppelee, isNot(contains('v1ecole')));
  });

  test('un chemin avec barre initiale ne la double pas', () async {
    await api.get('/sync');

    expect(espion.urlAppelee, contains('/api/v1/sync'));
    expect(espion.urlAppelee, isNot(contains('v1//sync')));
  });

  test('les chemins à plusieurs segments sont préservés', () async {
    await api.get('bus/vehicules');

    expect(espion.urlAppelee, contains('/api/v1/bus/vehicules'));
  });

  test('une écriture passe elle aussi par la normalisation', () async {
    await api.post('inventaire', const {'nom': 'Test'});

    expect(espion.urlAppelee, contains('/api/v1/inventaire'));
    expect(espion.urlAppelee, isNot(contains('v1inventaire')));
  });
}
