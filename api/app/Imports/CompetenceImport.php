<?php

namespace App\Imports;

use App\Models\Classe;
use App\Models\Competence;
use App\Services\CompetenceAttributionService;
use App\Support\ImportExport\Resolveur;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import du référentiel des compétences — **une ligne par compétence ET par
 * classe**.
 *
 * Le barème et la répartition des volets ne vivent plus sur la compétence mais
 * sur son attribution à une classe : une même compétence pèse 20 au CM2 et 10
 * au CP. Une ligne porte donc à la fois l'identité de la compétence (libellés,
 * abréviation, ordre — relus tels quels à chaque ligne) et la façon dont LA
 * CLASSE nommée la note. Une compétence enseignée dans six classes occupe six
 * lignes, exactement comme {@see \App\Exports\MatiereExport} sort une ligne par
 * affectation — et c'est la forme que ressort {@see \App\Exports\CompetenceExport},
 * pour que l'aller-retour tableur fonctionne.
 *
 * La colonne `Classe` reste facultative : une ligne sans classe se contente de
 * déclarer la compétence au référentiel, à attribuer ensuite depuis l'écran des
 * classes. Une classe que l'on ne sait pas rattacher n'arrête pas l'import —
 * la compétence est tout de même enregistrée, et le libellé fautif remonte dans
 * `classesIntrouvables` pour que l'utilisateur corrige son fichier et rejoue.
 */
class CompetenceImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    /**
     * En-tête source (slug minuscule produit par maatwebsite) => clé
     * canonique. Plusieurs en-têtes peuvent viser la même clé ; la première
     * colonne renseignée l'emporte — même tolérance aux synonymes que
     * {@see MatiereImport::COLONNES}.
     */
    private const COLONNES = [
        'competence' => 'label_fr',
        'competences' => 'label_fr',
        'label_fr' => 'label_fr',
        'nom_fr' => 'label_fr',
        'nom' => 'label_fr',
        'libelle' => 'label_fr',
        'competence_fr' => 'label_fr',
        'label_en' => 'label_en',
        'nom_en' => 'label_en',
        'competence_en' => 'label_en',
        'abbreviation' => 'abbreviation',
        'abreviation' => 'abbreviation',
        'sigle' => 'abbreviation',
        'ordre' => 'ordre',
        'classe' => 'classe',
        'classes' => 'classe',
        'notation' => 'notation',
        'bareme' => 'notation',
        'note_sur' => 'notation',
        'notee_sur' => 'notation',
        'oral' => 'oral',
        'ecrit' => 'ecrit',
        'savoir_etre' => 'savoir_etre',
        'savoiretre' => 'savoir_etre',
        'pratique' => 'pratique',
    ];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $attributionsCount = 0;

    /** @var list<string> Libellés de classes qu'aucune classe de l'école ne porte. */
    public array $classesIntrouvables = [];

    /** @var array<int, true> Compétences déjà vues dans ce fichier, pour ne les compter qu'une fois. */
    private array $competencesVues = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly CompetenceAttributionService $attribution,
    ) {}

    /**
     * En-têtes du modèle téléchargeable — et du fichier que l'export produit :
     * les deux ne peuvent pas diverger sans casser le réimport.
     *
     * @return list<string>
     */
    public static function enTetes(): array
    {
        return [
            'Competence',
            'Competence (EN)',
            'Abreviation',
            'Ordre',
            'Classe',
            'Notation',
            'Oral',
            'Ecrit',
            'Savoir-etre',
            'Pratique',
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : (array) $row);

            if (($ligne['label_fr'] ?? null) === null) {
                continue;
            }

            $competence = $this->enregistrerCompetence($ligne);

            if (($ligne['classe'] ?? null) !== null) {
                $this->attribuer($competence, $ligne);
            }
        }
    }

    /**
     * Ligne brute -> clés canoniques : en-têtes normalisés, chaînes élaguées,
     * cellules vides ramenées à `null`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliser(array $data): array
    {
        $ligne = [];

        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : $valeur;
            $valeur = ($valeur === '') ? null : $valeur;

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        return $ligne;
    }

    /**
     * Crée ou met à jour la compétence, reconnue à son libellé français dans
     * l'école — même clé d'unicité qu'avant le découpage par classe, pour que
     * les fichiers existants continuent de se réimporter.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function enregistrerCompetence(array $ligne): Competence
    {
        $competence = Competence::updateOrCreate(
            ['school_id' => $this->schoolId, 'label_fr' => $ligne['label_fr']],
            array_filter([
                'label_en' => $ligne['label_en'] ?? null,
                'abbreviation' => $ligne['abbreviation'] ?? null,
                'ordre' => isset($ligne['ordre']) ? (int) $ligne['ordre'] : null,
            ], fn ($v) => $v !== null),
        );

        // Les lignes d'une même compétence se suivent (une par classe) : seule
        // la première compte, sans quoi le décompte annoncé à l'utilisateur
        // compterait des classes et non des compétences.
        if (! isset($this->competencesVues[$competence->id])) {
            $this->competencesVues[$competence->id] = true;
            $competence->wasRecentlyCreated ? $this->importedCount++ : $this->updatedCount++;
        }

        return $competence;
    }

    /**
     * Attribue la compétence à la classe nommée, avec le barème de la ligne.
     *
     * Passe par {@see CompetenceAttributionService} plutôt que d'écrire la
     * table : attribuer une compétence installe aussi ses matières dans la
     * classe, et l'import n'a aucune raison de court-circuiter cette règle.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function attribuer(Competence $competence, array $ligne): void
    {
        $classeId = Resolveur::id(Classe::class, $this->schoolId, $ligne['classe'], ['nom']);

        if ($classeId === null) {
            $libelle = (string) $ligne['classe'];

            if (! in_array($libelle, $this->classesIntrouvables, true)) {
                $this->classesIntrouvables[] = $libelle;
            }

            return;
        }

        $classe = Classe::find($classeId);

        if ($classe === null) {
            return;
        }

        $this->attribution->attribuer($classe, [$competence->id], $this->bareme($ligne));
        $this->attributionsCount++;
    }

    /**
     * Barème de la ligne. Les quatre colonnes de volets sont facultatives :
     * sans aucune d'elles, la répartition reste nulle et le barème se partage
     * à parts égales ({@see \App\Models\ClasseCompetence::repartitionVolets()}).
     * Le volet pratique n'est déclaré évalué que s'il porte des points — c'est
     * la seule façon de l'exprimer dans un tableur, et la case à cocher de
     * l'interface dit la même chose.
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function bareme(array $ligne): array
    {
        $volets = [];

        foreach (['oral', 'ecrit', 'savoir_etre', 'pratique'] as $volet) {
            if (isset($ligne[$volet])) {
                $volets[$volet] = (float) $ligne[$volet];
            }
        }

        return [
            'notation' => isset($ligne['notation']) ? (int) $ligne['notation'] : null,
            'evalue_pratique' => ($volets['pratique'] ?? 0) > 0,
            'repartition_volets' => $volets === [] ? null : $volets,
        ];
    }
}
