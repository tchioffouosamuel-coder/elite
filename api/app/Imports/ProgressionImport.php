<?php

namespace App\Imports;

use App\Models\ClasseMatiere;
use App\Models\ProgressionItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import de la fiche de progression au format du gabarit de l'établissement.
 *
 * Il en existe deux — un pour maternelle/primaire, un pour le secondaire —
 * qui partagent la plupart de leurs colonnes mais pas la ligne d'en-tête (7
 * pour le premier, 8 pour le second, à cause du cartouche « Department /
 * Specialty » propre au secondaire) : le cycle de l'affectation visée
 * détermine laquelle lire, pas une détection sur le contenu du fichier.
 *
 * L'import COMPLÈTE, il n'écrase pas : une leçon déjà présente — reconnue à
 * son couple Topic + Sub-topic — ne voit remplir que ses champs restés vides.
 */
class ProgressionImport implements ToCollection, WithHeadingRow
{
    /**
     * En-tête du fichier (normalisé) => champ du modèle. Commune aux deux
     * gabarits : « Competency » (primaire seul) et « Teaching / Strategy »
     * (secondaire seul) y figurent toutes les deux, la colonne absente du
     * fichier importé n'apparaissant simplement jamais dans les en-têtes lus.
     */
    private const COLONNES = [
        'week' => 'semaine',
        'dateplanned' => 'date_prevue',
        'datetaught' => 'date_realisee',
        'duration' => 'duree',
        'periods' => 'duree',
        'topic' => 'topic',
        'subtopic' => 'sous_topic',
        'competency' => 'competence',
        'learningoutcomes' => 'expected_learning_outcomes',
        'entrybehaviourpreviousknowledge' => 'entry_behaviour',
        'teachingaids' => 'teaching_aids',
        'resourcesteachingaids' => 'teaching_aids',
        'teachingstrategy' => 'teaching_learning_strategies',
        'teachersactivities' => 'facilitators_activities',
        'learnersactivities' => 'learners_activities',
        'assessment' => 'assessment',
        'assessmentevaluation' => 'assessment',
        'assignment' => 'assignment',
        'remarks' => 'remarks',
    ];

    public int $creees = 0;

    public int $completees = 0;

    public int $ignorees = 0;

    public function __construct(private readonly ClasseMatiere $classeMatiere, private readonly string $cycle) {}

    /** Ligne 7 pour maternelle/primaire, ligne 8 pour le secondaire (cartouche Department/Specialty en plus). */
    public function headingRow(): int
    {
        return $this->cycle === 'secondaire' ? 8 : 7;
    }

    public function collection(Collection $rows): void
    {
        // Leçons déjà en base, indexées par Topic + Sub-topic : c'est ce
        // couple qui identifie une ligne de la feuille.
        $existantes = ProgressionItem::where('classe_matiere_id', $this->classeMatiere->id)
            ->where('type', 'lecon')
            ->get()
            ->keyBy(fn (ProgressionItem $item) => self::cle($item->topic).'|'.self::cle($item->sous_topic));

        $ordre = (int) ProgressionItem::where('classe_matiere_id', $this->classeMatiere->id)->max('ordre');

        foreach ($rows as $row) {
            $ligne = $this->canoniser($row instanceof Collection ? $row->all() : (array) $row);

            // Une ligne sans topic ni sous-sujet est une ligne vide
            // pré-formatée : les deux gabarits en comptent plusieurs.
            if (($ligne['topic'] ?? null) === null && ($ligne['sous_topic'] ?? null) === null) {
                $this->ignorees++;

                continue;
            }

            $cle = self::cle($ligne['topic'] ?? null).'|'.self::cle($ligne['sous_topic'] ?? null);
            $existante = $existantes->get($cle);

            if ($existante !== null) {
                $this->completer($existante, $ligne);

                continue;
            }

            $item = ProgressionItem::create([
                'classe_matiere_id' => $this->classeMatiere->id,
                'type' => 'lecon',
                // Le titre affiché dans la liste : le sujet, le sous-sujet à
                // défaut — jamais vide, la colonne l'exige.
                'titre' => $ligne['topic'] ?? $ligne['sous_topic'],
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
            $actuel = $champ === 'colonnes_libres' ? null : $item->{$champ};

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
     * valeurs. Une colonne inconnue est écartée : les gabarits en portent
     * plusieurs (cartouche, colonnes propres à une matière) qui ne relèvent
     * pas de l'import ligne à ligne.
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

            $valeur = in_array($champ, ['date_prevue', 'date_realisee'], true) ? self::date($valeur) : self::texte($valeur);

            if ($valeur !== null) {
                $canonique[$champ] = $valeur;
            }
        }

        return $canonique;
    }

    private static function texte(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

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

    /**
     * En-têtes du fichier, normalisés comme le fait maatwebsite (formateur
     * « slug » par défaut) : sert à choisir la ligne d'en-tête à passer à
     * Excel::import() (7 ou 8) avant de savoir si le fichier correspond
     * vraiment au cycle de l'affectation visée — l'appelant compare le
     * résultat à ce qu'il attend et rejette sinon.
     */
    public static function ligneEnTete(UploadedFile $fichier): ?int
    {
        $lecteur = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fichier->getRealPath());
        $lecteur->setReadDataOnly(true);
        $feuille = $lecteur->load($fichier->getRealPath())->getSheet(0);

        foreach ([7, 8] as $ligne) {
            foreach ($feuille->getRowIterator($ligne, $ligne) as $ligneEntete) {
                foreach ($ligneEntete->getCellIterator() as $cellule) {
                    if (self::cle((string) $cellule->getValue()) === 'week') {
                        return $ligne;
                    }
                }
            }
        }

        return null;
    }
}
