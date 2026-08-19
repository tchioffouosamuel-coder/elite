import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/nav/barre_app.dart';
import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/theme.dart';

/// Jours de la semaine, indexés comme `Carbon::dayOfWeekIso` (1 = lundi),
/// puisque c'est ce que stocke et attend l'API.
const _jours = {
  1: 'Lundi',
  2: 'Mardi',
  3: 'Mercredi',
  4: 'Jeudi',
  5: 'Vendredi',
  6: 'Samedi',
  7: 'Dimanche',
};

/// Emploi du temps d'une classe.
///
/// Choisir la classe d'abord : contrairement au web, où la barre latérale
/// laisse de la place pour une grille hebdomadaire complète, un téléphone ne
/// peut afficher lisiblement que le programme d'une classe à la fois.
class EmploiDuTempsPage extends ConsumerStatefulWidget {
  const EmploiDuTempsPage({super.key});

  @override
  ConsumerState<EmploiDuTempsPage> createState() => _EmploiDuTempsPageState();
}

class _EmploiDuTempsPageState extends ConsumerState<EmploiDuTempsPage> {
  Map<String, dynamic>? _classe;

  @override
  Widget build(BuildContext context) {
    final classes = ref.watch(listeApiProvider(const RequeteListe('classes')));

    return Scaffold(
      appBar: BarreApp(titre: 'Emploi du temps'),
      body: classes.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(listeApiProvider(const RequeteListe('classes'))),
        ),
        data: (liste) {
          if (liste.isEmpty) {
            return const EtatVide(message: 'Aucune classe.');
          }

          final classe = _classe ?? liste.first;

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: DropdownButtonFormField<int>(
                  initialValue: classe['id'] as int?,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Classe', isDense: true),
                  items: [
                    for (final c in liste)
                      DropdownMenuItem(value: c['id'] as int?, child: Text('${c['nom']}')),
                  ],
                  onChanged: (id) => setState(
                    () => _classe = liste.firstWhere((c) => c['id'] == id),
                  ),
                ),
              ),
              const Divider(height: 1),
              Expanded(child: _Grille(classe: classe)),
            ],
          );
        },
      ),
    );
  }
}

class _Grille extends ConsumerWidget {
  const _Grille({required this.classe});

  final Map<String, dynamic> classe;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final classeId = classe['id'];
    final requete = RequeteListe('classes/$classeId/emploi-du-temps');
    final async = ref.watch(listeApiProvider(requete));
    final peutGerer = peutEcrire(context, 'emploi_du_temps.manage');

    return Scaffold(
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => EtatErreur(
          message: e is ErreurApi ? e.message : '$e',
          onReessayer: () => ref.invalidate(listeApiProvider(requete)),
        ),
        data: (creneaux) {
          if (creneaux.isEmpty) {
            return EtatVide(
              message: 'Aucun créneau pour ${classe['nom']}.',
              icone: Icons.calendar_month_outlined,
              indication: peutGerer
                  ? "Ajoutez les créneaux, puis générez les séances de la période."
                  : null,
            );
          }

          // Regroupé par jour : c'est ainsi qu'on lit un emploi du temps, et
          // ça évite de faire défiler une liste plate de trente lignes.
          final parJour = <int, List<Map<String, dynamic>>>{};
          for (final c in creneaux) {
            parJour.putIfAbsent(c['jour'] as int? ?? 0, () => []).add(c);
          }
          for (final liste in parJour.values) {
            liste.sort((a, b) => '${a['heure_debut']}'.compareTo('${b['heure_debut']}'));
          }

          final jours = parJour.keys.toList()..sort();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(listeApiProvider(requete)),
            child: ListView(
              padding: const EdgeInsets.only(bottom: 96),
              children: [
                for (final jour in jours) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
                    child: Text(
                      (_jours[jour] ?? 'Jour $jour').toUpperCase(),
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.8,
                        color: Couleurs.texteSecondaire,
                      ),
                    ),
                  ),
                  for (final c in parJour[jour]!)
                    _LigneCreneau(
                      creneau: c,
                      classeId: classeId,
                      requete: requete,
                      peutGerer: peutGerer,
                    ),
                ],
              ],
            ),
          );
        },
      ),
      floatingActionButton: !peutGerer
          ? null
          : _BoutonsGrille(classe: classe, requete: requete),
    );
  }
}

class _LigneCreneau extends ConsumerWidget {
  const _LigneCreneau({
    required this.creneau,
    required this.classeId,
    required this.requete,
    required this.peutGerer,
  });

  final Map<String, dynamic> creneau;
  final dynamic classeId;
  final RequeteListe requete;
  final bool peutGerer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ListTile(
      leading: Container(
        width: 58,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: Couleurs.gold100,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          children: [
            Text('${creneau['heure_debut']}',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
            Text('${creneau['heure_fin']}',
                style: const TextStyle(fontSize: 11, color: Couleurs.texteSecondaire)),
          ],
        ),
      ),
      title: Text('${creneau['matiere'] ?? '—'}'),
      subtitle: Text(
        [creneau['enseignant'], creneau['salle']].where((e) => e != null).join(' · '),
        style: const TextStyle(fontSize: 12.5),
      ),
      trailing: !peutGerer
          ? null
          : IconButton(
              icon: const Icon(Icons.delete_outline, size: 20, color: Couleurs.echec),
              tooltip: 'Supprimer ce créneau',
              onPressed: () => _supprimer(context, ref),
            ),
    );
  }

  Future<void> _supprimer(BuildContext context, WidgetRef ref) async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Supprimer ce créneau ?'),
        content: Text('${creneau['matiere']} — ${creneau['heure_debut']} à ${creneau['heure_fin']}'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Supprimer')),
        ],
      ),
    );

    if (confirme != true || !context.mounted) return;

    try {
      await ref.read(apiClientProvider).post(
        'classes/$classeId/emploi-du-temps/${creneau['id']}',
        const {'_method': 'DELETE'},
      );
      ref.invalidate(listeApiProvider(requete));
    } on ErreurApi catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Couleurs.echec),
      );
    }
  }
}

class _BoutonsGrille extends ConsumerWidget {
  const _BoutonsGrille({required this.classe, required this.requete});

  final Map<String, dynamic> classe;
  final RequeteListe requete;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        // La génération vient après la saisie des créneaux : bouton secondaire,
        // pour ne pas la confondre avec l'ajout d'un créneau.
        FloatingActionButton.small(
          heroTag: 'generer',
          tooltip: 'Générer les séances',
          onPressed: () => _genererSeances(context, ref),
          backgroundColor: Couleurs.navy800,
          child: const Icon(Icons.event_repeat),
        ),
        const SizedBox(height: 10),
        FloatingActionButton.extended(
          heroTag: 'creneau',
          onPressed: () => _ajouterCreneau(context, ref),
          icon: const Icon(Icons.add),
          label: const Text('Créneau'),
        ),
      ],
    );
  }

  Future<void> _ajouterCreneau(BuildContext context, WidgetRef ref) async {
    final ajoute = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _FormulaireCreneau(classe: classe),
    );

    if (ajoute == true) ref.invalidate(listeApiProvider(requete));
  }

  /// Matérialise les séances de la période à partir de la grille.
  ///
  /// C'est l'étape qui remplit « Ma journée » et rend le scan QR utile : sans
  /// elle, la grille reste théorique et l'enseignant n'a aucune séance à
  /// pointer.
  Future<void> _genererSeances(BuildContext context, WidgetRef ref) async {
    var debut = DateTime.now();
    var fin = DateTime.now().add(const Duration(days: 30));

    final periode = await showDialog<(DateTime, DateTime)>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, majEtat) => AlertDialog(
          title: const Text('Générer les séances'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Les séances de la période seront créées à partir des créneaux '
                'de cette classe. Ce sont elles que les enseignants pointent.',
                style: TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 16),
              _ChampDate(
                libelle: 'Du',
                date: debut,
                onChange: (d) => majEtat(() => debut = d),
              ),
              const SizedBox(height: 12),
              _ChampDate(
                libelle: 'Au',
                date: fin,
                onChange: (d) => majEtat(() => fin = d),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c), child: const Text('Annuler')),
            FilledButton(
              onPressed: () => Navigator.pop(c, (debut, fin)),
              child: const Text('Générer'),
            ),
          ],
        ),
      ),
    );

    if (periode == null || !context.mounted) return;

    try {
      final reponse = await ref.read(apiClientProvider).post(
        'classes/${classe['id']}/emploi-du-temps/generer-seances',
        {
          'date_debut': periode.$1.toIso8601String().substring(0, 10),
          'date_fin': periode.$2.toIso8601String().substring(0, 10),
        },
      );

      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${reponse['message'] ?? 'Séances générées.'}'),
          backgroundColor: Couleurs.synchro,
        ),
      );
    } on ErreurApi catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Couleurs.echec),
      );
    }
  }
}

class _ChampDate extends StatelessWidget {
  const _ChampDate({required this.libelle, required this.date, required this.onChange});

  final String libelle;
  final DateTime date;
  final ValueChanged<DateTime> onChange;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () async {
        final choix = await showDatePicker(
          context: context,
          initialDate: date,
          firstDate: DateTime(2000),
          lastDate: DateTime(2100),
        );
        if (choix != null) onChange(choix);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: libelle,
          suffixIcon: const Icon(Icons.calendar_today, size: 18),
          isDense: true,
        ),
        child: Text(date.toIso8601String().substring(0, 10)),
      ),
    );
  }
}

class _FormulaireCreneau extends ConsumerStatefulWidget {
  const _FormulaireCreneau({required this.classe});

  final Map<String, dynamic> classe;

  @override
  ConsumerState<_FormulaireCreneau> createState() => _FormulaireCreneauState();
}

class _FormulaireCreneauState extends ConsumerState<_FormulaireCreneau> {
  int _jour = 1;
  int? _classeMatiereId;
  TimeOfDay _debut = const TimeOfDay(hour: 8, minute: 0);
  TimeOfDay _fin = const TimeOfDay(hour: 9, minute: 0);
  String? _salle;
  bool _envoi = false;
  String? _erreur;

  static String _hhmm(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _enregistrer() async {
    if (_classeMatiereId == null) {
      setState(() => _erreur = 'Choisissez une matière.');
      return;
    }

    setState(() {
      _envoi = true;
      _erreur = null;
    });

    try {
      await ref.read(apiClientProvider).post(
        'classes/${widget.classe['id']}/emploi-du-temps',
        {
          'classe_matiere_id': _classeMatiereId,
          'jour': _jour,
          'heure_debut': _hhmm(_debut),
          'heure_fin': _hhmm(_fin),
          if (_salle != null) 'salle': _salle,
        },
      );

      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ErreurApi catch (e) {
      setState(() {
        _erreur = e.message;
        _envoi = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    // Seules les matières affectées à cette classe : l'API refuse les autres.
    final affectations = ref.watch(
      listeApiProvider(RequeteListe('classes/${widget.classe['id']}/matieres')),
    );

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.8,
      maxChildSize: 0.95,
      builder: (context, controleur) => ListView(
        controller: controleur,
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
        children: [
          Text('Nouveau créneau — ${widget.classe['nom']}',
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          const SizedBox(height: 18),

          affectations.when(
            loading: () => const LinearProgressIndicator(),
            error: (e, _) => Text(
              e is ErreurApi ? e.message : 'Matières indisponibles',
              style: const TextStyle(color: Couleurs.echec),
            ),
            data: (liste) => DropdownButtonFormField<int>(
              initialValue: _classeMatiereId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Matière *'),
              items: [
                for (final a in liste)
                  DropdownMenuItem(
                    value: a['id'] as int?,
                    child: Text(
                      a['matiere'] is Map ? '${a['matiere']['nom']}' : '${a['matiere'] ?? a['id']}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (v) => setState(() => _classeMatiereId = v),
            ),
          ),
          const SizedBox(height: 14),

          DropdownButtonFormField<int>(
            initialValue: _jour,
            decoration: const InputDecoration(labelText: 'Jour *'),
            items: [
              for (final e in _jours.entries)
                DropdownMenuItem(value: e.key, child: Text(e.value)),
            ],
            onChanged: (v) => setState(() => _jour = v ?? 1),
          ),
          const SizedBox(height: 14),

          Row(
            children: [
              Expanded(
                child: _ChampHeure(
                  libelle: 'Début *',
                  heure: _debut,
                  onChange: (h) => setState(() => _debut = h),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _ChampHeure(
                  libelle: 'Fin *',
                  heure: _fin,
                  onChange: (h) => setState(() => _fin = h),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          TextFormField(
            decoration: const InputDecoration(labelText: 'Salle'),
            onChanged: (v) => _salle = v.trim().isEmpty ? null : v.trim(),
          ),
          const SizedBox(height: 18),

          if (_erreur != null) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Couleurs.echec.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(_erreur!, style: const TextStyle(color: Couleurs.echec)),
            ),
            const SizedBox(height: 14),
          ],

          FilledButton(
            onPressed: _envoi ? null : _enregistrer,
            child: _envoi
                ? const SizedBox(
                    height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Ajouter le créneau'),
          ),
        ],
      ),
    );
  }
}

class _ChampHeure extends StatelessWidget {
  const _ChampHeure({required this.libelle, required this.heure, required this.onChange});

  final String libelle;
  final TimeOfDay heure;
  final ValueChanged<TimeOfDay> onChange;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () async {
        final choix = await showTimePicker(context: context, initialTime: heure);
        if (choix != null) onChange(choix);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: libelle,
          suffixIcon: const Icon(Icons.schedule, size: 18),
        ),
        child: Text(
          '${heure.hour.toString().padLeft(2, '0')}:${heure.minute.toString().padLeft(2, '0')}',
        ),
      ),
    );
  }
}
