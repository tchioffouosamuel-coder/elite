import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'db/database.dart';

/// Base locale, partagée par toute l'application. Une seule instance : deux
/// connexions Drift sur le même fichier se marcheraient dessus en écriture.
final dbProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});
