import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/etats.dart';

/// Saisie des notes d'une affectation classe↔matière, séquence par séquence.
///
/// Chaque note est écrite en local dès la frappe : un enseignant qui ferme
/// l'app au milieu d'une classe de 40 ne doit rien reperdre. L'envoi au
/// serveur est un geste distinct, explicite.
class SaisieNotesPage extends ConsumerStatefulWidget {
  const SaisieNotesPage({
    super.key,
    required this.classeMatiereId,
    required this.classeId,
    required this.titre,
  });

  final int classeMatiereId;
  final int classeId;
  final String titre;

  @override
  ConsumerState<SaisieNotesPage> createState() => _SaisieNotesPageState();
}

class _SaisieNotesPageState extends ConsumerState<SaisieNotesPage> {
  int? _sequenceId;

  @override
  Widget build(BuildContext context) {
    final db = ref.watch(dbProvider);

    return Scaffold(
      appBar: AppBar(title: Text(widget.titre)),
      body: StreamBuilder<List<Sequence>>(
        stream: (db.select(db.sequences)
              ..orderBy([(s) => OrderingTerm(expression: s.ordre)]))
            .watch(),
        builder: (context, snapshotSequences) {
          final sequences = snapshotSequences.data ?? const <Sequence>[];
          if (sequences.isEmpty) {
            return const EtatVide(message: 'Aucune séquence synchronisée.');
          }

          final sequenceId = _sequenceId ?? sequences.first.id;

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: DropdownButtonFormField<int>(
                  initialValue: sequenceId,
                  decoration: const InputDecoration(labelText: 'Séquence', isDense: true),
                  items: [
                    for (final s in sequences)
                      DropdownMenuItem(value: s.id, child: Text(s.libelle)),
                  ],
                  onChanged: (v) => setState(() => _sequenceId = v),
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: _Grille(
                  classeMatiereId: widget.classeMatiereId,
                  classeId: widget.classeId,
                  sequenceId: sequenceId,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Grille extends ConsumerWidget {
  const _Grille({
    required this.classeMatiereId,
    required this.classeId,
    required this.sequenceId,
  });

  final int classeMatiereId;
  final int classeId;
  final int sequenceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final db = ref.watch(dbProvider);

    return StreamBuilder<List<Eleve>>(
      stream: (db.select(db.eleves)
            ..where((e) => e.classeId.equals(classeId) & e.statut.equals('actif'))
            ..orderBy([(e) => OrderingTerm(expression: e.nomComplet)]))
          .watch(),
      builder: (context, snapshotEleves) {
        final eleves = snapshotEleves.data ?? const <Eleve>[];
        if (eleves.isEmpty) {
          return const EtatVide(message: 'Aucun élève actif dans cette classe.');
        }

        return StreamBuilder<List<Note>>(
          stream: (db.select(db.notes)
                ..where((n) =>
                    n.classeMatiereId.equals(classeMatiereId) & n.sequenceId.equals(sequenceId)))
              .watch(),
          builder: (context, snapshotNotes) {
            final notes = {
              for (final n in snapshotNotes.data ?? const <Note>[]) n.eleveId: n,
            };

            final saisies = notes.values.where((n) => n.valeur != null).length;

            return Column(
              children: [
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  child: Text('$saisies / ${eleves.length} notes saisies'),
                ),
                Expanded(
                  child: ListView.separated(
                    itemCount: eleves.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (_, i) => _LigneNote(
                      eleve: eleves[i],
                      note: notes[eleves[i].id],
                      classeMatiereId: classeMatiereId,
                      sequenceId: sequenceId,
                    ),
                  ),
                ),
                _BarreEnvoi(
                  classeMatiereId: classeMatiereId,
                  sequenceId: sequenceId,
                  eleves: eleves,
                  notes: notes,
                ),
              ],
            );
          },
        );
      },
    );
  }
}

class _LigneNote extends ConsumerStatefulWidget {
  const _LigneNote({
    required this.eleve,
    required this.note,
    required this.classeMatiereId,
    required this.sequenceId,
  });

  final Eleve eleve;
  final Note? note;
  final int classeMatiereId;
  final int sequenceId;

  @override
  ConsumerState<_LigneNote> createState() => _LigneNoteState();
}

class _LigneNoteState extends ConsumerState<_LigneNote> {
  late final TextEditingController _champ;
  String? _erreur;

  @override
  void initState() {
    super.initState();
    _champ = TextEditingController(text: widget.note?.valeur?.toString() ?? '');
  }

  @override
  void dispose() {
    _champ.dispose();
    super.dispose();
  }

  /// Écrit à chaque frappe valide. Pas de bouton « enregistrer » par ligne :
  /// sur 40 élèves, ce serait 40 gestes de plus sans rien garantir de mieux.
  Future<void> _enregistrer(String saisie) async {
    final texte = saisie.trim().replaceAll(',', '.');
    final valeur = texte.isEmpty ? null : double.tryParse(texte);

    if (texte.isNotEmpty && (valeur == null || valeur < 0 || valeur > 20)) {
      setState(() => _erreur = 'Entre 0 et 20');
      return;
    }
    if (_erreur != null) setState(() => _erreur = null);

    final db = ref.read(dbProvider);
    await db.into(db.notes).insertOnConflictUpdate(
          NotesCompanion.insert(
            id: Value(widget.note?.id ?? _idLocalNote(widget.classeMatiereId, widget.sequenceId, widget.eleve.id)),
            eleveId: widget.eleve.id,
            classeMatiereId: widget.classeMatiereId,
            sequenceId: Value(widget.sequenceId),
            valeur: Value(valeur),
            etatSync: const Value('enAttente'),
          ),
        );
  }

  @override
  Widget build(BuildContext context) {
    return ListTile(
      title: Text(widget.eleve.nomComplet),
      subtitle: Text(widget.eleve.matricule ?? '—', style: const TextStyle(fontSize: 12.5)),
      trailing: SizedBox(
        width: 96,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (widget.note != null) PastilleSync(etat: widget.note!.etatSync),
            const SizedBox(width: 6),
            Expanded(
              child: TextField(
                controller: _champ,
                textAlign: TextAlign.center,
                // Clavier décimal : la virgule est acceptée puis normalisée,
                // c'est ce que tape naturellement un enseignant francophone.
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  isDense: true,
                  hintText: '—',
                  errorText: _erreur,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                ),
                onChanged: _enregistrer,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Identifiant local déterministe : resaisir la même note écrase la même
/// ligne au lieu d'en empiler une nouvelle.
int _idLocalNote(int classeMatiereId, int sequenceId, int eleveId) =>
    -((classeMatiereId * 1000000) + (sequenceId * 10000) + eleveId);

class _BarreEnvoi extends ConsumerStatefulWidget {
  const _BarreEnvoi({
    required this.classeMatiereId,
    required this.sequenceId,
    required this.eleves,
    required this.notes,
  });

  final int classeMatiereId;
  final int sequenceId;
  final List<Eleve> eleves;
  final Map<int, Note> notes;

  @override
  ConsumerState<_BarreEnvoi> createState() => _BarreEnvoiState();
}

class _BarreEnvoiState extends ConsumerState<_BarreEnvoi> {
  bool _envoi = false;

  Future<void> _envoyer() async {
    final aEnvoyer = widget.notes.values.where((n) => n.valeur != null).toList();

    if (aEnvoyer.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Aucune note saisie.')),
      );
      return;
    }

    setState(() => _envoi = true);

    await ref.read(syncServiceProvider.notifier).enfiler(
          methode: 'POST',
          chemin: 'classe-matieres/${widget.classeMatiereId}/notes',
          corps: {
            'sequence_id': widget.sequenceId,
            'notes': [
              for (final n in aEnvoyer) {'eleve_id': n.eleveId, 'valeur': n.valeur},
            ],
          },
          entite: 'notes',
          entiteId: widget.classeMatiereId,
        );

    if (!mounted) return;
    setState(() => _envoi = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('${aEnvoyer.length} note(s) en file — envoi dès que le réseau revient.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: FilledButton.icon(
          onPressed: _envoi ? null : _envoyer,
          icon: const Icon(Icons.cloud_upload_outlined),
          label: const Text('Envoyer les notes'),
        ),
      ),
    );
  }
}
