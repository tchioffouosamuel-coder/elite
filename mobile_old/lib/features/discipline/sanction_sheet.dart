import 'package:drift/drift.dart' hide Column;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/db/database.dart';
import '../../core/providers.dart';
import '../../core/sync/sync_service.dart';
import '../../core/ui/theme.dart';

/// Types de sanction acceptés par l'API (`Sanction::TYPES`).
const typesSanction = {
  'avertissement': 'Avertissement',
  'blame': 'Blâme',
  'corvee': 'Corvée',
  'exclusion_temporaire': 'Exclusion temporaire',
  'exclusion_definitive': 'Exclusion définitive',
  'autre': 'Autre',
};

/// Saisie d'une sanction, en feuille.
///
/// Écrite pour le surveillant général en déplacement dans l'établissement,
/// souvent loin d'un bureau et d'un réseau — d'où l'écriture locale immédiate
/// et l'envoi différé.
class SanctionSheet extends ConsumerStatefulWidget {
  const SanctionSheet({super.key, required this.eleve});

  final Eleve eleve;

  static Future<void> ouvrir(BuildContext context, Eleve eleve) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => SanctionSheet(eleve: eleve),
    );
  }

  @override
  ConsumerState<SanctionSheet> createState() => _SanctionSheetState();
}

class _SanctionSheetState extends ConsumerState<SanctionSheet> {
  String _type = 'avertissement';
  final _motif = TextEditingController();
  final _duree = TextEditingController();
  bool _impacteBulletin = false;
  bool _envoi = false;
  String? _erreur;

  /// Seule l'exclusion temporaire se borne dans le temps ; une corvée ou un
  /// blâme n'ont pas de durée (cf. `StoreSanctionRequest`).
  bool get _avecDuree => _type == 'exclusion_temporaire';

  @override
  void dispose() {
    _motif.dispose();
    _duree.dispose();
    super.dispose();
  }

  Future<void> _enregistrer() async {
    final motif = _motif.text.trim();

    // Même règle que l'API : un motif trop court ne permet pas au conseil de
    // discipline de statuer. Vérifié ici pour éviter un aller-retour réseau
    // qui, hors connexion, n'arriverait que bien plus tard.
    if (motif.length < 10) {
      setState(() => _erreur = 'Décrivez le motif en quelques mots (10 caractères minimum).');
      return;
    }
    if (_avecDuree && int.tryParse(_duree.text.trim()) == null) {
      setState(() => _erreur = 'Indiquez la durée en jours.');
      return;
    }

    setState(() {
      _envoi = true;
      _erreur = null;
    });

    final db = ref.read(dbProvider);
    final trimestre = await (db.select(db.trimestres)
          ..where((t) => t.isActive.equals(true))
          ..limit(1))
        .getSingleOrNull();

    if (trimestre == null) {
      setState(() {
        _envoi = false;
        _erreur = 'Aucun trimestre actif synchronisé.';
      });
      return;
    }

    final aujourdhui = DateTime.now().toIso8601String().substring(0, 10);

    final corps = <String, dynamic>{
      'eleve_id': widget.eleve.id,
      'trimestre_id': trimestre.id,
      'type': _type,
      'motif': motif,
      'date_sanction': aujourdhui,
      'impacte_bulletin': _impacteBulletin,
      if (_avecDuree) 'duree_jours': int.parse(_duree.text.trim()),
    };

    // Identifiant local négatif : il ne peut entrer en collision avec ceux du
    // serveur, et la ligne sera remplacée par la version serveur au prochain
    // delta.
    final idLocal = -DateTime.now().millisecondsSinceEpoch.remainder(1000000000);

    await db.into(db.sanctions).insertOnConflictUpdate(
          SanctionsCompanion.insert(
            id: Value(idLocal),
            eleveId: widget.eleve.id,
            classeId: Value(widget.eleve.classeId),
            trimestreId: Value(trimestre.id),
            type: _type,
            motif: Value(motif),
            dateSanction: Value(aujourdhui),
            statut: const Value('en_attente'),
            impacteBulletin: Value(_impacteBulletin),
            dureeJours: Value(_avecDuree ? int.parse(_duree.text.trim()) : null),
            etatSync: const Value('enAttente'),
          ),
        );

    await ref.read(syncServiceProvider.notifier).enfiler(
          methode: 'POST',
          chemin: 'sanctions',
          corps: corps,
          entite: 'sanctions',
          entiteId: idLocal,
        );

    if (!mounted) return;
    Navigator.pop(context);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Sanction enregistrée — envoi dès que le réseau revient.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.75,
      maxChildSize: 0.95,
      builder: (context, controleur) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 10),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Enregistrer une sanction',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  Text(
                    widget.eleve.nomComplet,
                    style: TextStyle(
                      fontSize: 13,
                      color: Theme.of(context).colorScheme.outline,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              controller: controleur,
              padding: const EdgeInsets.all(16),
              children: [
                DropdownButtonFormField<String>(
                  initialValue: _type,
                  decoration: const InputDecoration(labelText: 'Type'),
                  items: [
                    for (final e in typesSanction.entries)
                      DropdownMenuItem(value: e.key, child: Text(e.value)),
                  ],
                  onChanged: (v) => setState(() => _type = v ?? 'avertissement'),
                ),
                if (_avecDuree) ...[
                  const SizedBox(height: 14),
                  TextField(
                    controller: _duree,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Durée (jours)',
                      helperText: "La date de fin en découle automatiquement.",
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                TextField(
                  controller: _motif,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Motif',
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 6),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _impacteBulletin,
                  title: const Text('Impacte le bulletin (note de conduite)'),
                  onChanged: (v) => setState(() => _impacteBulletin = v ?? false),
                ),
                if (_erreur != null)
                  Text(_erreur!, style: const TextStyle(color: Couleurs.echec)),
              ],
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton(
                onPressed: _envoi ? null : _enregistrer,
                child: const Text('Enregistrer'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
