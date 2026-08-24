<?php

namespace App\Imports;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Competence;
use App\Models\Departement;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Services\CompetenceAttributionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import des matières, dans le cycle que l'utilisateur désigne au moment du
 * dépôt du fichier.
 *
 * Le secondaire d'un côté, le primaire et la maternelle de l'autre, ne
 * décrivent pas la même chose, et un import unique aurait exigé de l'un des
 * deux des colonnes qui n'ont aucun sens chez lui. Primaire et maternelle
 * partagent le même fichier et le même traitement — seul l'établissement visé
 * change, d'où deux valeurs de cycle distinctes plutôt qu'un choix ambigu :
 *
 * - **secondaire** : la matière appartient à un département et s'enseigne dans
 *   des classes avec un coefficient, un quota horaire et un enseignant. Les
 *   colonnes d'affectation sont facultatives — sans elles, on importe le seul
 *   catalogue des matières ;
 * - **primaire et maternelle** : chaque ligne porte le barème réparti par
 *   volet (oral, écrit, savoir-être, et pratique quand la compétence s'y
 *   prête) — c'est donc d'abord une COMPÉTENCE ÉVALUÉE qui s'écrit, puisque
 *   c'est elle que le bulletin note. Une matière du même nom, rattachée à
 *   cette compétence, est installée dans le même geste : sans elle, la
 *   compétence resterait invisible dans la liste des matières alors que
 *   l'emploi du temps, les séances et la progression suivent les matières au
 *   quotidien (cf. Matiere::competence_id). L'établissement peut ensuite en
 *   ajouter d'autres à la main sous le même bloc.
 *
 * Les en-têtes sont tolérants — français, anglais, avec ou sans accent — parce
 * qu'un fichier d'établissement vient rarement du gabarit qu'on lui a donné.
 * L'import est idempotent : une matière déjà connue est mise à jour, pas
 * dupliquée (la table interdit de toute façon deux fois le même nom dans une
 * école).
 */
class MatiereImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithMultipleSheets
{
    public const CYCLE_SECONDAIRE = 'secondaire';

    public const CYCLE_PRIMAIRE = 'primaire';

    /**
     * Même fichier, mêmes colonnes, même traitement que le primaire (barème
     * réparti par volet) : distingué du primaire uniquement pour que
     * l'utilisateur déclare explicitement l'établissement visé plutôt qu'un
     * « Primaire / maternelle » ambigu qui ne dit pas vers laquelle des deux
     * écoles le fichier part.
     */
    public const CYCLE_MATERNELLE = 'maternelle';

    public const CYCLES = [self::CYCLE_SECONDAIRE, self::CYCLE_PRIMAIRE, self::CYCLE_MATERNELLE];

    /** Cycles qui décrivent des compétences évaluées (barème par volet) plutôt que des matières de département. */
    private const CYCLES_COMPETENCE = [self::CYCLE_PRIMAIRE, self::CYCLE_MATERNELLE];

    /**
     * En-tête source (slug minuscule produit par maatwebsite) => clé
     * canonique. Plusieurs en-têtes peuvent viser la même clé ; la première
     * colonne renseignée l'emporte.
     */
    private const COLONNES = [
        'nom' => 'nom',
        'matiere' => 'nom',
        'matieres' => 'nom',
        'libelle' => 'nom',
        'subject' => 'nom',
        'subjects' => 'nom',
        'nom_en' => 'nom_en',
        'name_en' => 'nom_en',
        'nom_anglais' => 'nom_en',
        'abbreviation' => 'abbreviation',
        'abreviation' => 'abbreviation',
        'sigle' => 'abbreviation',
        'departement' => 'departement',
        'department' => 'departement',
        'classes' => 'classes',
        'classe' => 'classes',
        'coefficient' => 'coefficient',
        'coef' => 'coefficient',
        'quota_horaire' => 'quota_horaire',
        'quota' => 'quota_horaire',
        'periodes' => 'quota_horaire',
        'periods' => 'quota_horaire',
        'enseignant' => 'enseignant',
        'enseignants' => 'enseignant',
        'teacher' => 'enseignant',
        'teachers' => 'enseignant',
        'oral' => 'oral',
        'ecrit' => 'ecrit',
        'savoir_etre' => 'savoir_etre',
        'pratique' => 'pratique',
    ];

    /**
     * Séparateurs admis dans la colonne `classes`. Le tiret en est
     * volontairement absent : les noms de classes en contiennent eux-mêmes
     * (« Home eco-5 », « ACT F1-Marketing F1 »), et le retenir découperait au
     * mauvais endroit sans qu'on puisse s'en apercevoir.
     */
    private const SEPARATEURS_CLASSES = [';', '|', "\n", "\r"];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $ignoredCount = 0;

    public int $affectationsCount = 0;

    /** @var array<string, int> libellé non résolu => nombre de lignes concernées */
    public array $classesIntrouvables = [];

    /** @var array<string, int> */
    public array $enseignantsIntrouvables = [];

    /** @var array<string, int>|null clé normalisée (nom ou sigle) => id de classe */
    private ?array $classes = null;

    /** @var array<string, int>|null clé normalisée du nom complet => id de personnel */
    private ?array $personnels = null;

    public function __construct(
        private readonly int $schoolId,
        private readonly string $cycle = self::CYCLE_SECONDAIRE,
        private readonly ?int $classeId = null,
    ) {}

    /**
     * Première feuille seulement. Un classeur d'établissement porte souvent
     * un mode d'emploi, un brouillon ou une feuille de calculs à côté des
     * données ; sans cette borne, maatwebsite les relit toutes et le mode
     * d'emploi devient des matières.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $this->canoniser($row instanceof Collection ? $row->all() : (array) $row);
            $nom = trim((string) ($ligne['nom'] ?? ''));

            if ($nom === '' && $this->cycle === self::CYCLE_SECONDAIRE) {
                $nom = trim((string) ($ligne['nom_en'] ?? ''));
            }

            // Une ligne sans nom de matière n'est pas une erreur à signaler :
            // c'est un total, un intitulé de section ou une ligne de garde.
            if ($nom === '') {
                $this->ignoredCount++;

                continue;
            }

            if (in_array($this->cycle, self::CYCLES_COMPETENCE, true)) {
                $this->enregistrerCompetence($nom, $ligne);

                continue;
            }

            $matiere = $this->enregistrerMatiere($nom, $ligne);
            $this->rattacherAffectations($matiere, $ligne);
        }
    }

    /**
     * Traduit les en-têtes du fichier en clés canoniques. Une colonne inconnue
     * est conservée telle quelle plutôt que jetée : elle ne sert à rien ici,
     * mais la laisser tomber compliquerait le diagnostic d'un fichier qui
     * n'importe pas ce qu'on attendait.
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function canoniser(array $ligne): array
    {
        $canonique = [];

        foreach ($ligne as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? $entete;

            if (is_string($valeur)) {
                $valeur = trim($valeur);
            }

            // La première colonne renseignée gagne : un fichier peut porter
            // à la fois « matiere » et « subject », l'un des deux étant vide.
            if (! isset($canonique[$cle]) || $canonique[$cle] === null || $canonique[$cle] === '') {
                $canonique[$cle] = $valeur;
            }
        }

        return $canonique;
    }

    /**
     * Compétence évaluée du primaire et de la maternelle. Le nom de la colonne
     * « matière » du fichier désigne ici la compétence : c'est elle que le
     * bulletin note, et le barème par volet que porte la ligne n'a de sens
     * qu'à ce niveau.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function enregistrerCompetence(string $label, array $ligne): Competence
    {
        $attributs = [
            'label_en' => $this->valeur($ligne, 'nom_en'),
            'abbreviation' => $this->valeur($ligne, 'abbreviation'),
            'statut' => 'actif',
        ] + $this->bareme($ligne);

        // Une colonne laissée vide ne doit pas effacer une valeur saisie à la
        // main dans l'application : on ne réécrit que ce que le fichier porte.
        $attributs = array_filter($attributs, fn($valeur) => $valeur !== null);

        $competence = Competence::firstOrNew(['school_id' => $this->schoolId, 'label_fr' => $label]);
        $existante = $competence->exists;

        $competence->fill($attributs)->save();

        $existante ? $this->updatedCount++ : $this->importedCount++;

        $this->rattacherMatiere($competence, $label, $ligne);

        return $competence;
    }

    /**
     * Matière de contenu installée du même nom que la compétence qu'elle
     * rejoint. Sans elle, la compétence resterait invisible dans la liste des
     * matières — c'est pourtant elle que l'emploi du temps, les séances et la
     * progression suivent au quotidien. Propagée aussitôt vers les classes qui
     * portent déjà la compétence, comme le fait l'ajout manuel d'une matière
     * au référentiel (cf. CompetenceAttributionService::propagerMatiere).
     *
     * @param  array<string, mixed>  $ligne
     */
    private function rattacherMatiere(Competence $competence, string $label, array $ligne): void
    {
        $attributs = array_filter([
            'nom_en' => $this->valeur($ligne, 'nom_en'),
            'abbreviation' => $this->valeur($ligne, 'abbreviation'),
            'competence_id' => $competence->id,
            'statut' => 'actif',
        ], fn($valeur) => $valeur !== null);

        $matiere = Matiere::firstOrNew(['school_id' => $this->schoolId, 'nom' => $label]);
        $matiere->fill($attributs)->save();

        app(CompetenceAttributionService::class)->propagerMatiere($matiere);
    }

    /** @param array<string, mixed> $ligne */
    private function enregistrerMatiere(string $nom, array $ligne): Matiere
    {
        $attributs = [
            'nom_en' => $this->valeur($ligne, 'nom_en'),
            'abbreviation' => $this->valeur($ligne, 'abbreviation'),
            'departement_id' => $this->departementId($this->valeur($ligne, 'departement')),
            'statut' => 'actif',
        ];

        // Renseigner explicitement une colonne à vide effacerait une valeur
        // saisie à la main dans l'application : on ne réécrit que ce que le
        // fichier porte réellement.
        $attributs = array_filter($attributs, fn($valeur) => $valeur !== null);

        $matiere = Matiere::firstOrNew(['school_id' => $this->schoolId, 'nom' => $nom]);
        $existante = $matiere->exists;

        $matiere->fill($attributs)->save();

        $existante ? $this->updatedCount++ : $this->importedCount++;

        return $matiere;
    }

    /**
     * Barème du primaire : la somme des volets fait la notation, et la
     * présence de points en pratique dit si la compétence évalue ce volet.
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function bareme(array $ligne): array
    {
        $volets = ['oral', 'ecrit', 'savoir_etre', 'pratique'];
        $points = [];

        foreach ($volets as $volet) {
            $valeur = $this->valeur($ligne, $volet);
            $points[$volet] = $valeur === null ? 0.0 : (float) $valeur;
        }

        // Aucun volet renseigné : la compétence garde le barème qu'elle a déjà.
        if (array_sum($points) <= 0) {
            return [];
        }

        $evaluePratique = $points['pratique'] > 0;

        $repartition = [
            'oral' => $points['oral'],
            'ecrit' => $points['ecrit'],
            'savoir_etre' => $points['savoir_etre'],
        ];

        if ($evaluePratique) {
            $repartition['pratique'] = $points['pratique'];
        }

        return [
            'notation' => (int) round(array_sum($repartition)),
            'evalue_pratique' => $evaluePratique,
            'repartition_volets' => $repartition,
        ];
    }

    /**
     * Crée l'affectation de la matière dans chacune des classes citées.
     *
     * Le département manquant se crée à la volée, mais pas la classe : une
     * classe inventée depuis un nom mal orthographié se retrouverait vide, à
     * côté de la vraie, et personne ne saurait laquelle garder. Les libellés
     * non résolus sont donc remontés à l'utilisateur, qui corrige et rejoue.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function rattacherAffectations(Matiere $matiere, array $ligne): void
    {
        $libelles = $this->classeId !== null
            ? ['__classe_cible__']
            : $this->decouperClasses($this->valeur($ligne, 'classes'));

        if ($libelles === []) {
            return;
        }

        $coefficient = $this->valeur($ligne, 'coefficient');
        $quota = $this->valeur($ligne, 'quota_horaire');
        $personnelId = $this->personnelId($this->valeur($ligne, 'enseignant'));

        foreach ($libelles as $libelle) {
            $classeId = $this->classeId ?? $this->classes()[self::cle($libelle)] ?? null;

            if ($classeId === null) {
                $this->classesIntrouvables[$libelle] = ($this->classesIntrouvables[$libelle] ?? 0) + 1;

                continue;
            }

            $affectation = ClasseMatiere::firstOrNew([
                'classe_id' => $classeId,
                'matiere_id' => $matiere->id,
            ]);

            $affectation->fill(array_filter([
                'personnel_id' => $personnelId,
                'coefficient' => $coefficient === null ? null : (float) $coefficient,
                'quota_horaire' => $quota === null ? null : (int) $quota,
            ], fn($valeur) => $valeur !== null));

            $affectation->statut ??= 'actif';
            $affectation->save();

            $this->affectationsCount++;
        }
    }

    /**
     * @return list<string>
     */
    private function decouperClasses(mixed $valeur): array
    {
        if ($valeur === null || trim((string) $valeur) === '') {
            return [];
        }

        $normalise = str_replace(self::SEPARATEURS_CLASSES, ';', (string) $valeur);

        return collect(explode(';', $normalise))
            ->map(fn(string $libelle) => trim($libelle))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function departementId(mixed $nom): ?int
    {
        $nom = trim((string) ($nom ?? ''));

        if ($nom === '') {
            return null;
        }

        return Departement::firstOrCreate(['school_id' => $this->schoolId, 'nom' => $nom])->id;
    }

    private function personnelId(mixed $nomComplet): ?int
    {
        $cle = self::cle((string) ($nomComplet ?? ''));

        if ($cle === '') {
            return null;
        }

        $id = $this->personnels()[$cle] ?? null;

        if ($id === null) {
            $libelle = trim((string) $nomComplet);
            $this->enseignantsIntrouvables[$libelle] = ($this->enseignantsIntrouvables[$libelle] ?? 0) + 1;
        }

        return $id;
    }

    /** @return array<string, int> */
    private function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $this->classes = [];

        foreach (Classe::where('school_id', $this->schoolId)->get(['id', 'nom', 'sigle']) as $classe) {
            foreach ([$classe->nom, $classe->sigle] as $libelle) {
                $cle = self::cle((string) $libelle);

                if ($cle !== '' && ! isset($this->classes[$cle])) {
                    $this->classes[$cle] = $classe->id;
                }
            }
        }

        return $this->classes;
    }

    /** @return array<string, int> */
    private function personnels(): array
    {
        if ($this->personnels !== null) {
            return $this->personnels;
        }

        $this->personnels = [];

        foreach (Personnel::where('school_id', $this->schoolId)->get(['id', 'nom_complet']) as $personnel) {
            $cle = self::cle((string) $personnel->nom_complet);

            if ($cle !== '' && ! isset($this->personnels[$cle])) {
                $this->personnels[$cle] = $personnel->id;
            }
        }

        return $this->personnels;
    }

    /**
     * Clé de rapprochement : accents, casse, espaces et ponctuation écartés.
     * « Building Construction. F3 » et « building construction f3 » désignent
     * la même classe, et un fichier saisi à la main varie sur les trois.
     */
    private static function cle(string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii($libelle))) ?? '';
    }

    private function valeur(array $ligne, string $cle): mixed
    {
        $valeur = $ligne[$cle] ?? null;

        return is_string($valeur) && trim($valeur) === '' ? null : $valeur;
    }
}
