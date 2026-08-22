<?php

namespace App\Imports;

use App\Models\ClasseMatiere;
use App\Models\ProgressionItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import de la fiche de progression de l'établissement.
 *
 * Le classeur (« Mr/Mrs XX Subject XX progression sheet class-XX ») porte ses
 * en-têtes en ligne 6 : les cinq premières lignes sont un cartouche (école,
 * classe, matière, enseignant, horaires) que l'application connaît déjà par
 * l'affectation visée. Chaque ligne suivante est une leçon.
 *
 * L'import COMPLÈTE, il n'écrase pas : une leçon déjà présente — reconnue à son
 * couple Topic + Lesson — ne voit remplir que ses champs restés vides. Un
 * enseignant qui a commencé sa saisie à l'écran ne la perd donc pas en
 * important le fichier de son collègue, ce qui serait le pire moment pour
 * découvrir la règle.
 */
class ProgressionImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    /**
     * En-tête du fichier (normalisé) => champ du modèle.
     *
     * Les libellés viennent de la feuille réelle, apostrophes typographiques
     * comprises ; la normalisation par `cle()` les ramène à des lettres, si
     * bien qu'une variante de casse ou de ponctuation tombe au même endroit.
     */
    private const COLONNES = [
        'term' => 'term',
        'month' => 'mois',
        'mois' => 'mois',
        'week' => 'semaine',
        'semaine' => 'semaine',
        'dates' => 'date_prevue',
        'date' => 'date_prevue',
        'expectedlearningoutcomes' => 'expected_learning_outcomes',
        'topic' => 'topic',
        'lesson' => 'lesson',
        'competence' => 'competence',
        'digitalpracticalnormal' => 'mode',
        'stagesofthelesson' => 'stages_of_lesson',
        'entrybehaviour' => 'entry_behaviour',
        'teachingaids' => 'teaching_aids',
        'teachinglearningstrategies' => 'teaching_learning_strategies',
        'references' => 'references',
        'stageintroduction' => 'introduction',
        'stagepresentation' => 'presentation',
        'stageconclusion' => 'conclusion',
        'mainpointsofmatter' => 'main_points',
        'learnersactivities' => 'learners_activities',
        'facilitatorsactivities' => 'facilitators_activities',
        'reserchquestions' => 'research_questions',
        'researchquestions' => 'research_questions',
    ];

    /**
     * Nombre de lignes sans leçon qui marque la fin du tableau.
     *
     * Le gabarit se termine par une dizaine de lignes pré-formatées vides,
     * puis un pied de page de signatures — « The teacher », « The dean of
     * studies », « The head teacher » — dont les cellules tombent sous des
     * colonnes du tableau. Prises pour des données, elles créent une leçon
     * fantôme intitulée d'après un signataire. Le trou qui les précède est ce
     * qui les distingue : cinq lignes vides tolèrent qu'un enseignant en
     * saute quelques-unes, sans laisser passer le pied de page.
     *
     * C'est aussi pourquoi cet import n'implémente pas SkipsEmptyRows : sans
     * les lignes vides, le pied de page suivrait la dernière leçon sans trou
     * et redeviendrait indiscernable d'une donnée.
     */
    private const FIN_DE_TABLE = 5;

    public int $creees = 0;

    public int $completees = 0;

    public int $ignorees = 0;

    public function __construct(private readonly ClasseMatiere $classeMatiere) {}

    /**
     * Première feuille seulement : le classeur porte aussi « Subjects per
     * staff », un annuaire du personnel qui n'a rien d'une progression.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /** Les en-têtes utiles sont en ligne 6 ; au-dessus vit le cartouche. */
    public function headingRow(): int
    {
        return 6;
    }

    public function collection(Collection $rows): void
    {
        // Les leçons déjà en base, indexées par Topic + Lesson : c'est ce
        // couple qui identifie une ligne de la feuille, le titre libre de
        // l'application ne s'y prêtant pas.
        $existantes = ProgressionItem::where('classe_matiere_id', $this->classeMatiere->id)
            ->where('type', 'lecon')
            ->get()
            ->keyBy(fn (ProgressionItem $item) => self::cle($item->topic).'|'.self::cle($item->lesson));

        $ordre = (int) ProgressionItem::where('classe_matiere_id', $this->classeMatiere->id)->max('ordre');

        $vides = 0;

        foreach ($rows as $row) {
            $ligne = $this->canoniser($row instanceof Collection ? $row->all() : (array) $row);

            // Une ligne sans topic ni leçon est une ligne de garde, un total ou
            // une ligne vide pré-formatée : le gabarit en compte des dizaines.
            if (($ligne['topic'] ?? null) === null && ($ligne['lesson'] ?? null) === null) {
                $this->ignorees++;

                // Assez de vide d'affilée : le tableau est fini, ce qui suit
                // relève du pied de page.
                if (++$vides >= self::FIN_DE_TABLE) {
                    break;
                }

                continue;
            }

            $vides = 0;

            $cle = self::cle($ligne['topic'] ?? null).'|'.self::cle($ligne['lesson'] ?? null);
            $existante = $existantes->get($cle);

            if ($existante !== null) {
                $this->completer($existante, $ligne);

                continue;
            }

            $item = ProgressionItem::create([
                'classe_matiere_id' => $this->classeMatiere->id,
                'type' => 'lecon',
                // Le titre affiché dans la liste : la leçon quand elle est
                // nommée, le sujet à défaut — jamais vide, la colonne l'exige.
                'titre' => $ligne['lesson'] ?? $ligne['topic'],
                'ordre' => ++$ordre,
                ...$ligne,
            ]);

            $existantes->put($cle, $item);
            $this->creees++;
        }
    }

    /**
     * Complète une leçon existante sans écraser ce qui est déjà saisi.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function completer(ProgressionItem $item, array $ligne): void
    {
        $aRemplir = [];

        foreach ($ligne as $champ => $valeur) {
            $actuel = $item->{$champ};

            if ($valeur !== null && ($actuel === null || trim((string) $actuel) === '')) {
                $aRemplir[$champ] = $valeur;
            }
        }

        if ($aRemplir === []) {
            $this->ignorees++;

            return;
        }

        $item->update($aRemplir);
        $this->completees++;
    }

    /**
     * Traduit les en-têtes du fichier en champs du modèle, et normalise les
     * valeurs. Une colonne inconnue est écartée : le gabarit en porte
     * plusieurs — horaires, appel, visa — qui relèvent de la séance tenue et
     * non de sa préparation.
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function canoniser(array $ligne): array
    {
        $canonique = [];

        foreach ($ligne as $entete => $valeur) {
            $champ = self::COLONNES[self::cle($entete)] ?? null;

            if ($champ === null || isset($canonique[$champ])) {
                continue;
            }

            $valeur = $champ === 'date_prevue' ? self::date($valeur) : self::texte($valeur);

            if ($valeur !== null) {
                $canonique[$champ] = $champ === 'mode' ? self::mode($valeur) : $valeur;
            }
        }

        // Un mode illisible ne doit pas faire échouer la ligne entière : la
        // colonne est renseignée à la main, souvent en abrégé.
        if (($canonique['mode'] ?? false) === null) {
            unset($canonique['mode']);
        }

        return $canonique;
    }

    /** « Digital », « prat. », « N » : la colonne est saisie à la main. */
    private static function mode(string $valeur): ?string
    {
        $cle = self::cle($valeur);

        return match (true) {
            str_starts_with($cle, 'dig') || str_starts_with($cle, 'num') => 'digital',
            str_starts_with($cle, 'prat') || str_starts_with($cle, 'pract') => 'practical',
            str_starts_with($cle, 'norm') || $cle === 'n' => 'normal',
            default => null,
        };
    }

    private static function texte(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        // Les cellules de durée du gabarit arrivent en objets date : elles ne
        // concernent pas la préparation, mais mieux vaut un texte qu'une erreur.
        if ($valeur instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valeur)->format('H:i');
        }

        $texte = trim(preg_replace('/[ \t]+/u', ' ', (string) $valeur) ?? '');

        return $texte === '' ? null : $texte;
    }

    private static function date(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valeur)->toDateString();
        }

        if (is_numeric($valeur)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $valeur))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y', '!d/m/y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, trim((string) $valeur))->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** Clé insensible à la casse, aux accents, aux espaces et à la ponctuation. */
    private static function cle(?string $valeur): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(Str::ascii((string) $valeur))) ?? '';
    }
}
