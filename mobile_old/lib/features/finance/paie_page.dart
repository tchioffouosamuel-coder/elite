import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/nav/barre_app.dart';
import '../../core/network/api_client.dart';
import '../../core/ui/etats.dart';
import '../../core/ui/format.dart';
import '../../core/ui/permission.dart';
import '../../core/ui/theme.dart';

const _modes = {
  'especes': 'Espèces',
  'mobile_money': 'Mobile money',
  'virement': 'Virement',
  'cheque': 'Chèque',
  'depot_bancaire': 'Dépôt bancaire',
};

const _moisFr = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

/// Période de paie consultée. Le mois courant par défaut, comme le serveur.
class Periode {
  const Periode(this.annee, this.mois);

  final int annee;
  final int mois;

  String get libelle => '${_moisFr[mois - 1]} $annee';

  Periode get precedent => mois == 1 ? Periode(annee - 1, 12) : Periode(annee, mois - 1);
  Periode get suivant => mois == 12 ? Periode(annee + 1, 1) : Periode(annee, mois + 1);

  @override
  bool operator ==(Object other) =>
      other is Periode && other.annee == annee && other.mois == mois;

  @override
  int get hashCode => Object.hash(annee, mois);
}

final _paieProvider = FutureProvider.family<Map<String, dynamic>, Periode>((ref, periode) async {
  final reponse = await ref.watch(apiClientProvider).get(
    'paie',
    params: {'annee': periode.annee, 'mois': periode.mois},
  );
  final data = reponse['data'];
  return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
});

/// Paie du personnel : préparation, arrêté, paiement.
///
/// Le cycle est volontairement rendu visible — préparé, arrêté, payé — parce
/// que c'est lui qui gouverne ce qu'on a le droit de faire : un bulletin arrêté
/// ne se recalcule plus, un bulletin payé ne s'arrête pas deux fois. Masquer
/// l'étape derrière un simple bouton exposerait à des gestes que le serveur
/// refuserait de toute façon.
class PaiePage extends ConsumerStatefulWidget {
  const PaiePage({super.key});

  @override
  ConsumerState<PaiePage> createState() => _PaiePageState();
}

class _PaiePageState extends ConsumerState<PaiePage> {
  Periode _periode = Periode(DateTime.now().year, DateTime.now().month);
  final Set<int> _selection = {};

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_paieProvider(_periode));
    final peutGerer = peutEcrire(context, 'finance.paie');

    return Scaffold(
      appBar: BarreApp(titre: 'Paie'),
      body: Column(
        children: [
          _SelecteurPeriode(
            periode: _periode,
            onChange: (p) => setState(() {
              _periode = p;
              // La sélection porte sur des bulletins d'une période donnée :
              // la garder en changeant de mois agirait sur les mauvais.
              _selection.clear();
            }),
          ),
          Expanded(
            child: async.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => EtatErreur(
                message: e is ErreurApi ? e.message : '$e',
                onReessayer: () => ref.invalidate(_paieProvider(_periode)),
              ),
              data: (paie) {
                final bulletins = (paie['bulletins'] as List?)?.cast<Map<String, dynamic>>() ?? const [];

                if (bulletins.isEmpty) {
                  return EtatVide(
                    message: 'Aucun bulletin pour ${_periode.libelle}.',
                    icone: Icons.request_quote_outlined,
                    indication: peutGerer
                        ? 'Préparez la paie pour générer les bulletins du mois.'
                        : null,
                    action: peutGerer
                        ? FilledButton.icon(
                            onPressed: _preparerLot,
                            icon: const Icon(Icons.play_arrow),
                            label: const Text('Préparer la paie'),
                          )
                        : null,
                  );
                }

                return RefreshIndicator(
                  onRefresh: () async => ref.invalidate(_paieProvider(_periode)),
                  child: Column(
                    children: [
                      _Totaux(totaux: paie['totaux'] as Map? ?? const {}),
                      Expanded(
                        child: ListView.separated(
                          padding: const EdgeInsets.only(bottom: 92),
                          itemCount: bulletins.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (_, i) => _LigneBulletin(
                            bulletin: bulletins[i],
                            selectionne: _selection.contains(bulletins[i]['id']),
                            selectionActive: _selection.isNotEmpty,
                            peutGerer: peutGerer,
                            onSelection: (v) => setState(() {
                              final id = bulletins[i]['id'] as int;
                              v ? _selection.add(id) : _selection.remove(id);
                            }),
                            onDetail: () => _ouvrirDetail(bulletins[i]),
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
      floatingActionButton: !peutGerer
          ? null
          : _selection.isEmpty
              ? FloatingActionButton.extended(
                  onPressed: _preparerLot,
                  icon: const Icon(Icons.play_arrow),
                  label: const Text('Préparer'),
                )
              : FloatingActionButton.extended(
                  onPressed: _actionsSurSelection,
                  icon: const Icon(Icons.checklist),
                  label: Text('${_selection.length} sélectionné(s)'),
                ),
    );
  }

  Future<void> _preparerLot() async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('Préparer la paie de ${_periode.libelle} ?'),
        content: const Text(
          'Les bulletins seront calculés pour tout le personnel rémunéré. '
          'Un bulletin déjà arrêté ne sera pas recalculé.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Préparer')),
        ],
      ),
    );

    if (confirme != true) return;
    await _appeler('paie/preparer', {'annee': _periode.annee, 'mois': _periode.mois});
  }

  Future<void> _actionsSurSelection() async {
    final choix = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
              child: Text('${_selection.length} bulletin(s) sélectionné(s)',
                  style: const TextStyle(fontWeight: FontWeight.w800)),
            ),
            ListTile(
              leading: const Icon(Icons.lock_outline),
              title: const Text('Arrêter'),
              subtitle: const Text('Fige le calcul — le bulletin ne sera plus recalculé'),
              onTap: () => Navigator.pop(context, 'arreter'),
            ),
            ListTile(
              leading: const Icon(Icons.payments_outlined, color: Couleurs.synchro),
              title: const Text('Payer'),
              subtitle: const Text('Enregistre le règlement des bulletins arrêtés'),
              onTap: () => Navigator.pop(context, 'payer'),
            ),
            ListTile(
              leading: const Icon(Icons.clear),
              title: const Text('Vider la sélection'),
              onTap: () => Navigator.pop(context, 'vider'),
            ),
          ],
        ),
      ),
    );

    if (!mounted || choix == null) return;

    switch (choix) {
      case 'vider':
        setState(_selection.clear);
      case 'arreter':
        await _arreterLot();
      case 'payer':
        await _payerLot();
    }
  }

  Future<void> _arreterLot() async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('Arrêter ${_selection.length} bulletin(s) ?'),
        content: const Text(
          "Un bulletin arrêté ne se recalcule plus, même si la rémunération "
          "change ensuite. C'est l'étape qui fige la paie du mois.",
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Arrêter')),
        ],
      ),
    );

    if (confirme != true) return;
    await _appeler('paie/bulletins/arreter-lot', {'ids': _selection.toList()});
  }

  Future<void> _payerLot() async {
    final reglement = await _demanderReglement();
    if (reglement == null) return;

    await _appeler('paie/bulletins/payer-lot', {
      'ids': _selection.toList(),
      ...reglement,
    });
  }

  /// Mode et date de règlement, exigés par l'API pour un paiement.
  Future<Map<String, dynamic>?> _demanderReglement() async {
    var mode = 'especes';
    var date = DateTime.now();

    return showDialog<Map<String, dynamic>>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, majEtat) => AlertDialog(
          title: const Text('Enregistrer le paiement'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: mode,
                decoration: const InputDecoration(labelText: 'Mode de règlement'),
                items: [
                  for (final e in _modes.entries)
                    DropdownMenuItem(value: e.key, child: Text(e.value)),
                ],
                onChanged: (v) => majEtat(() => mode = v ?? 'especes'),
              ),
              const SizedBox(height: 14),
              InkWell(
                onTap: () async {
                  final choix = await showDatePicker(
                    context: c,
                    initialDate: date,
                    firstDate: DateTime(2000),
                    lastDate: DateTime(2100),
                  );
                  if (choix != null) majEtat(() => date = choix);
                },
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Date du paiement',
                    suffixIcon: Icon(Icons.calendar_today, size: 18),
                  ),
                  child: Text(date.toIso8601String().substring(0, 10)),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c), child: const Text('Annuler')),
            FilledButton(
              onPressed: () => Navigator.pop(c, {
                'mode': mode,
                'date_paiement': date.toIso8601String().substring(0, 10),
              }),
              child: const Text('Payer'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _appeler(String chemin, Map<String, dynamic> corps) async {
    try {
      final reponse = await ref.read(apiClientProvider).post(chemin, corps);
      ref.invalidate(_paieProvider(_periode));
      if (!mounted) return;
      setState(_selection.clear);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${reponse['message'] ?? 'Opération effectuée.'}'),
          backgroundColor: Couleurs.synchro,
        ),
      );
    } on ErreurApi catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: Couleurs.echec),
      );
    }
  }

  void _ouvrirDetail(Map<String, dynamic> bulletin) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _DetailBulletin(
        bulletin: bulletin,
        onAction: (chemin, corps) async {
          Navigator.pop(context);
          await _appeler(chemin, corps);
        },
        peutGerer: peutEcrire(context, 'finance.paie'),
      ),
    );
  }
}

class _SelecteurPeriode extends StatelessWidget {
  const _SelecteurPeriode({required this.periode, required this.onChange});

  final Periode periode;
  final ValueChanged<Periode> onChange;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Couleurs.surface,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.chevron_left),
            onPressed: () => onChange(periode.precedent),
          ),
          Expanded(
            child: Text(
              periode.libelle,
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.chevron_right),
            onPressed: () => onChange(periode.suivant),
          ),
        ],
      ),
    );
  }
}

class _Totaux extends StatelessWidget {
  const _Totaux({required this.totaux});

  final Map totaux;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      color: Couleurs.navy900.withValues(alpha: 0.04),
      child: Row(
        children: [
          _Total('Effectif', '${totaux['effectif'] ?? 0}'),
          _Total('Net à payer', formaterMontant(totaux['net_a_payer'])),
          _Total('Coût employeur', formaterMontant(totaux['cout_employeur'])),
        ],
      ),
    );
  }
}

class _Total extends StatelessWidget {
  const _Total(this.libelle, this.valeur);

  final String libelle;
  final String valeur;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(libelle,
              style: const TextStyle(fontSize: 11, color: Couleurs.texteSecondaire)),
          const SizedBox(height: 2),
          Text(valeur, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
        ],
      ),
    );
  }
}

class _LigneBulletin extends StatelessWidget {
  const _LigneBulletin({
    required this.bulletin,
    required this.selectionne,
    required this.selectionActive,
    required this.peutGerer,
    required this.onSelection,
    required this.onDetail,
  });

  final Map<String, dynamic> bulletin;
  final bool selectionne;
  final bool selectionActive;
  final bool peutGerer;
  final ValueChanged<bool> onSelection;
  final VoidCallback onDetail;

  @override
  Widget build(BuildContext context) {
    final personnel = bulletin['personnel'];
    final statut = '${bulletin['statut']}';

    return ListTile(
      // Appui long pour entrer en sélection : le tap simple reste le geste
      // naturel pour consulter un bulletin, le plus fréquent des deux.
      onTap: selectionActive && peutGerer ? () => onSelection(!selectionne) : onDetail,
      onLongPress: peutGerer ? () => onSelection(!selectionne) : null,
      leading: selectionActive && peutGerer
          ? Checkbox(value: selectionne, onChanged: (v) => onSelection(v ?? false))
          : CircleAvatar(
              backgroundColor: _couleurStatut(statut).withValues(alpha: 0.14),
              child: Icon(_iconeStatut(statut), size: 19, color: _couleurStatut(statut)),
            ),
      title: Text(personnel is Map ? '${personnel['nom_complet']}' : '—'),
      subtitle: Text(
        [
          _libelleStatut(statut),
          if (bulletin['emarge'] == true) 'émargé',
        ].join(' · '),
        style: TextStyle(fontSize: 12.5, color: _couleurStatut(statut)),
      ),
      trailing: Text(
        formaterMontant(bulletin['net_a_payer']),
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
    );
  }

  static String _libelleStatut(String s) => switch (s) {
        'brouillon' => 'Préparé',
        'arrete' => 'Arrêté',
        'paye' => 'Payé',
        _ => s,
      };

  static IconData _iconeStatut(String s) => switch (s) {
        'brouillon' => Icons.edit_note,
        'arrete' => Icons.lock_outline,
        'paye' => Icons.check_circle_outline,
        _ => Icons.description_outlined,
      };

  static Color _couleurStatut(String s) => switch (s) {
        'brouillon' => Couleurs.enAttente,
        'arrete' => Couleurs.navy800,
        'paye' => Couleurs.synchro,
        _ => Couleurs.texteSecondaire,
      };
}

class _DetailBulletin extends StatelessWidget {
  const _DetailBulletin({
    required this.bulletin,
    required this.onAction,
    required this.peutGerer,
  });

  final Map<String, dynamic> bulletin;
  final Future<void> Function(String chemin, Map<String, dynamic> corps) onAction;
  final bool peutGerer;

  @override
  Widget build(BuildContext context) {
    final personnel = bulletin['personnel'];
    final statut = '${bulletin['statut']}';
    final id = bulletin['id'];

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.75,
      maxChildSize: 0.95,
      builder: (context, controleur) => ListView(
        controller: controleur,
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
        children: [
          Text(personnel is Map ? '${personnel['nom_complet']}' : 'Bulletin',
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          Text(
            [bulletin['numero'], bulletin['periode']].where((e) => e != null).join(' · '),
            style: const TextStyle(color: Couleurs.texteSecondaire, fontSize: 12.5),
          ),
          const SizedBox(height: 16),

          _Rubrique('Jours ouvrables', '${bulletin['jours_ouvrables'] ?? '—'}'),
          _Rubrique('Jours travaillés', '${bulletin['jours_travailles'] ?? '—'}'),
          const Divider(height: 22),
          _Rubrique('Salaire brut', formaterMontant(bulletin['salaire_brut'])),
          _Rubrique('Net taxable', formaterMontant(bulletin['net_taxable'])),
          _Rubrique('Charges salariales', formaterMontant(bulletin['charges_salariales'])),
          _Rubrique('Total déductions', formaterMontant(bulletin['total_deductions'])),
          const Divider(height: 22),
          _Rubrique('Net à payer', formaterMontant(bulletin['net_a_payer']), gras: true),
          _Rubrique('Charges patronales', formaterMontant(bulletin['charges_patronales'])),
          _Rubrique('Coût employeur', formaterMontant(bulletin['cout_employeur'])),

          if (bulletin['date_paiement'] != null) ...[
            const Divider(height: 22),
            _Rubrique('Payé le', formaterDate(bulletin['date_paiement'])),
            _Rubrique('Mode', _modes[bulletin['mode_paiement']] ?? '—'),
          ],

          if (peutGerer) ...[
            const SizedBox(height: 20),
            // Une seule action proposée à la fois : celle que le statut
            // autorise réellement. Le serveur refuserait les autres.
            if (statut == 'brouillon')
              FilledButton.icon(
                onPressed: () => onAction('paie/bulletins/$id/arreter', const {}),
                icon: const Icon(Icons.lock_outline),
                label: const Text('Arrêter ce bulletin'),
              ),
            if (statut == 'arrete')
              FilledButton.icon(
                onPressed: () => onAction('paie/bulletins/$id/payer', {
                  'mode': 'especes',
                  'date_paiement': DateTime.now().toIso8601String().substring(0, 10),
                }),
                icon: const Icon(Icons.payments_outlined),
                label: const Text('Payer en espèces'),
              ),
            if (statut == 'paye' && bulletin['emarge'] != true) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => onAction('paie/bulletins/$id/emarger', const {}),
                icon: const Icon(Icons.draw_outlined),
                label: const Text('Marquer émargé'),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _Rubrique extends StatelessWidget {
  const _Rubrique(this.libelle, this.valeur, {this.gras = false});

  final String libelle;
  final String valeur;
  final bool gras;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(libelle,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: gras ? FontWeight.w700 : FontWeight.w500,
                )),
          ),
          Text(valeur,
              style: TextStyle(
                fontSize: gras ? 15.5 : 13.5,
                fontWeight: FontWeight.w800,
                color: gras ? Couleurs.navy900 : null,
              )),
        ],
      ),
    );
  }
}
