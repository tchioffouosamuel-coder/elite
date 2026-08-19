import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import 'api_client.dart';

/// Téléchargement et ouverture des documents PDF produits par l'API.
///
/// Les documents ne peuvent pas s'ouvrir par un simple lien : l'API exige un
/// jeton dans l'en-tête, qu'un lecteur externe ne transmettrait pas. On les
/// récupère donc authentifiés, on les écrit dans le dossier temporaire, puis
/// on laisse le lecteur du téléphone les afficher — inutile de réimplémenter
/// un rendu que mPDF a déjà mis en page.
class ServiceDocuments {
  const ServiceDocuments(this._api);

  final ApiClient _api;

  /// Récupère le document et l'ouvre. Renvoie un message d'erreur, ou `null`
  /// si tout s'est bien passé.
  Future<String?> ouvrir(
    String chemin, {
    required String nomFichier,
    Map<String, dynamic>? params,
  }) async {
    try {
      final reponse = await _api.dio.get<List<int>>(
        chemin.startsWith('/') ? chemin : '/$chemin',
        queryParameters: params,
        options: Options(
          responseType: ResponseType.bytes,
          // Le client global avale les codes d'erreur pour les traduire ;
          // ici on veut distinguer un PDF d'un message d'erreur JSON.
          validateStatus: (_) => true,
        ),
      );

      final statut = reponse.statusCode ?? 0;
      if (statut < 200 || statut >= 300) {
        return _messageErreur(statut, reponse.data);
      }

      final octets = reponse.data;
      if (octets == null || octets.isEmpty) {
        return 'Le document reçu est vide.';
      }

      final dossier = await getTemporaryDirectory();
      final fichier = File('${dossier.path}/$nomFichier');
      await fichier.writeAsBytes(octets, flush: true);

      final resultat = await OpenFilex.open(fichier.path, type: 'application/pdf');
      if (resultat.type != ResultType.done) {
        // Le cas le plus fréquent sur un téléphone d'entrée de gamme : aucun
        // lecteur PDF installé. Le dire clairement vaut mieux qu'un échec muet.
        return "Impossible d'ouvrir le document — installez un lecteur PDF.";
      }

      return null;
    } on DioException {
      return 'Pas de connexion — le document se télécharge depuis le serveur.';
    } catch (e) {
      return "Le document n'a pas pu être ouvert.";
    }
  }

  /// Une erreur arrive en octets puisqu'on a demandé des octets : il faut la
  /// retraduire pour ne pas afficher un tableau de nombres à l'utilisateur.
  String _messageErreur(int statut, List<int>? corps) {
    if (corps != null && corps.isNotEmpty) {
      try {
        final texte = String.fromCharCodes(corps);
        final debut = texte.indexOf('"message":"');
        if (debut != -1) {
          final reste = texte.substring(debut + 11);
          final fin = reste.indexOf('"');
          if (fin > 0) return reste.substring(0, fin);
        }
      } catch (_) {
        // Corps illisible : on retombe sur le message générique ci-dessous.
      }
    }

    return switch (statut) {
      403 => "Vous n'avez pas la permission d'ouvrir ce document.",
      404 => 'Document introuvable.',
      422 => 'Ce document ne peut pas être produit pour cette sélection.',
      _ => 'Le serveur n\'a pas pu produire le document.',
    };
  }
}

final documentsProvider = Provider(
  (ref) => ServiceDocuments(ref.watch(apiClientProvider)),
);
