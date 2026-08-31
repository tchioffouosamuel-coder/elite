<?php

namespace App\Support\Word;

use App\Models\Apee;
use App\Models\AssuranceScolaire;
use App\Models\ActiviteRentree;
use App\Models\ConseilEcole;
use App\Models\EquipementMobilier;
use App\Models\Personnel;
use App\Models\School;
use App\Models\VenteDenree;
use App\Models\VisiteAutorite;
use App\Services\VisaComposeService;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Rapport de rentrée scolaire, canevas MINEDUB — version .docx fidèle au
 * gabarit papier déposé à la délégation (numérotation « Tableau N°X »,
 * en-tête bilingue, pied de page administratif), à la différence du PDF
 * (`RapportRentreeGenerator`) qui reformate le même contenu en rubriques
 * synthétiques.
 *
 * Consomme le même tableau `$d` que `RapportRentreeGenerator::build()`,
 * produit par `RapportRentreeService::generer()` — aucune donnée
 * recalculée en double entre les deux générateurs.
 *
 * Quelques tableaux du canevas papier n'ont pas d'équivalent dans le
 * système (évolution des effectifs sur 5 ans, taux de fréquentation,
 * assiduité des enseignants, protocole anti-COVID) : imprimés comme des
 * rubriques « à compléter manuellement », comme le fait déjà le PDF. La
 * ventilation des tableaux 3, 4, 6 et 8 par nationalité/statut de réfugié
 * n'est pas toujours disponible à ce niveau de détail (le système ne
 * suit pas, p. ex., les sans-acte-de-naissance par nationalité) : ces
 * tableaux reprennent alors la seule ventilation que la base connaît.
 */
class RapportRentreeWordGenerator
{
    private const BRUN_FOOTER = 'B5651D';

    private const RUBRIQUES_BUDGET = [
        'primes_rendement' => 'Primes de rendement',
        'projet_ecole' => "Projet d'école",
        'fenassco' => 'FENASSCO',
        'fonctionnement' => 'Fonctionnement',
        'evaluation' => 'Évaluation',
    ];

    private const TYPES_INFRA = [
        'wc' => 'WC', 'cloture' => 'Clôture', 'point_eau' => "Point d'eau",
        'electricite' => 'Électricité', 'aire_jeu' => 'Aire de jeu', 'logement_maitre' => 'Logement maître', 'autre' => 'Autre',
    ];

    private const MATERIAUX = ['dur' => 'Dur', 'semi_dur' => 'Semi-dur', 'provisoire' => 'Matériaux provisoires'];

    private const ETATS = ['bon' => 'Bon', 'assez_bon' => 'Assez-bon', 'mauvais' => 'Mauvais'];

    private const LIBELLES_TEXTE = [
        'securite_cloture' => 'Clôture',
        'securite_detecteur_metaux' => 'Détecteur de métaux',
        'securite_controle_armes' => 'Contrôle des armes blanches',
        'securite_surveillance_pauses' => 'Surveillance des pauses',
        'securite_autres_mesures' => 'Autres mesures',
        'probleme_infrastructure_maternelle' => "Problèmes d'infrastructure",
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

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'marginTop' => 1000, 'marginBottom' => 1000, 'marginLeft' => 1000, 'marginRight' => 1000,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);
        $this->ajouterPiedDePage($section, $school);

        $this->blocTitre($section, $d);
        $this->sectionIdentite($section, $d);
        $this->sectionEffectifs($section, $d);
        $this->sectionMinorites($section, $d);
        $this->sectionAges($section, $d);
        $this->sectionPersonnel($section, $d);
        $this->sectionInfrastructures($section, $d);
        $this->sectionBudget($section, $d);
        $this->sectionVisites($section, $d);
        $this->sectionRegimeParticulier($section, $d);
        $this->sectionActivitesPedagogiques($section, $d);
        $this->sectionPostPeriscolaire($section, $d);

        $this->signature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/rapport-rentree-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    // -- Structure --------------------------------------------------

    private function blocTitre(Section $section, array $d): void
    {
        $school = $d['school'];
        $annee = $d['annee'];

        $table = $section->addTable(['borderSize' => 10, 'borderColor' => '000000', 'cellMargin' => 200, 'alignment' => JcTable::CENTER, 'width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();
        $cellule = $table->addCell(null, ['valign' => 'center']);

        $cellule->addText('RAPPORT DE LA RENTRÉE SCOLAIRE', ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $cellule->addText('DE '.mb_strtoupper((string) $school->name), ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $cellule->addText('ANNÉE SCOLAIRE '.($annee->libelle ?? ''), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $section->addTextBreak(1);

        $identite = $d['identite'];
        $presente = $section->addTextRun(['alignment' => Jc::END, 'spaceAfter' => 60]);
        $presente->addText('Présenté par : ', ['italic' => true]);
        $presente->addText($identite['directeur_nom'] ?? '……………………………………', ['italic' => true, 'bold' => true]);

        $section->addTextBreak(1);
    }

    private function sectionIdentite(Section $section, array $d): void
    {
        $this->titreRomain($section, 'I. PRÉSENTATION DE L\'ÉCOLE');

        $i = $d['identite'];
        $school = $d['school'];

        $lignes = [
            ['Arrondissement', $i['arrondissement']],
            ["Nom de l'école", $school->name],
            ['Secteur (Privé ou Public)', $i['secteur']],
            ['Cycle (Complet ou Incomplet)', $i['cycle']],
            ['Mode de fonctionnement', $i['mode_fonctionnement']],
            ['Adresse', $school->address],
            ['Téléphone', $school->phone],
            ['E-mail', $school->email],
            ['Année de création', $i['annee_creation']],
            ["Année d'ouverture", $i['annee_ouverture']],
            ["Arrêté d'ouverture", $i['numero_arrete_ouverture']],
            ["Autorisation d'ouverture", $i['numero_autorisation_ouverture']],
            ['Fondateur', trim(($i['fondateur_nom'] ?? '').(($i['fondateur_contact'] ?? null) ? ' — '.$i['fondateur_contact'] : '')) ?: null],
            ['Directeur', trim(($i['directeur_nom'] ?? '').(($i['directeur_contact'] ?? null) ? ' — '.$i['directeur_contact'] : '')) ?: null],
        ];

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        foreach ($lignes as [$label, $valeur]) {
            $table->addRow();
            $table->addCell(3500)->addText($label.' :', ['bold' => true, 'size' => 9]);
            $table->addCell(6500)->addText($valeur !== null && $valeur !== '' ? (string) $valeur : '—', ['bold' => true, 'size' => 9]);
        }

        $section->addTextBreak(1);
    }

    private function sectionEffectifs(Section $section, array $d): void
    {
        $this->titreRomain($section, "II. ACTIVITÉS ADMINISTRATIVES");
        $this->titreSous($section, '1- Les élèves');

        $lignes = [];
        $tCam = ['garcons' => 0, 'filles' => 0];
        $tNonCam = ['garcons' => 0, 'filles' => 0];
        $tG = 0;
        $tF = 0;

        foreach ($d['effectifs_par_classe'] as $l) {
            $lignes[] = [$l['classe']['nom'], $l['garcons']['total'], $l['filles']['total'], $l['total']['total']];
            $tCam['garcons'] += $l['garcons']['camerounais'];
            $tCam['filles'] += $l['filles']['camerounais'];
            $tNonCam['garcons'] += $l['garcons']['non_camerounais'];
            $tNonCam['filles'] += $l['filles']['non_camerounais'];
            $tG += $l['garcons']['total'];
            $tF += $l['filles']['total'];
        }
        if ($lignes !== []) {
            $lignes[] = ['TOTAL', $tG, $tF, $tG + $tF];
        }

        $this->titreTableau($section, 'Tableau N°1', "Effectif désagrégés des élèves en début d'année");
        $this->tableau($section, ['Classes', 'G', 'F', 'T'], $lignes);

        $this->titreTableau($section, 'Tableau N°2', "Evolution des effectifs de l'école pour les 05 dernières années");
        $this->noteManuelle($section, "Non suivi par le système : à compléter manuellement à partir des rapports des années précédentes.");

        $this->titreTableau($section, 'Tableau N°3', 'Effectifs des élèves non camerounais');
        $this->tableau($section, ['Nationalité', 'G', 'F', 'T'], [
            ['Camerounais', $tCam['garcons'], $tCam['filles'], $tCam['garcons'] + $tCam['filles']],
            ['Non camerounais (réfugiés)', $tNonCam['garcons'], $tNonCam['filles'], $tNonCam['garcons'] + $tNonCam['filles']],
            ['TOTAL', $tG, $tF, $tG + $tF],
        ]);

        $this->titreTableau($section, 'Tableau N°4', 'Effectifs désagrégés des élèves réfugiés par cours');
        $lignesRefugies = array_map(fn (array $l) => [
            $l['classe']['nom'],
            $l['garcons']['non_camerounais'], $l['filles']['non_camerounais'], $l['garcons']['non_camerounais'] + $l['filles']['non_camerounais'],
        ], $d['effectifs_par_classe']);
        $this->tableau($section, ['Cours', 'G', 'F', 'T'], $lignesRefugies, 'Aucun élève réfugié enregistré.');
    }

    private function sectionMinorites(Section $section, array $d): void
    {
        $m = $d['minorites'];

        $this->titreTableau($section, 'Tableau N°5', 'Effectifs des minorités');
        $this->tableau($section, ['Minorité', 'G', 'F', 'T'], [
            ['Bororo', $m['bororo']['garcons'], $m['bororo']['filles'], $m['bororo']['total']],
            ['Baka', $m['baka']['garcons'], $m['baka']['filles'], $m['baka']['total']],
            ['Déplacés internes', $m['deplaces_internes']['garcons'], $m['deplaces_internes']['filles'], $m['deplaces_internes']['total']],
            ['TOTAL', $m['total']['garcons'], $m['total']['filles'], $m['total']['total']],
        ]);

        $this->titreTableau($section, 'Tableau N°6', "Effectifs des élèves sans acte de naissance");
        $lignes = array_map(fn (array $l) => [
            $l['classe']['nom'],
            $l['garcons']['sans_acte_naissance'], $l['filles']['sans_acte_naissance'],
            $l['garcons']['sans_acte_naissance'] + $l['filles']['sans_acte_naissance'],
        ], $d['effectifs_par_classe']);
        $this->tableau($section, ['Cours', 'G', 'F', 'T'], $lignes, 'Aucun élève sans acte de naissance.');
    }

    private function sectionAges(Section $section, array $d): void
    {
        $this->titreTableau($section, 'Tableau N°7', 'Effectifs désagrégés des élèves par âge');
        $lignes = array_map(fn (array $l) => [$l['age'].' ans', $l['garcons'], $l['filles'], $l['total']], $d['pyramide_ages']);
        $this->tableau($section, ['Âge', 'G', 'F', 'T'], $lignes);

        $this->titreTableau($section, 'Tableau N°8', 'Effectif désagrégés des redoublants à l\'école');
        $lignes = array_map(fn (array $l) => [
            $l['classe']['nom'], $l['garcons']['redoublants'], $l['filles']['redoublants'],
            $l['garcons']['redoublants'] + $l['filles']['redoublants'],
        ], $d['effectifs_par_classe']);
        $this->tableau($section, ['Cours', 'G', 'F', 'T'], $lignes, 'Aucun redoublant.');

        $this->titreTableau($section, 'Tableau N°9', 'Taux de fréquentation');
        $this->noteManuelle($section, "Non suivi par le système : à compléter manuellement (relevé de présence journalier).");
    }

    private function sectionPersonnel(Section $section, array $d): void
    {
        $this->titreSous($section, "2- Le personnel enseignant et d'appui");
        $p = $d['personnel'];

        $this->titreTableau($section, 'Tableau N°10-12', 'Le personnel enseignant par grade et par sexe');
        $ventilation = collect($p['par_grade'])->map(fn ($n, $k) => [$k, $n])->values()->all();
        $this->tableau($section, ['Grade', 'Effectif'], $ventilation, 'Aucune donnée.');

        $this->titreTableau($section, 'Tableau N°11', 'Les instituteurs contractualisés par grade et par sexe');
        $parContrat = collect($p['par_contrat'])->map(fn ($n, $k) => [$k, $n])->values()->all();
        $this->tableau($section, ['Type de contrat', 'Effectif'], $parContrat, 'Aucune donnée.');

        $agents = fn (Collection $c) => $this->tableau($section, ['Nom', 'Fonction'], $c->map(fn (Personnel $a) => [$a->nom_complet, $a->fonction])->all(), 'Aucun agent concerné.');

        $this->titreTableau($section, 'Tableau N°13', 'Enseignants absents au poste');
        $agents($p['absents_au_poste']);

        $this->titreTableau($section, 'Tableau N°14', 'Enseignants décédés');
        $agents($p['decedes']);

        $this->titreTableau($section, 'Tableau N°15', "Personnels admis à faire valoir leur droit à la retraite en fin d'année");
        $agents($p['a_la_retraite']);

        $this->titreTableau($section, 'Tableau N°16', 'Ratio élève / maître');
        $lignesRatio = array_map(fn (array $l) => [$l['classe'], $l['effectif'], $l['enseignants'], $l['ratio'] ?? '—'], $d['ratio_eleve_maitre']);
        $this->tableau($section, ['Classe', 'Effectif', 'Enseignants', 'Ratio'], $lignesRatio);

        $this->titreTableau($section, 'Tableau N°17', "Taux d'assiduité des enseignants");
        $this->noteManuelle($section, 'Non suivi par le système : à compléter manuellement.');
    }

    private function sectionInfrastructures(Section $section, array $d): void
    {
        $this->titreRomain($section, '3. INFRASTRUCTURES, ÉQUIPEMENTS ET MOBILIER DE L\'ÉCOLE');
        $infra = $d['infrastructures'];

        $grille = function (array $g, string $titre) use ($section) {
            $lignes = [];
            foreach (self::MATERIAUX as $mat => $labelMat) {
                foreach (self::ETATS as $etat => $labelEtat) {
                    $q = $g[$mat][$etat] ?? 0;
                    if ($q > 0) {
                        $lignes[] = [$labelMat, $labelEtat, $q];
                    }
                }
            }
            $section->addText($titre, ['italic' => true, 'size' => 9], ['spaceAfter' => 40]);
            $this->tableau($section, ['Matériau', 'État', 'Quantité'], $lignes, 'Aucune infrastructure de ce type.');
        };

        $this->titreTableau($section, 'Tableau N°18', 'Infrastructures et nombre de salles de classe');
        $grille($infra['salles_classe'], 'Salles de classe');
        $grille($infra['bloc_administratif'], 'Bloc administratif');
        $autres = collect($infra['autres'])->filter(fn ($q) => $q > 0)->map(fn ($q, $type) => [self::TYPES_INFRA[$type] ?? $type, $q])->values()->all();
        $section->addText('Autres infrastructures', ['italic' => true, 'size' => 9], ['spaceAfter' => 40]);
        $this->tableau($section, ['Nature', 'Quantité'], $autres, 'Aucune.');

        $this->titreTableau($section, 'Tableau N°19', 'Équipements et mobilier');
        $equipements = $infra['equipements']->map(fn (EquipementMobilier $e) => [$e->nature, $e->quantite])->all();
        $this->tableau($section, ['Nature', 'Quantité'], $equipements);

        $this->titreTableau($section, 'Tableau N°20', 'Besoins en enseignants, infrastructures, équipements et mobiliers');
        $besoins = $infra['equipements']->filter(fn (EquipementMobilier $e) => ($e->besoin_quantite ?? 0) > 0)
            ->map(fn (EquipementMobilier $e) => [$e->nature, $e->besoin_quantite])->all();
        $this->tableau($section, ['Nature', 'Besoin'], $besoins, 'Aucun besoin enregistré (enseignants : non suivi par le système).');
    }

    private function sectionBudget(Section $section, array $d): void
    {
        $this->titreRomain($section, '4. LES FINANCES');
        $this->titreTableau($section, 'Tableau N°21', 'Gestion du budget de fonctionnement');

        $lignes = array_map(fn (array $l) => [
            self::RUBRIQUES_BUDGET[$l['rubrique']] ?? $l['rubrique'],
            number_format($l['montant_percu'], 0, ',', ' '),
            number_format($l['montant_depense'], 0, ',', ' '),
            number_format($l['reste'], 0, ',', ' '),
        ], $d['budget_fonctionnement']);
        $this->tableau($section, ['Rubriques', 'Montant perçu', 'Montant dépensé', 'Reste'], $lignes);
    }

    private function sectionVisites(Section $section, array $d): void
    {
        $this->titreRomain($section, '5. VISITE DES AUTORITÉS ADMINISTRATIVES, PÉDAGOGIQUES ET AUTRES PERSONNALITÉS');
        $this->titreTableau($section, 'Tableau N°22', 'Visite des autorités administratives, pédagogiques et autres personnalités');

        $lignes = $d['visites_autorites']->map(fn (VisiteAutorite $v) => [
            $v->date_visite?->format('d/m/Y'), $v->qualite_autorite, $v->nature_visite, $v->objectifs, $v->observations,
        ])->all();
        $this->tableau($section, ['Date', 'Qualité', 'Nature', 'Objectifs', 'Observations'], $lignes);
    }

    private function sectionRegimeParticulier(Section $section, array $d): void
    {
        $secteurPrive = str_contains(mb_strtolower((string) $d['identite']['secteur']), 'priv');
        $ras = $secteurPrive ? 'RAS (établissement privé).' : null;

        $this->titreRomain($section, '6. LA SITUATION DU PAQUET MINIMUM REÇU');
        $section->addText($ras ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);

        $this->titreRomain($section, '7. LA SITUATION DES FRAIS DES EXAMENS OFFICIELS');
        $section->addText($ras ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);

        $t = $d['textes'];
        $this->titreRomain($section, "8. LES PROBLÈMES RENCONTRÉS DANS LE FONCTIONNEMENT DE L'ÉCOLE");
        $section->addText($t['problemes_fonctionnement'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);

        $this->titreRomain($section, '9. LES RÉSOLUTIONS PRISES LORS DES CONSEILS DES MAÎTRES');
        $section->addText($t['resolutions_conseil_maitres'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);
    }

    private function sectionActivitesPedagogiques(Section $section, array $d): void
    {
        $this->titreRomain($section, 'III. ACTIVITÉS PÉDAGOGIQUES');
        $this->titreTableau($section, 'Tableau N°23', "Programmation et taux d'exécution");

        $lignes = $d['activites_pedagogiques']->map(fn (ActiviteRentree $a) => [
            $a->activite, $a->prevues, $a->faites, $a->taux_affichage !== null ? $a->taux_affichage.'%' : '—',
        ])->all();
        $this->tableau($section, ['Activités', 'Prévues', 'Faites', '%'], $lignes);
    }

    private function sectionPostPeriscolaire(Section $section, array $d): void
    {
        $this->titreRomain($section, 'IV. ACTIVITÉS POST ET PÉRISCOLAIRES');

        $this->titreSous($section, '1- Éducation physique et sportive (EPS)');
        $this->titreTableau($section, 'Tableau N°24', "Programmation de l'Éducation Physique et Sportive");
        $eps = $d['activites_eps']->map(fn (ActiviteRentree $a) => [$a->activite, $a->objectifs_vises, $a->taux_affichage !== null ? $a->taux_affichage.'%' : '—'])->all();
        $this->tableau($section, ['Activités', 'Objectifs visés', '%'], $eps);

        $this->titreTableau($section, 'Tableau N°25', 'Les activités de la FENASSCO');
        $fenassco = $d['activites_fenassco']->map(fn (ActiviteRentree $a) => [$a->activite, $a->objectifs_vises, $a->taux_affichage !== null ? $a->taux_affichage.'%' : '—'])->all();
        $this->tableau($section, ['Activités', 'Objectifs visés', '%'], $fenassco);

        $this->titreTableau($section, 'Tableau N°26', 'Assurance scolaire');
        $assurances = $d['assurances_scolaires']->map(fn (AssuranceScolaire $a) => [$a->libelle, $a->effectif, $a->nom_assureur, $a->numero_police])->all();
        $this->tableau($section, ['Classes', 'Effectifs', 'Nom de l\'assureur', 'N° de police'], $assurances);

        $this->titreSous($section, '2- La sécurité');
        $t = $d['textes'];
        $securite = array_map(fn (string $r) => [self::LIBELLES_TEXTE[$r], $t[$r] ?? '—'], [
            'securite_cloture', 'securite_detecteur_metaux', 'securite_controle_armes', 'securite_surveillance_pauses', 'securite_autres_mesures',
        ]);
        $this->tableau($section, ['Mesure', 'Description'], $securite);

        $this->titreSous($section, '3- Santé et hygiène');
        $this->titreTableau($section, 'Tableau N°27', 'Le protocole anti-COVID 19');
        $this->noteManuelle($section, 'Rubrique obsolète : non reprise.');

        $this->titreTableau($section, 'Tableau N°28', 'La vente des denrées alimentaires');
        $ventes = $d['ventes_denrees']->map(fn (VenteDenree $v) => [
            $v->nature, $v->vendeur_nom, $v->dossier_medical_ok === null ? '—' : ($v->dossier_medical_ok ? 'Oui' : 'Non'),
            number_format($v->frais_verses, 0, ',', ' '), $v->gestion_frais,
        ])->all();
        $this->tableau($section, ['Denrée', 'Vendeur', 'Dossier médical', 'Frais versés', 'Gestion des frais'], $ventes);

        $this->titreSous($section, "4- Les conseils d'école");
        $this->titreTableau($section, 'Tableau N°29', "Fonctionnement du conseil d'école");
        /** @var ConseilEcole $c */
        $c = $d['conseil_ecole'];
        $this->tableau($section, ['Nom de l\'école', 'Statut du conseil', 'Date de la dernière AG élective', 'Mandat du bureau actuel', 'Fin du mandat'], [[
            $d['school']->name, $c->existe ? 'Oui' : 'Non', (string) ($c->date_derniere_ag ?? '—'), $c->duree_mandat ?? '—', (string) ($c->fin_mandat ?? '—'),
        ]]);
        $this->tableau($section, ['Nom du président', 'Fonction civile', 'N° de téléphone'], [[
            $c->president_nom ?? '—', $c->president_fonction ?? '—', $c->president_telephone ?? '—',
        ]]);

        $this->titreTableau($section, 'Tableau N°30', 'La situation des APEE');
        /** @var Apee $a */
        $a = $d['apee'];
        $this->tableau($section, ['Nom de l\'école', 'Statut APEE', 'Montant perçu', 'Montant dépensé', 'Solde'], [[
            $d['school']->name,
            $a->legalisee ? 'Légalisée' : 'Non légalisée',
            number_format($a->montant_percu ?? 0, 0, ',', ' ').' FCFA',
            number_format($a->montant_depense ?? 0, 0, ',', ' ').' FCFA',
            number_format(($a->montant_percu ?? 0) - ($a->montant_depense ?? 0), 0, ',', ' ').' FCFA',
        ]]);
        $this->tableau($section, ['Nom du président', 'N° de téléphone'], [[$a->president_nom ?? '—', $a->president_telephone ?? '—']]);

        $this->titreSous($section, "5- Les gouvernements d'enfants");
        $section->addText($t['gouvernements_enfants'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);

        $this->titreSous($section, '6- Les IRR');
        $section->addText($t['irr'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);

        $this->titreSous($section, '7- Événements socio-culturels');
        $section->addText($t['evenements_socioculturels'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 120]);

        $this->titreSous($section, '8- Les fêtes nationales (célébration des journées)');
        $section->addText($t['fetes_nationales'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 240]);

        $section->addTextRun(['spaceAfter' => 120])->addText('CONCLUSION GÉNÉRALE :', ['bold' => true, 'underline' => 'single']);
        $section->addText($t['conclusion_generale'] ?? '……………………………………', ['size' => 10], ['spaceAfter' => 0]);
    }

    // -- Signature ----------------------------------------------------

    private function signature(Section $section, School $school): void
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        $section->addTextBreak(1);
        $ligne = $section->addTextRun(['spaceAfter' => 240]);
        $ligne->addText('Fait à '.($ville !== '' ? $ville : '…………').', le '.now()->format('d/m/Y'));

        $titre = $section->addTextRun(['alignment' => Jc::END, 'spaceAfter' => 0]);
        $titre->addText('Le/La Chef d\'établissement', ['bold' => true]);

        $visa = (new VisaComposeService)->chemin($school);
        if ($visa !== null) {
            $section->addImage($visa, ['height' => 50, 'alignment' => Jc::END]);
        } else {
            $section->addTextBreak(3);
        }
    }

    // -- Pied de page ---------------------------------------------------

    private function ajouterPiedDePage(Section $section, School $school): void
    {
        $footer = $section->addFooter();
        $table = $footer->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();

        $nomEspace = implode(' ', preg_split('//u', mb_strtoupper((string) $school->name), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $gauche = $table->addCell(8000, ['bgColor' => self::BRUN_FOOTER, 'valign' => 'center']);
        $gauche->addText($nomEspace, ['bold' => true, 'color' => 'FFFFFF', 'size' => 8]);

        $droite = $table->addCell(1500, ['bgColor' => self::BRUN_FOOTER, 'valign' => 'center']);
        $droite->addPreserveText('{PAGE}', ['bold' => true, 'color' => 'FFFFFF', 'size' => 9], ['alignment' => Jc::END]);
    }

    // -- Primitives -----------------------------------------------------

    private function titreRomain(Section $section, string $texte): void
    {
        $section->addText($texte, ['bold' => true, 'underline' => 'single', 'size' => 11], ['spaceBefore' => 200, 'spaceAfter' => 100]);
    }

    private function titreSous(Section $section, string $texte): void
    {
        $section->addText($texte, ['bold' => true, 'underline' => 'single', 'size' => 10], ['spaceBefore' => 160, 'spaceAfter' => 80]);
    }

    private function titreTableau(Section $section, string $numero, string $titre): void
    {
        $ligne = $section->addTextRun(['spaceBefore' => 120, 'spaceAfter' => 40]);
        $ligne->addText($numero, ['bold' => true, 'underline' => 'single', 'size' => 10]);
        $ligne->addText(' : '.$titre, ['bold' => true, 'size' => 10]);
    }

    private function noteManuelle(Section $section, string $texte): void
    {
        $section->addText($texte, ['italic' => true, 'size' => 8, 'color' => '777777'], ['spaceAfter' => 120]);
    }

    /** Tableau générique du canevas : bordures noires, en-tête gras sans fond coloré. */
    private function tableau(Section $section, array $entetes, array $lignes, ?string $vide = null): void
    {
        if (count($lignes) === 0) {
            $this->noteManuelle($section, $vide ?? 'Aucune donnée enregistrée.');

            return;
        }

        $table = $section->addTable([
            'borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60,
            'width' => 100 * 50, 'unit' => 'pct',
        ]);

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($entetes as $entete) {
            $table->addCell(null)->addText((string) $entete, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }

        foreach ($lignes as $ligne) {
            $table->addRow();
            foreach ($ligne as $cellule) {
                $table->addCell(null)->addText(
                    $cellule !== null && $cellule !== '' ? (string) $cellule : '—',
                    ['size' => 9],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
                );
            }
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }
}
