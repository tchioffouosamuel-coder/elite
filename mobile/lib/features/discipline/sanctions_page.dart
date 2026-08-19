import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/format.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/theme.dart';
import '../../core/nav/barre_app.dart';
import 'sanction_sheet.dart';

/// Liste locale des sanctions, avec filtre de classe.
///
/// La feuille de sanction existe déjà pour la saisie ponctuelle depuis la
/// fiche élève ; cette page reprend la vue d'ensemble qui manquait au tiroir.
class SanctionsPage extends ConsumerStatefulWidget {
  const SanctionsPage({super.key});

  @override
  ConsumerState<SanctionsPage> createState() => _SanctionsPageState();
}

class _SanctionsPageState extends ConsumerState<SanctionsPage> {
  int? _classeId;
  String _recherche = '';

  @override
  Widget build(BuildContext context) {
    final db = ref.watch(dbProvider);
    final peutEcrireSanctions = peutEcrire(context, 'discipline.manage');

    final requeteClasses = db.select(db.classes)
      ..orderBy([(c) => OrderingTerm(expression: c.nom)]);

    final requeteSanctions = db.select(db.sanctions).join([
      innerJoin(db.eleves, db.eleves.id.equalsExp(db.sanctions.eleveId)),
      leftOuterJoin(db.classes, db.classes.id.equalsExp(db.sanctions.classeId)),
    ])
      ..orderBy([
        OrderingTerm(expression: db.sanctions.dateSanction, mode: OrderingMode.desc),
        OrderingTerm(expression: db.sanctions.id, mode: OrderingMode.desc),
      ]);

    if (_classeId != null) {
      requeteSanctions.where(db.sanctions.classeId.equals(_classeId!));
    }

    return Scaffold(
      appBar: BarreApp(titre: 'Sanctions'),
      floatingActionButton: peutEcrireSanctions
          ? FloatingActionButton.extended(
              onPressed: () => _choisirEleve(context),
              icon: const Icon(Icons.add),
              label: const Text('Sanction'),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            child: StreamBuilder<List<ClassesData>>(
              stream: requeteClasses.watch(),
              builder: (context, snapshot) {
                final classes = snapshot.data ?? const <ClassesData>[];

                return DropdownButtonFormField<int?>(
                  initialValue: _classeId,
                  decoration: const InputDecoration(
                    labelText: 'Classe',
                    isDense: true,
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Toutes les classes'),
                    ),
                    for (final classe in classes)
                      DropdownMenuItem<int?>(
                        value: classe.id,
                        child: Text(classe.nom),
                      ),
                  ],
                  onChanged: (v) => setState(() => _classeId = v),
                );
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: TextField(
              onChanged: (v) => setState(() => _recherche = v),
              decoration: const InputDecoration(
                hintText: 'Rechercher un élève ou un motif…',
                prefixIcon: Icon(Icons.search, size: 20),
                isDense: true,
              ),
            ),
          ),
          Expanded(
            child: StreamBuilder<List<TypedResult>>(
              stream: requeteSanctions.watch(),
              builder: (context, snapshot) {
                if (!snapshot.hasData) {
                  return const Center(child: CircularProgressIndicator());
                }

                final lignes = snapshot.data!.where((ligne) {
                  if (_recherche.trim().isEmpty) return true;
                  final sanction = ligne.readTable(db.sanctions);
                  final eleve = ligne.readTable(db.eleves);
                  final classe = ligne.readTableOrNull(db.classes);
                  final texte = [
                    eleve.nomComplet,
                    eleve.matricule ?? '',
                    sanction.motif ?? '',
                    classe?.nom ?? '',
                  ].join(' ').toLowerCase();
                  return texte.contains(_recherche.toLowerCase());
                }).toList();

                if (lignes.isEmpty) {
                  return const EtatVide(
                    message: 'Aucune sanction enregistrée.',
                    icone: Icons.gavel_outlined,
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 20),
                  itemCount: lignes.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (_, i) {
                    final sanction = lignes[i].readTable(db.sanctions);
                    final eleve = lignes[i].readTable(db.eleves);
                    final classe = lignes[i].readTableOrNull(db.classes);

                    return CarteListe(
                      icone: Icons.gavel_outlined,
                      titre: eleve.nomComplet,
                      sousTitre: [
                        classe?.nom ?? 'Sans classe',
                        formaterDateCourte(sanction.dateSanction ?? sanction.dateDebut),
                        _libelleType(sanction.type),
                      ].where((e) => e.isNotEmpty).join(' · '),
                      fin: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            _libelleStatut(sanction.statut),
                            style: const TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700,
                              color: Couleurs.texteSecondaire,
                            ),
                          ),
                          const SizedBox(height: 4),
                          PastilleSync(etat: sanction.etatSync),
                        ],
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _choisirEleve(BuildContext context) async {
    final eleve = await showModalBottomSheet<Eleve>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _SelecteurEleve(classeId: _classeId),
    );

    if (eleve == null || !context.mounted) return;

    await SanctionSheet.ouvrir(context, eleve);
  }

  String _libelleType(String type) => switch (type) {
        'avertissement' => 'Avertissement',
        'blame' => 'Blâme',
        'corvee' => 'Corvée',
        'exclusion_temporaire' => 'Exclusion temporaire',
        'exclusion_definitive' => 'Exclusion définitive',
        'autre' => 'Autre',
        _ => type,
      };

  String _libelleStatut(String? statut) => switch (statut) {
        'confirmee' => 'Confirmée',
        'annulee' => 'Annulée',
        _ => 'En attente',
      };
}

class _SelecteurEleve extends ConsumerStatefulWidget {
  const _SelecteurEleve({required this.classeId});

  final int? classeId;

  @override
  ConsumerState<_SelecteurEleve> createState() => _SelecteurEleveState();
}

class _SelecteurEleveState extends ConsumerState<_SelecteurEleve> {
  String _recherche = '';

  @override
  Widget build(BuildContext context) {
    final db = ref.watch(dbProvider);

    final requete = db.select(db.eleves).join([
      leftOuterJoin(db.classes, db.classes.id.equalsExp(db.eleves.classeId)),
    ])
      ..where(db.eleves.statut.equals('actif'))
      ..orderBy([OrderingTerm(expression: db.eleves.nomComplet)]);

    if (widget.classeId != null) {
      requete.where(db.eleves.classeId.equals(widget.classeId!));
    }

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      builder: (context, controleur) => Column(
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 4, 20, 10),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Quel élève ?',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              autofocus: true,
              decoration: const InputDecoration(
                hintText: 'Rechercher un élève…',
                prefixIcon: Icon(Icons.search, size: 20),
                isDense: true,
              ),
              onChanged: (v) => setState(() => _recherche = v),
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: StreamBuilder<List<TypedResult>>(
              stream: requete.watch(),
              builder: (context, snapshot) {
                final lignes = snapshot.data ?? const <TypedResult>[];
                final filtres = lignes.where((ligne) {
                  if (_recherche.trim().isEmpty) return true;
                  final eleve = ligne.readTable(db.eleves);
                  final classe = ligne.readTableOrNull(db.classes);
                  return [
                    eleve.nomComplet,
                    eleve.matricule ?? '',
                    classe?.nom ?? '',
                  ].join(' ').toLowerCase().contains(_recherche.toLowerCase());
                }).toList();

                if (filtres.isEmpty) {
                  return const EtatVide(
                    message: 'Aucun élève correspondant.',
                    icone: Icons.person_outline,
                  );
                }

                return ListView.separated(
                  controller: controleur,
                  itemCount: filtres.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    final eleve = filtres[i].readTable(db.eleves);
                    final classe = filtres[i].readTableOrNull(db.classes);

                    return ListTile(
                      leading: CircleAvatar(child: Text(_initiales(eleve.nomComplet))),
                      title: Text(eleve.nomComplet),
                      subtitle: Text(
                        [
                          classe?.nom ?? 'Sans classe',
                          if ((eleve.matricule ?? '').isNotEmpty) eleve.matricule!,
                        ].join(' · '),
                      ),
                      onTap: () => Navigator.pop(context, eleve),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  String _initiales(String texte) {
    final mots = texte.trim().split(RegExp(r'\s+'));
    return mots.take(2).map((m) => m.isEmpty ? '' : m[0]).join().toUpperCase();
  }
}
