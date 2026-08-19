import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import 'ecran_liste.dart';
import 'theme.dart';

/// Type d'un champ de formulaire.
enum TypeChamp { texte, texteLong, nombre, montant, date, choix, bascule }

/// Description d'un champ, telle que le module la déclare.
///
/// Décrire les champs plutôt qu'écrire cent formulaires : les modules du web
/// sont pour l'essentiel des saisies plates dont seuls les libellés et les
/// règles changent. Un formulaire codé à la main par module aurait multiplié
/// les divergences de validation et de rendu.
class Champ {
  const Champ({
    required this.cle,
    required this.libelle,
    this.type = TypeChamp.texte,
    this.requis = false,
    this.options,
    this.optionsDepuis,
    this.indication,
    this.min,
    this.max,
  });

  final String cle;
  final String libelle;
  final TypeChamp type;
  final bool requis;

  /// Choix fixes, pour un champ `choix`.
  final Map<String, String>? options;

  /// Choix chargés depuis l'API. Les libellés viennent de
  /// `nom`/`libelle`/`nom_complet`, l'identifiant de `id`.
  final ChoixDistants? optionsDepuis;

  final String? indication;
  final num? min;
  final num? max;
}

class ChoixDistants {
  const ChoixDistants(this.chemin, {this.cleListe});

  final String chemin;
  final String? cleListe;
}

/// Feuille de saisie générique : création comme modification.
///
/// Une feuille et non une page : l'utilisateur garde la liste derrière et la
/// voit se mettre à jour en se refermant.
class FormulaireSheet extends ConsumerStatefulWidget {
  const FormulaireSheet({
    super.key,
    required this.titre,
    required this.champs,
    required this.chemin,
    this.methode = 'POST',
    this.valeursInitiales,
    this.messageSucces,
  });

  final String titre;
  final List<Champ> champs;

  /// Chemin d'écriture, déjà complété de l'identifiant pour une modification.
  final String chemin;
  final String methode;
  final Map<String, dynamic>? valeursInitiales;
  final String? messageSucces;

  /// Ouvre la feuille et indique si une écriture a eu lieu — l'appelant s'en
  /// sert pour rafraîchir sa liste.
  static Future<bool> ouvrir(
    BuildContext context, {
    required String titre,
    required List<Champ> champs,
    required String chemin,
    String methode = 'POST',
    Map<String, dynamic>? valeursInitiales,
    String? messageSucces,
  }) async {
    final resultat = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => FormulaireSheet(
        titre: titre,
        champs: champs,
        chemin: chemin,
        methode: methode,
        valeursInitiales: valeursInitiales,
        messageSucces: messageSucces,
      ),
    );

    return resultat ?? false;
  }

  @override
  ConsumerState<FormulaireSheet> createState() => _FormulaireSheetState();
}

class _FormulaireSheetState extends ConsumerState<FormulaireSheet> {
  final _cleFormulaire = GlobalKey<FormState>();
  late final Map<String, dynamic> _valeurs;
  bool _envoi = false;
  String? _erreur;

  /// Erreurs de validation renvoyées par le serveur, champ par champ : c'est
  /// lui qui détient les règles, les redire ici les ferait diverger.
  Map<String, dynamic> _erreursChamps = const {};

  @override
  void initState() {
    super.initState();
    _valeurs = {...?widget.valeursInitiales};
  }

  Future<void> _soumettre() async {
    if (!_cleFormulaire.currentState!.validate()) return;
    _cleFormulaire.currentState!.save();

    setState(() {
      _envoi = true;
      _erreur = null;
      _erreursChamps = const {};
    });

    try {
      // L'API n'expose que POST côté client ; `_method` porte la méthode réelle
      // pour une modification ou une suppression, comme le fait le web.
      await ref.read(apiClientProvider).post(widget.chemin, {
        ..._valeurs,
        if (widget.methode != 'POST') '_method': widget.methode,
      });

      if (!mounted) return;
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(widget.messageSucces ?? 'Enregistré.')),
      );
    } on ErreurApi catch (e) {
      setState(() {
        _erreur = e.message;
        _erreursChamps = e.erreurs ?? const {};
        _envoi = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
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
            Text(widget.titre,
                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            for (final champ in widget.champs) ...[
              _ChampSaisie(
                champ: champ,
                valeur: _valeurs[champ.cle],
                erreur: _premiereErreur(champ.cle),
                onChange: (v) => _valeurs[champ.cle] = v,
              ),
              const SizedBox(height: 14),
            ],
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
              onPressed: _envoi ? null : _soumettre,
              child: _envoi
                  ? const SizedBox(
                      height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Enregistrer'),
            ),
          ],
        ),
      ),
    );
  }

  String? _premiereErreur(String cle) {
    final valeur = _erreursChamps[cle];
    if (valeur is List && valeur.isNotEmpty) return '${valeur.first}';
    return valeur == null ? null : '$valeur';
  }
}

class _ChampSaisie extends ConsumerWidget {
  const _ChampSaisie({
    required this.champ,
    required this.valeur,
    required this.erreur,
    required this.onChange,
  });

  final Champ champ;
  final dynamic valeur;
  final String? erreur;
  final ValueChanged<dynamic> onChange;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final libelle = champ.requis ? '${champ.libelle} *' : champ.libelle;

    switch (champ.type) {
      case TypeChamp.bascule:
        return SwitchListTile(
          title: Text(champ.libelle),
          subtitle: champ.indication == null ? null : Text(champ.indication!),
          value: valeur == true,
          contentPadding: EdgeInsets.zero,
          onChanged: onChange,
        );

      case TypeChamp.choix:
        if (champ.optionsDepuis != null) {
          return _ChoixDistant(champ: champ, valeur: valeur, erreur: erreur, onChange: onChange);
        }
        return DropdownButtonFormField<String>(
          initialValue: valeur?.toString(),
          decoration: InputDecoration(labelText: libelle, errorText: erreur),
          items: [
            for (final e in champ.options!.entries)
              DropdownMenuItem(value: e.key, child: Text(e.value)),
          ],
          validator: (v) => champ.requis && (v == null || v.isEmpty) ? 'Requis' : null,
          onChanged: onChange,
        );

      case TypeChamp.date:
        return _ChampDate(champ: champ, valeur: valeur, erreur: erreur, onChange: onChange);

      case TypeChamp.nombre:
      case TypeChamp.montant:
        return TextFormField(
          initialValue: valeur?.toString(),
          keyboardType: TextInputType.numberWithOptions(
            decimal: champ.type == TypeChamp.nombre,
          ),
          inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
          decoration: InputDecoration(
            labelText: libelle,
            errorText: erreur,
            helperText: champ.indication,
            suffixText: champ.type == TypeChamp.montant ? 'F' : null,
          ),
          validator: (v) {
            if (champ.requis && (v == null || v.trim().isEmpty)) return 'Requis';
            if (v == null || v.trim().isEmpty) return null;
            final n = num.tryParse(v.replaceAll(',', '.'));
            if (n == null) return 'Nombre attendu';
            if (champ.min != null && n < champ.min!) return 'Minimum ${champ.min}';
            if (champ.max != null && n > champ.max!) return 'Maximum ${champ.max}';
            return null;
          },
          onSaved: (v) => onChange(
            v == null || v.trim().isEmpty ? null : num.tryParse(v.replaceAll(',', '.')),
          ),
        );

      case TypeChamp.texteLong:
      case TypeChamp.texte:
        return TextFormField(
          initialValue: valeur?.toString(),
          maxLines: champ.type == TypeChamp.texteLong ? 4 : 1,
          decoration: InputDecoration(
            labelText: libelle,
            errorText: erreur,
            helperText: champ.indication,
            alignLabelWithHint: champ.type == TypeChamp.texteLong,
          ),
          validator: (v) =>
              champ.requis && (v == null || v.trim().isEmpty) ? 'Requis' : null,
          onSaved: (v) => onChange(v?.trim().isEmpty ?? true ? null : v!.trim()),
        );
    }
  }
}

class _ChampDate extends StatefulWidget {
  const _ChampDate({
    required this.champ,
    required this.valeur,
    required this.erreur,
    required this.onChange,
  });

  final Champ champ;
  final dynamic valeur;
  final String? erreur;
  final ValueChanged<dynamic> onChange;

  @override
  State<_ChampDate> createState() => _ChampDateState();
}

class _ChampDateState extends State<_ChampDate> {
  DateTime? _date;

  @override
  void initState() {
    super.initState();
    _date = DateTime.tryParse('${widget.valeur}');
    // Une date obligatoire est presque toujours « aujourd'hui » : la
    // pré-remplir épargne un geste sur le cas courant.
    if (_date == null && widget.champ.requis) {
      _date = DateTime.now();
      widget.onChange(_iso(_date!));
    }
  }

  static String _iso(DateTime d) => d.toIso8601String().substring(0, 10);

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () async {
        final choix = await showDatePicker(
          context: context,
          initialDate: _date ?? DateTime.now(),
          firstDate: DateTime(2000),
          lastDate: DateTime(2100),
        );
        if (choix == null) return;
        setState(() => _date = choix);
        widget.onChange(_iso(choix));
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: widget.champ.requis ? '${widget.champ.libelle} *' : widget.champ.libelle,
          errorText: widget.erreur,
          suffixIcon: const Icon(Icons.calendar_today, size: 18),
        ),
        child: Text(_date == null ? '—' : _iso(_date!)),
      ),
    );
  }
}

/// Liste déroulante alimentée par l'API (classes, élèves, personnels…).
class _ChoixDistant extends ConsumerWidget {
  const _ChoixDistant({
    required this.champ,
    required this.valeur,
    required this.erreur,
    required this.onChange,
  });

  final Champ champ;
  final dynamic valeur;
  final String? erreur;
  final ValueChanged<dynamic> onChange;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final source = champ.optionsDepuis!;
    final async = ref.watch(
      listeApiProvider(RequeteListe(source.chemin, cleListe: source.cleListe)),
    );

    return async.when(
      loading: () => InputDecorator(
        decoration: InputDecoration(labelText: champ.libelle),
        child: const Text('Chargement…', style: TextStyle(color: Couleurs.texteSecondaire)),
      ),
      error: (e, _) => InputDecorator(
        decoration: InputDecoration(
          labelText: champ.libelle,
          errorText: e is ErreurApi ? e.message : 'Chargement impossible',
        ),
        child: const Text('—'),
      ),
      data: (lignes) => DropdownButtonFormField<int>(
        initialValue: valeur is int ? valeur : int.tryParse('$valeur'),
        isExpanded: true,
        decoration: InputDecoration(
          labelText: champ.requis ? '${champ.libelle} *' : champ.libelle,
          errorText: erreur,
        ),
        items: [
          for (final l in lignes)
            DropdownMenuItem(
              value: l['id'] as int?,
              child: Text(
                '${l['nom'] ?? l['libelle'] ?? l['nom_complet'] ?? l['immatriculation'] ?? l['id']}',
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
        validator: (v) => champ.requis && v == null ? 'Requis' : null,
        onChanged: onChange,
      ),
    );
  }
}
