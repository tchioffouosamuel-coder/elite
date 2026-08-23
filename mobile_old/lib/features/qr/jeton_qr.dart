/// Extrait le jeton de salle d'un code QR scanné.
///
/// Le code affiché au mur est produit par le portail web sous la forme
/// `{origine}/qr/{jeton}` — chercher la valeur scannée telle quelle dans la
/// table `classes` ne trouvait donc jamais rien. On accepte les deux formes
/// plutôt que de dépendre de celle qu'un imprimeur a bien voulu produire :
/// un code déjà affiché dans une salle doit rester valide.
String? extraireJetonQr(String? brut) {
  final valeur = brut?.trim();
  if (valeur == null || valeur.isEmpty) return null;

  final segments = Uri.tryParse(valeur)?.pathSegments;
  if (segments != null && segments.length >= 2 && segments[segments.length - 2] == 'qr') {
    return segments.last;
  }

  return valeur;
}
