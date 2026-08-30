<?php

namespace App\Support\Pdf;

use App\Models\ActiviteRentree;
use App\Models\Apee;
use App\Models\AssuranceScolaire;
use App\Models\ConseilEcole;
use App\Models\EquipementMobilier;
use App\Models\Personnel;
use App\Models\School;
use App\Models\VenteDenree;
use App\Models\VisiteAutorite;
use App\Support\Pdf\Concerns\RenduDocument;
use Illuminate\Support\Collection;
use Mpdf\Output\Destination;

/**
 * Rapport de rentrée scolaire, canevas MINEDUB — assemble en un seul document
 * ce que les écrans Élèves, Personnel, Infrastructures, Finances et Vie
 * scolaire exposent séparément (cf. RapportRentreeService::generer()).
 *
 * Quatre rubriques du canevas papier n'ont pas d'équivalent dans le système
 * (évolution des effectifs sur 5 ans, taux de fréquentation, assiduité des
 * enseignants, protocole anti-COVID) : imprimées comme des lignes « à
 * compléter manuellement » plutôt qu'omises, pour que le document reste
 * fidèle à la structure du canevas déposé à la délégation.
 */
class RapportRentreeGenerator
{
    use RenduDocument;

    private const LIBELLES_RUBRIQUE_BUDGET = [
        'primes_rendement' => 'Primes de rendement',
        'projet_ecole' => "Projet d'école",
        'fenassco' => 'FENASSCO',
        'fonctionnement' => 'Fonctionnement',
        'evaluation' => 'Évaluation',
    ];

    private const LIBELLES_TYPE_INFRA = [
        'wc' => 'WC', 'cloture' => 'Clôture', 'point_eau' => "Point d'eau",
        'electricite' => 'Électricité', 'aire_jeu' => 'Aire de jeu', 'logement_maitre' => 'Logement maître', 'autre' => 'Autre',
    ];

    private const LIBELLES_MATERIAU = ['dur' => 'Dur', 'semi_dur' => 'Semi-dur', 'provisoire' => 'Matériaux provisoires'];

    private const LIBELLES_ETAT = ['bon' => 'Bon', 'assez_bon' => 'Assez-bon', 'mauvais' => 'Mauvais'];

    private const LIBELLES_TEXTE = [
        'securite_cloture' => 'Clôture',
        'securite_detecteur_metaux' => 'Détecteur de métaux',
        'securite_controle_armes' => 'Contrôle des armes blanches',
        'securite_surveillance_pauses' => 'Surveillance des pauses',
        'securite_autres_mesures' => 'Autres mesures',
        'probleme_infrastructure_maternelle' => "Problèmes d'infrastructure (maternelle)",
        'doleances' => 'Doléances',
        'problemes_fonctionnement' => 'Problèmes rencontrés dans le fonctionnement',
        'resolutions_conseil_maitres' => 'Résolutions des conseils des maîtres',
        'gouvernements_enfants' => "Gouvernements d'enfants",
        'irr' => 'IRR',
        'evenements_socioculturels' => 'Événements socio-culturels',
        'fetes_nationales' => 'Fêtes nationales',
        'conclusion_generale' => 'Conclusion générale',
    ];

    public function build(array $d): string
    {
        /** @var School $school */
        $school = $d['school'];

        $mpdf = MpdfFactory::make(['format' => 'A4', 'orientation' => 'P'], $school);
        $mpdf->SetTitle('Rapport de rentrée scolaire');

        $mpdf->WriteHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                .'<style>'.$this->stylesBase().$this->stylesPropres().'</style></head><body>'
                .$this->enTeteEcole($school)
                .'<h1 class="titre" style="text-align:center;">RAPPORT DE LA RENTRÉE SCOLAIRE '.$this->e($d['annee']->libelle ?? '').'</h1>'
                .$this->sectionIdentite($d)
                .$this->sectionEffectifs($d)
                .$this->sectionMinorites($d)
                .$this->sectionAges($d)
                .$this->sectionPersonnel($d)
                .$this->sectionRatio($d)
                .$this->sectionInfrastructures($d)
                .$this->sectionBudget($d)
                .$this->sectionVisites($d)
                .$this->sectionActivites($d)
                .$this->sectionAssurances($d)
                .$this->sectionSecurite($d)
                .$this->sectionVentes($d)
                .$this->sectionConseilApee($d)
                .$this->sectionTextesLibres($d)
                .$this->signatureChef($school)
                .'</body></html>'
        );

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return 'h2.section{background:'.self::ARDOISE.';color:#fff;padding:1.5mm 2mm;font-size:3mm;'
            .'text-transform:uppercase;margin:5mm 0 2mm 0;}'
            .'.note{font-size:2.3mm;color:#888;font-style:italic;}'
            .'.champ{display:inline-block;width:49%;font-size:2.6mm;margin:0.6mm 0;}'
            .'.champ b{color:'.self::ARDOISE.';}';
    }

    /** Tableau générique : en-têtes fixes, lignes de cellules déjà formatées en texte. */
    private function tableau(array $entetes, array $lignes, ?string $vide = null): string
    {
        if (count($lignes) === 0) {
            return '<p class="note">'.$this->e($vide ?? 'Aucune donnée enregistrée.').'</p>';
        }

        $html = '<table><thead><tr>';
        foreach ($entetes as $entete) {
            $html .= '<th>'.$this->e($entete).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($lignes as $ligne) {
            $html .= '<tr>';
            foreach ($ligne as $cellule) {
                $html .= '<td>'.$this->e($cellule ?? '—').'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    private function sectionIdentite(array $d): string
    {
        $i = $d['identite'];
        $school = $d['school'];

        $champs = [
            'Arrondissement' => $i['arrondissement'],
            'Secteur' => $i['secteur'],
            'Cycle' => $i['cycle'],
            'Mode de fonctionnement' => $i['mode_fonctionnement'],
            'Adresse' => $school->address,
            'Téléphone' => $school->phone,
            'E-mail' => $school->email,
            'Année de création' => $i['annee_creation'],
            "Année d'ouverture" => $i['annee_ouverture'],
            "Arrêté d'ouverture" => $i['numero_arrete_ouverture'],
            "Autorisation d'ouverture" => $i['numero_autorisation_ouverture'],
            'Fondateur' => trim(($i['fondateur_nom'] ?? '').(($i['fondateur_contact'] ?? null) ? ' — '.$i['fondateur_contact'] : '')) ?: null,
            'Directeur' => trim(($i['directeur_nom'] ?? '').(($i['directeur_contact'] ?? null) ? ' — '.$i['directeur_contact'] : '')) ?: null,
        ];

        $html = '<h2 class="section">I. Présentation de l\'établissement</h2>';
        foreach ($champs as $label => $valeur) {
            $html .= '<div class="champ"><b>'.$this->e($label).' :</b> '.$this->e($valeur ?? '—').'</div>';
        }

        return $html;
    }

    private function sectionEffectifs(array $d): string
    {
        $lignes = [];
        $totaux = ['garcons' => 0, 'filles' => 0, 'total' => 0, 'camerounais' => 0, 'non_camerounais' => 0, 'refugies' => 0, 'redoublants' => 0, 'sans_acte_naissance' => 0];

        foreach ($d['effectifs_par_classe'] as $ligne) {
            $lignes[] = [
                $ligne['classe']['nom'],
                $ligne['garcons']['total'], $ligne['filles']['total'], $ligne['total']['total'],
                $ligne['total']['camerounais'], $ligne['total']['non_camerounais'],
                $ligne['total']['refugies'], $ligne['total']['redoublants'], $ligne['total']['sans_acte_naissance'],
            ];
            $totaux['garcons'] += $ligne['garcons']['total'];
            $totaux['filles'] += $ligne['filles']['total'];
            $totaux['total'] += $ligne['total']['total'];
            $totaux['camerounais'] += $ligne['total']['camerounais'];
            $totaux['non_camerounais'] += $ligne['total']['non_camerounais'];
            $totaux['refugies'] += $ligne['total']['refugies'];
            $totaux['redoublants'] += $ligne['total']['redoublants'];
            $totaux['sans_acte_naissance'] += $ligne['total']['sans_acte_naissance'];
        }

        if (count($lignes) > 0) {
            $lignes[] = ['TOTAL', ...array_values($totaux)];
        }

        return '<h2 class="section">II.1 Élèves — effectifs par classe</h2>'
            .$this->tableau(['Classe', 'G', 'F', 'T', 'Camerounais', 'Non camerounais', 'Réfugiés', 'Redoublants', 'Sans acte de naissance'], $lignes)
            .'<p class="note">Tableaux 1, 3, 4, 6 et 8 du canevas — évolution des effectifs sur 5 ans (tableau 2) et taux de fréquentation (tableau 9) : non suivis par le système, à compléter manuellement.</p>';
    }

    private function sectionMinorites(array $d): string
    {
        $m = $d['minorites'];
        $lignes = [
            ['Bororo', $m['bororo']['garcons'], $m['bororo']['filles'], $m['bororo']['total']],
            ['Baka', $m['baka']['garcons'], $m['baka']['filles'], $m['baka']['total']],
            ['Déplacés internes', $m['deplaces_internes']['garcons'], $m['deplaces_internes']['filles'], $m['deplaces_internes']['total']],
            ['TOTAL', $m['total']['garcons'], $m['total']['filles'], $m['total']['total']],
        ];

        return '<h2 class="section">II.2 Effectifs des minorités (tableau 5)</h2>'.$this->tableau(['Minorité', 'G', 'F', 'T'], $lignes);
    }

    private function sectionAges(array $d): string
    {
        $lignes = array_map(fn (array $l) => [$l['age'].' ans', $l['garcons'], $l['filles'], $l['total']], $d['pyramide_ages']);

        return '<h2 class="section">II.3 Pyramide des âges (tableau 7)</h2>'.$this->tableau(['Âge', 'G', 'F', 'T'], $lignes);
    }

    private function sectionPersonnel(array $d): string
    {
        $p = $d['personnel'];

        $ventilation = fn (array $comptes) => count($comptes) === 0
            ? '<p class="note">Aucune donnée.</p>'
            : $this->tableau(['Libellé', 'Effectif'], collect($comptes)->map(fn ($n, $k) => [$k, $n])->values()->all());

        $agents = fn (Collection $c, array $colonnes) => $this->tableau(
            $colonnes,
            $c->map(fn (Personnel $ag) => [$ag->nom_complet, $ag->fonction])->all(),
            'Aucun agent concerné.',
        );

        return '<h2 class="section">III. Personnel — mise en place (tableaux 10-15)</h2>'
            .'<p class="note">Par grade / titre</p>'.$ventilation($p['par_grade'])
            .'<p class="note">Par type de contrat</p>'.$ventilation($p['par_contrat'])
            .'<p class="note">Par statut</p>'.$ventilation($p['par_statut_contrat'])
            .'<p class="note">Absents au poste (tableau 13)</p>'.$agents($p['absents_au_poste'], ['Nom', 'Fonction'])
            .'<p class="note">Décédés (tableau 14)</p>'.$agents($p['decedes'], ['Nom', 'Fonction'])
            .'<p class="note">Admis à la retraite dans l\'année (tableau 15)</p>'.$agents($p['a_la_retraite'], ['Nom', 'Fonction']);
    }

    private function sectionRatio(array $d): string
    {
        $lignes = array_map(fn (array $l) => [$l['classe'], $l['effectif'], $l['enseignants'], $l['ratio'] ?? '—'], $d['ratio_eleve_maitre']);

        return '<h2 class="section">III.1 Ratio élève / maître (tableau 16)</h2>'.$this->tableau(['Classe', 'Effectif', 'Enseignants', 'Ratio'], $lignes)
            .'<p class="note">Assiduité des enseignants (tableau 17) : non suivie par le système, à compléter manuellement.</p>';
    }

    private function sectionInfrastructures(array $d): string
    {
        $infra = $d['infrastructures'];

        $grille = function (array $g, string $titre) {
            $lignes = [];
            foreach (self::LIBELLES_MATERIAU as $mat => $labelMat) {
                foreach (self::LIBELLES_ETAT as $etat => $labelEtat) {
                    $q = $g[$mat][$etat] ?? 0;
                    if ($q > 0) {
                        $lignes[] = [$labelMat, $labelEtat, $q];
                    }
                }
            }

            return '<p class="note">'.$titre.'</p>'.$this->tableau(['Matériau', 'État', 'Quantité'], $lignes, 'Aucune infrastructure de ce type.');
        };

        $autres = collect($infra['autres'])->filter(fn ($q) => $q > 0)
            ->map(fn ($q, $type) => [self::LIBELLES_TYPE_INFRA[$type] ?? $type, $q])->values()->all();

        $equipements = $infra['equipements']->map(fn (EquipementMobilier $e) => [$e->nature, $e->quantite, $e->besoin_quantite ?? '—'])->all();

        return '<h2 class="section">IV. Infrastructures, équipements et mobilier (tableaux 18-20)</h2>'
            .$grille($infra['salles_classe'], 'Salles de classe')
            .$grille($infra['bloc_administratif'], 'Bloc administratif')
            .'<p class="note">Autres infrastructures</p>'.$this->tableau(['Nature', 'Quantité'], $autres)
            .'<p class="note">Équipements et mobilier — besoins</p>'.$this->tableau(['Nature', 'Quantité', 'Besoin'], $equipements);
    }

    private function sectionBudget(array $d): string
    {
        $lignes = array_map(fn (array $l) => [
            self::LIBELLES_RUBRIQUE_BUDGET[$l['rubrique']] ?? $l['rubrique'],
            number_format($l['montant_percu'], 0, ',', ' '),
            number_format($l['montant_depense'], 0, ',', ' '),
            number_format($l['reste'], 0, ',', ' '),
        ], $d['budget_fonctionnement']);

        return '<h2 class="section">V. Budget de fonctionnement (tableau 21)</h2>'.$this->tableau(['Rubrique', 'Perçu', 'Dépensé', 'Reste'], $lignes);
    }

    private function sectionVisites(array $d): string
    {
        $lignes = $d['visites_autorites']->map(fn (VisiteAutorite $v) => [
            $v->date_visite?->format('d/m/Y'), $v->qualite_autorite, $v->nature_visite, $v->objectifs, $v->observations,
        ])->all();

        return '<h2 class="section">VI. Visites des autorités (tableau 22)</h2>'
            .$this->tableau(['Date', 'Autorité', 'Nature', 'Objectifs', 'Observations'], $lignes);
    }

    private function sectionActivites(array $d): string
    {
        $pedagogiques = $d['activites_pedagogiques']->map(fn (ActiviteRentree $a) => [$a->activite, $a->prevues, $a->faites, $a->taux_affichage !== null ? $a->taux_affichage.'%' : '—'])->all();
        $eps = $d['activites_eps']->map(fn (ActiviteRentree $a) => [$a->activite, $a->objectifs_vises, $a->taux_affichage !== null ? $a->taux_affichage.'%' : '—'])->all();
        $fenassco = $d['activites_fenassco']->map(fn (ActiviteRentree $a) => [$a->activite, $a->objectifs_vises, $a->taux_affichage !== null ? $a->taux_affichage.'%' : '—'])->all();

        return '<h2 class="section">VII. Activités pédagogiques, EPS et FENASSCO (tableaux 23-25)</h2>'
            .'<p class="note">Pédagogiques</p>'.$this->tableau(['Activité', 'Prévues', 'Faites', '%'], $pedagogiques)
            .'<p class="note">EPS</p>'.$this->tableau(['Activité', 'Objectifs visés', '%'], $eps)
            .'<p class="note">FENASSCO</p>'.$this->tableau(['Activité', 'Objectifs visés', '%'], $fenassco);
    }

    private function sectionAssurances(array $d): string
    {
        $lignes = $d['assurances_scolaires']->map(fn (AssuranceScolaire $a) => [$a->libelle, $a->effectif, $a->nom_assureur, $a->numero_police])->all();

        return '<h2 class="section">VIII. Assurance scolaire (tableau 26)</h2>'.$this->tableau(['Niveau', 'Effectif', 'Assureur', 'N° de police'], $lignes);
    }

    private function sectionSecurite(array $d): string
    {
        $t = $d['textes'];
        $rubriques = ['securite_cloture', 'securite_detecteur_metaux', 'securite_controle_armes', 'securite_surveillance_pauses', 'securite_autres_mesures'];
        $lignes = array_map(fn (string $r) => [self::LIBELLES_TEXTE[$r], $t[$r] ?? '—'], $rubriques);

        return '<h2 class="section">IX. Sécurité</h2>'.$this->tableau(['Mesure', 'Description'], $lignes)
            .'<p class="note">Protocole anti-COVID (tableau 27) : rubrique obsolète, non reprise.</p>';
    }

    private function sectionVentes(array $d): string
    {
        $lignes = $d['ventes_denrees']->map(fn (VenteDenree $v) => [
            $v->nature, $v->vendeur_nom, $v->dossier_medical_ok === null ? '—' : ($v->dossier_medical_ok ? 'Oui' : 'Non'),
            number_format($v->frais_verses, 0, ',', ' '), $v->gestion_frais,
        ])->all();

        return '<h2 class="section">X. Santé et hygiène — vente de denrées alimentaires (tableau 28)</h2>'
            .$this->tableau(['Denrée', 'Vendeur', 'Dossier médical', 'Frais versés', 'Gestion des frais'], $lignes);
    }

    private function sectionConseilApee(array $d): string
    {
        /** @var ConseilEcole $c */
        $c = $d['conseil_ecole'];
        /** @var Apee $a */
        $a = $d['apee'];

        $html = '<h2 class="section">XI. Conseil d\'école et APEE (tableaux 29-30)</h2>';

        $html .= '<p class="note">Conseil d\'école</p>';
        $html .= '<div class="champ"><b>Existe :</b> '.($c->existe ? 'Oui' : 'Non').'</div>';
        $html .= '<div class="champ"><b>Président :</b> '.$this->e($c->president_nom ?? '—').'</div>';
        $html .= '<div class="champ"><b>Fonction :</b> '.$this->e($c->president_fonction ?? '—').'</div>';
        $html .= '<div class="champ"><b>Téléphone :</b> '.$this->e($c->president_telephone ?? '—').'</div>';
        $html .= '<div class="champ"><b>Durée du mandat :</b> '.$this->e($c->duree_mandat ?? '—').'</div>';
        $html .= '<div class="champ"><b>Fin du mandat :</b> '.$this->e((string) ($c->fin_mandat ?? '—')).'</div>';

        $html .= '<p class="note">APEE</p>';
        $html .= '<div class="champ"><b>Légalisée :</b> '.($a->legalisee ? 'Oui' : 'Non').'</div>';
        $html .= '<div class="champ"><b>Président :</b> '.$this->e($a->president_nom ?? '—').'</div>';
        $html .= '<div class="champ"><b>Montant perçu :</b> '.number_format($a->montant_percu ?? 0, 0, ',', ' ').' FCFA</div>';
        $html .= '<div class="champ"><b>Montant dépensé :</b> '.number_format($a->montant_depense ?? 0, 0, ',', ' ').' FCFA</div>';
        $html .= '<div class="champ"><b>Solde restant :</b> '.number_format(($a->montant_percu ?? 0) - ($a->montant_depense ?? 0), 0, ',', ' ').' FCFA</div>';

        return $html;
    }

    private function sectionTextesLibres(array $d): string
    {
        $t = $d['textes'];
        $rubriques = [
            'gouvernements_enfants', 'irr', 'evenements_socioculturels', 'fetes_nationales',
            'probleme_infrastructure_maternelle', 'problemes_fonctionnement', 'resolutions_conseil_maitres',
            'doleances', 'conclusion_generale',
        ];

        $html = '<h2 class="section">XII. Autres rubriques</h2>';
        foreach ($rubriques as $r) {
            $html .= '<p style="font-size:2.6mm;"><b>'.$this->e(self::LIBELLES_TEXTE[$r]).' :</b> '.nl2br($this->e($t[$r] ?? '—')).'</p>';
        }

        return $html;
    }
}
