import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/session/session.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/format.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/theme.dart';
import '../../core/nav/barre_app.dart';
import '../appel/appel_page.dart';

/// Séances locales, filtrées par classe comme sur le portail web.
///
/// La vue d'accueil donne déjà les séances du jour ; cette page sert à la
/// consultation plus large et à l'ouverture directe de l'appel.
class SeancesPage extends ConsumerStatefulWidget {
  const SeancesPage({super.key});

  @override
  ConsumerState<SeancesPage> createState() => _SeancesPageState();
}

class _SeancesPageState extends ConsumerState<SeancesPage> {
  int? _classeId;

  @override
  Widget build(BuildContext context) {
    final db = ref.watch(dbProvider);
    final session = ref.watch(sessionProvider);
    final peutFaireAppel = peutEcrire(context, 'appel.manage');
    final restreintATitulaire =
        session?.estEnseignant == true && (session?.typeEcole ?? 'secondaire') != 'secondaire';
    final titulaireId = session == null ? null : session.utilisateur['id'] as int?;

    final requeteClasses = db.select(db.classes)
      ..orderBy([(c) => OrderingTerm(expression: c.nom)]);
    if (restreintATitulaire && titulaireId != null) {
      requeteClasses.where((c) => c.titulaireId.equals(titulaireId));
    }

    return Scaffold(
      appBar: BarreApp(titre: 'Séances & appel'),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            child: StreamBuilder<List<ClassesData>>(
              stream: requeteClasses.watch(),
              builder: (context, snapshot) {
                final classes = snapshot.data ?? const <ClassesData>[];

                if (restreintATitulaire) {
                  final classe = classes.isEmpty ? null : classes.first;
                  if (classe == null) {
                    return const EtatVide(
                      message: 'Aucune classe confiée.',
                      icone: Icons.meeting_room_outlined,
                    );
                  }

                  return _BandeauClasse(classe: classe);
                }

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
          Expanded(
            child: _ListeSeances(
              classeId: restreintATitulaire ? null : _classeId,
              titulaireId: restreintATitulaire ? titulaireId : null,
              peutFaireAppel: peutFaireAppel,
            ),
          ),
        ],
      ),
    );
  }
}

class _ListeSeances extends ConsumerWidget {
  const _ListeSeances({
    required this.classeId,
    required this.titulaireId,
    required this.peutFaireAppel,
  });

  final int? classeId;
  final int? titulaireId;
  final bool peutFaireAppel;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (classeId == null && titulaireId == null) {
      return const EtatVide(
        message: 'Choisissez une classe pour afficher ses séances.',
        icone: Icons.event_available_outlined,
      );
    }

    final db = ref.watch(dbProvider);

    final requete = db.select(db.seances).join([
      leftOuterJoin(db.classeMatieres, db.classeMatieres.id.equalsExp(db.seances.classeMatiereId)),
      leftOuterJoin(db.matieres, db.matieres.id.equalsExp(db.classeMatieres.matiereId)),
    ])
      ..orderBy([
        OrderingTerm(expression: db.seances.dateSeance, mode: OrderingMode.desc),
        OrderingTerm(expression: db.seances.heureDebut),
      ]);

    if (classeId != null) {
      requete.where(db.seances.classeId.equals(classeId!));
    } else if (titulaireId != null) {
      requete.where(db.seances.classeId.isInQuery(
        db.selectOnly(db.classes)
          ..addColumns([db.classes.id])
          ..where(db.classes.titulaireId.equals(titulaireId!)),
      ));
    }

    return StreamBuilder<List<TypedResult>>(
      stream: requete.watch(),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        final lignes = snapshot.data!;
        if (lignes.isEmpty) {
          return const EtatVide(
            message: 'Aucune séance enregistrée.',
            icone: Icons.event_available_outlined,
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(12, 0, 12, 20),
          itemCount: lignes.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) {
            final seance = lignes[i].readTable(db.seances);
            final matiere = lignes[i].readTableOrNull(db.matieres);

            return CarteListe(
              icone: Icons.checklist_outlined,
              titre: matiere?.nom ?? seance.salle ?? 'Séance',
              sousTitre: [
                formaterDateCourte(seance.dateSeance),
                [
                  seance.heureDebut?.substring(0, 5) ?? '',
                  seance.heureFin?.substring(0, 5) ?? '',
                ].where((e) => e.isNotEmpty).join(' - '),
                seance.salle ?? '',
              ].where((e) => e.isNotEmpty).join(' · '),
              fin: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _libelleStatut(seance.statut),
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                      color: Couleurs.texteSecondaire,
                    ),
                  ),
                  const SizedBox(height: 4),
                  PastilleSync(etat: seance.etatSync),
                ],
              ),
              onTap: peutFaireAppel
                  ? () => Navigator.of(context).push(
                        MaterialPageRoute<void>(builder: (_) => AppelPage(seance: seance)),
                      )
                  : null,
            );
          },
        );
      },
    );
  }

  String _libelleStatut(String? statut) => switch (statut) {
        'faite' => 'Faite',
        'annulee' => 'Annulée',
        _ => 'Prévue',
      };
}

class _BandeauClasse extends StatelessWidget {
  const _BandeauClasse({required this.classe});

  final ClassesData classe;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 10),
      child: Row(
        children: [
          const Icon(Icons.meeting_room_outlined, size: 18, color: Couleurs.texteSecondaire),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              classe.nom,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}
