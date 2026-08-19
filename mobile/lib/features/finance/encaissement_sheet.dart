import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/ecran_liste.dart';
import '../../core/ui/format.dart';
import '../../core/ui/theme.dart';

/// Modes de règlement acceptés par l'API (`ScolariteController::encaisser`).
const _modes = {
  'especes': 'Espèces',
  'mobile_money': 'Mobile money',
  'virement': 'Virement',
  'cheque': 'Chèque',
  'depot_bancaire': 'Dépôt bancaire',
};

/// Encaissement des frais de scolarité.
///
/// Écran dédié plutôt que formulaire générique : c'est le geste qui engage le
/// plus, et il demande ce qu'aucune saisie plate ne donne — voir le reste dû
/// avant de saisir, contrôler que le montant ne le dépasse pas, et repartir
/// avec le numéro de reçu à recopier sur la souche papier.
class EncaissementSheet extends ConsumerStatefulWidget {
  const EncaissementSheet({super.key, required this.dossier});

  final Map<String, dynamic> dossier;

  static Future<bool> ouvrir(BuildContext context, Map<String, dynamic> dossier) async {
    final encaisse = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => EncaissementSheet(dossier: dossier),
    );
    return encaisse ?? false;
  }

  @override
  ConsumerState<EncaissementSheet> createState() => _EncaissementSheetState();
}

class _EncaissementSheetState extends ConsumerState<EncaissementSheet> {
  final _cleFormulaire = GlobalKey<FormState>();
  final _montant = TextEditingController();

  String _mode = 'especes';
  String? _reference;
  String? _note;
  DateTime _date = DateTime.now();

  bool _envoi = false;
  String? _erreur;

  @override
  void dispose() {
    _montant.dispose();
    super.dispose();
  }

  num get _reste => (widget.dossier['reste_a_payer'] as num?) ?? 0;

  Future<void> _encaisser() async {
    if (!_cleFormulaire.currentState!.validate()) return;
    _cleFormulaire.currentState!.save();

    setState(() {
      _envoi = true;
      _erreur = null;
    });

    try {
      final reponse = await ref.read(apiClientProvider).post(
        'scolarite/dossiers/${widget.dossier['id']}/versements',
        {
          'montant': num.parse(_montant.text.replaceAll(',', '.')).round(),
          'date_versement': _date.toIso8601String().substring(0, 10),
          'mode': _mode,
          if (_reference != null) 'reference_externe': _reference,
          if (_note != null) 'note': _note,
        },
      );

      final recu = (reponse['data'] as Map?)?['numero_recu'];

      if (!mounted) return;
      Navigator.of(context).pop(true);
      // Le numéro de reçu se recopie sur la souche papier : il doit rester
      // affiché plus longtemps qu'un message ordinaire, et rester lisible
      // après la fermeture de la feuille.
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Encaissement enregistré — reçu $recu'),
          duration: const Duration(seconds: 8),
          backgroundColor: Couleurs.synchro,
        ),
      );
    } on ErreurApi catch (e) {
      setState(() {
        _erreur = e.message;
        _envoi = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final eleve = widget.dossier['eleve'];
    final nom = eleve is Map ? '${eleve['nom_complet']}' : 'Élève';
    // `classe` est une chaîne dans `DossierScolariteResource`, pas l'objet
    // imbriqué qu'exposent les autres ressources.
    final classe = eleve is Map ? eleve['classe'] as String? : null;

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      maxChildSize: 0.95,
      builder: (context, controleur) => Form(
        key: _cleFormulaire,
        child: ListView(
          controller: controleur,
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          children: [
            Text(nom, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
            if (classe != null)
              Text(classe, style: const TextStyle(color: Couleurs.texteSecondaire)),
            const SizedBox(height: 14),

            // La situation avant tout : encaisser sans savoir ce qui reste dû
            // oblige à ressortir de l'écran pour le vérifier.
            _Situation(dossier: widget.dossier),
            const SizedBox(height: 18),

            TextFormField(
              controller: _montant,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: false),
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: InputDecoration(
                labelText: 'Montant encaissé *',
                suffixText: 'F',
                helperText: _reste > 0 ? 'Reste à payer : ${formaterMontant(_reste)}' : null,
              ),
              validator: (v) {
                if (v == null || v.trim().isEmpty) return 'Requis';
                final n = num.tryParse(v);
                if (n == null || n < 1) return 'Montant invalide';
                return null;
              },
            ),
            const SizedBox(height: 8),

            // Solder d'un geste : c'est le cas le plus fréquent au guichet.
            if (_reste > 0)
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: () => setState(() => _montant.text = '${_reste.round()}'),
                  icon: const Icon(Icons.done_all, size: 18),
                  label: Text('Solder (${formaterMontant(_reste)})'),
                ),
              ),
            const SizedBox(height: 6),

            DropdownButtonFormField<String>(
              initialValue: _mode,
              decoration: const InputDecoration(labelText: 'Mode de règlement'),
              items: [
                for (final e in _modes.entries)
                  DropdownMenuItem(value: e.key, child: Text(e.value)),
              ],
              onChanged: (v) => setState(() => _mode = v ?? 'especes'),
            ),
            const SizedBox(height: 14),

            InkWell(
              onTap: () async {
                final choix = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime(2000),
                  lastDate: DateTime(2100),
                );
                if (choix != null) setState(() => _date = choix);
              },
              child: InputDecorator(
                decoration: const InputDecoration(
                  labelText: 'Date du versement',
                  suffixIcon: Icon(Icons.calendar_today, size: 18),
                ),
                child: Text(_date.toIso8601String().substring(0, 10)),
              ),
            ),
            const SizedBox(height: 14),

            TextFormField(
              decoration: const InputDecoration(
                labelText: 'Référence externe',
                helperText: 'N° de transaction mobile money, de chèque…',
              ),
              onSaved: (v) => _reference = v?.trim().isEmpty ?? true ? null : v!.trim(),
            ),
            const SizedBox(height: 14),

            TextFormField(
              maxLines: 2,
              decoration: const InputDecoration(labelText: 'Note'),
              onSaved: (v) => _note = v?.trim().isEmpty ?? true ? null : v!.trim(),
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

            FilledButton.icon(
              onPressed: _envoi ? null : _encaisser,
              icon: _envoi
                  ? const SizedBox(
                      height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.payments_outlined),
              label: const Text('Encaisser'),
            ),
            const SizedBox(height: 8),
            const Text(
              "Un SMS de confirmation part au tuteur si son numéro est renseigné.",
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11.5, color: Couleurs.texteSecondaire),
            ),
          ],
        ),
      ),
    );
  }
}

class _Situation extends StatelessWidget {
  const _Situation({required this.dossier});

  final Map<String, dynamic> dossier;

  @override
  Widget build(BuildContext context) {
    final reste = (dossier['reste_a_payer'] as num?) ?? 0;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Couleurs.fond,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          _Ligne('Total dû', dossier['total_du']),
          _Ligne('Déjà payé', dossier['total_paye']),
          if ((dossier['avance'] as num? ?? 0) > 0) _Ligne('Avance', dossier['avance']),
          const Divider(height: 18),
          _Ligne(
            'Reste à payer',
            reste,
            gras: true,
            couleur: reste <= 0 ? Couleurs.synchro : Couleurs.echec,
          ),
        ],
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne(this.libelle, this.montant, {this.gras = false, this.couleur});

  final String libelle;
  final dynamic montant;
  final bool gras;
  final Color? couleur;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(
            child: Text(
              libelle,
              style: TextStyle(
                fontSize: 13,
                fontWeight: gras ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
          Text(
            formaterMontant(montant),
            style: TextStyle(
              fontSize: gras ? 15 : 13.5,
              fontWeight: FontWeight.w800,
              color: couleur,
            ),
          ),
        ],
      ),
    );
  }
}

/// Historique des versements d'un dossier, avec l'annulation.
///
/// L'annulation est tracée en comptabilité et non destructive : le web la
/// propose, on la reprend telle quelle.
class HistoriqueVersementsSheet extends ConsumerWidget {
  const HistoriqueVersementsSheet({super.key, required this.dossier, required this.requete});

  final Map<String, dynamic> dossier;
  final RequeteListe requete;

  static Future<void> ouvrir(
    BuildContext context,
    Map<String, dynamic> dossier,
    RequeteListe requete,
  ) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => HistoriqueVersementsSheet(dossier: dossier, requete: requete),
    );
  }

  Future<String?> _demanderMotif(BuildContext context, Map v) {
    final controleur = TextEditingController();

    return showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Annuler ce versement ?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Le reçu ${v['numero_recu']} de ${formaterMontant(v['montant'])} sera annulé. '
              "L'opération est tracée en comptabilité.",
              style: const TextStyle(fontSize: 13),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: controleur,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: "Motif de l'annulation *",
                hintText: 'Ex. erreur de saisie',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('Retour')),
          FilledButton(
            onPressed: () {
              final motif = controleur.text.trim();
              if (motif.length < 3) return;
              Navigator.pop(c, motif);
            },
            child: const Text('Annuler le versement'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final versements = (dossier['versements'] as List?) ?? const [];

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.6,
      maxChildSize: 0.9,
      builder: (context, controleur) => ListView(
        controller: controleur,
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 4, 20, 12),
            child: Text('Versements',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          ),
          if (versements.isEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 10, 20, 30),
              child: Text('Aucun versement enregistré.',
                  style: TextStyle(color: Couleurs.texteSecondaire)),
            ),
          for (final v in versements)
            ListTile(
              leading: const Icon(Icons.receipt_long_outlined),
              title: Text(formaterMontant(v['montant']),
                  style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text([
                v['numero_recu'],
                formaterDate(v['date_versement']),
                _modes[v['mode']] ?? v['mode'],
              ].where((e) => e != null).join(' · ')),
              trailing: IconButton(
                icon: const Icon(Icons.cancel_outlined, color: Couleurs.echec),
                tooltip: 'Annuler ce versement',
                onPressed: () => _annuler(context, ref, v),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _annuler(BuildContext context, WidgetRef ref, Map v) async {
    // Le motif est exigé par l'API (`required, min:3`) et c'est justifié : une
    // écriture annulée sans raison consignée est ingérable en comptabilité. On
    // le demande donc, au lieu d'une simple confirmation.
    final motif = await _demanderMotif(context, v);
    if (motif == null || !context.mounted) return;

    try {
      await ref.read(apiClientProvider).post(
        'versements/${v['id']}/annuler',
        {'motif': motif},
      );
      ref.invalidate(listeApiProvider(requete));
      if (!context.mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Versement annulé.')),
      );
    } on ErreurApi catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Couleurs.echec),
      );
    }
  }
}
