import 'package:elites_mobile/features/qr/jeton_qr.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  /*
   * Régression : le code affiché au mur est produit par le portail web sous
   * la forme `{origine}/qr/{jeton}`. Le scanner cherchait la valeur brute
   * dans la table `classes` — un code réellement imprimé n'aurait donc jamais
   * été reconnu, et le geste censé ouvrir l'appel aurait toujours échoué.
   */
  test('une URL du portail livre son jeton', () {
    expect(
      extraireJetonQr('https://elite-khaki-nine.vercel.app/qr/e2564383-a17b-4c9d'),
      'e2564383-a17b-4c9d',
    );
  });

  test('un jeton scanné seul reste inchangé', () {
    expect(extraireJetonQr('e2564383-a17b-4c9d'), 'e2564383-a17b-4c9d');
  });

  test('les espaces autour sont ignorés', () {
    expect(extraireJetonQr('  abc-123  '), 'abc-123');
  });

  test('une valeur vide ou absente ne donne rien', () {
    expect(extraireJetonQr(null), isNull);
    expect(extraireJetonQr('   '), isNull);
  });

  test('une URL sans segment /qr/ est prise telle quelle', () {
    // Un QR étranger à l'application : on ne prétend pas y trouver un jeton,
    // la recherche en base échouera et l'utilisateur sera prévenu.
    const autre = 'https://exemple.org/autre/chose';
    expect(extraireJetonQr(autre), autre);
  });
}
