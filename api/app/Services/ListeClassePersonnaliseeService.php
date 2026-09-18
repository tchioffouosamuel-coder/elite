<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Sequence;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Support\ListeClasseColonnes;
use App\Support\Pdf\ListeClassePersonnaliseeGenerator;
use App\Support\Word\EnTeteWord;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Construit les lignes de la « liste personnalisée de classe » (colonnes au
 * choix de l'utilisateur, cf. ListeClasseColonnes) puis les met en forme
 * dans les trois formats proposés — PDF, Word, Excel — pour rester la seule
 * source de vérité sur ce que contient chaque colonne, quel que soit le
 * format demandé.
 *
 * Les colonnes financières (statut solvabilité, reste à payer, dette
 * antérieure) réutilisent {@see ScolariteService::situation()}, déjà
 * responsable de ce calcul pour le recouvrement — pas de second calcul
 * susceptible de diverger. « Situation transport » se déduit de
 * {@see BusAffectation}, « moyenne » et « absences » de {@see MoyenneService}
 * / {@see MoyennePrimaireService} et {@see DisciplineService} sur la même
 * période choisie (trimestre, séquence ou année), et les colonnes parent de
 * {@see Eleve::tuteurs()}.
 */
class ListeClassePersonnaliseeService extends BaseService
{
    private const ACCENT = '39B54A';

    private const ARDOISE = '292F36';

    private const COLONNES_PARENTS = ['nom_parent', 'numero_parent', 'nom_pere', 'numero_pere', 'nom_mere', 'numero_mere'];

    public function __construct(
        private readonly ScolariteService $scolariteService,
        private readonly MoyenneService $moyenneService,
        private readonly MoyennePrimaireService $moyennePrimaireService,
        private readonly DisciplineService $disciplineService,
    ) {}

    /**
     * @param  list<string>  $colonnes
     * @return list<array<string, string>> une ligne par élève, valeurs déjà formatées pour l'affichage
     */
    public function construireLignes(Classe $classe, array $colonnes, ?string $moyenneType = null, ?int $moyenneReferenceId = null): array
    {
        $classe->loadMissing('school');
        $besoinParents = array_intersect($colonnes, self::COLONNES_PARENTS) !== [];

        $eleves = Eleve::forSchool($classe->school_id)
            ->where('classe_id', $classe->id)
            ->where('statut', 'actif')
            ->when($besoinParents, fn ($q) => $q->with('tuteurs'))
            ->orderBy('nom_complet')
            ->get();

        $dossiersParEleve = $this->besoinFinance($colonnes) ? $this->dossiersParEleve($classe) : collect();
        $abonnesBus = in_array('situation_transport', $colonnes, true) ? $this->elevesAbonnesBus($classe) : collect();
        $moyennes = in_array('moyenne', $colonnes, true)
            ? $this->moyennesParEleve($classe, $eleves, $moyenneType, $moyenneReferenceId)
            : collect();
        $absences = in_array('absences', $colonnes, true)
            ? $this->absencesParEleve($classe, $moyenneType, $moyenneReferenceId)
            : collect();
        $uniteAbsence = $classe->school?->estSecondaire() ?? true ? 'h' : 'j';

        $lignes = [];
        foreach ($eleves as $index => $eleve) {
            $ligne = [];
            foreach ($colonnes as $colonne) {
                $ligne[$colonne] = $this->valeurColonne($colonne, $eleve, $index + 1, $dossiersParEleve, $abonnesBus, $moyennes, $absences, $uniteAbsence);
            }
            $lignes[] = $ligne;
        }

        return $lignes;
    }

    /** @param list<string> $colonnes */
    private function besoinFinance(array $colonnes): bool
    {
        return array_intersect($colonnes, ['statut_solvabilite', 'reste_scolarite_a_payer', 'dette_anterieure']) !== [];
    }

    /** @return Collection<int, mixed> dossier (ou dossier projeté) keyé par eleve_id */
    private function dossiersParEleve(Classe $classe): Collection
    {
        $annee = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->first();

        if (! $annee) {
            return collect();
        }

        return $this->scolariteService
            ->situation($classe->school_id, $annee->id, ['classe_id' => $classe->id])['dossiers']
            ->keyBy(fn ($dossier) => $dossier->eleve_id ?? $dossier->eleve->id);
    }

    /** @return Collection<int, bool> vrai si l'élève a une souscription bus active, keyé par eleve_id */
    private function elevesAbonnesBus(Classe $classe): Collection
    {
        $annee = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->first();

        if (! $annee) {
            return collect();
        }

        return BusAffectation::whereIn('eleve_id', Eleve::where('classe_id', $classe->id)->pluck('id'))
            ->where('annee_scolaire_id', $annee->id)
            ->actives()
            ->pluck('eleve_id')
            ->flip()
            ->map(fn () => true);
    }

    /** @return Collection<int, ?float> moyenne keyée par eleve_id */
    private function moyennesParEleve(Classe $classe, Collection $eleves, ?string $moyenneType, ?int $moyenneReferenceId): Collection
    {
        $classe->loadMissing('school');
        $secondaire = $classe->school?->estSecondaire() ?? true;
        $service = $secondaire ? $this->moyenneService : $this->moyennePrimaireService;

        return match ($moyenneType) {
            'trimestre' => $this->calculerMoyennes($eleves, function (Eleve $eleve) use ($service, $moyenneReferenceId) {
                $trimestre = $moyenneReferenceId ? Trimestre::find($moyenneReferenceId) : null;

                return $trimestre ? $service->moyenneGeneraleEleve($eleve, $trimestre)['moyenne'] : null;
            }),
            'sequence' => $this->calculerMoyennes($eleves, function (Eleve $eleve) use ($service, $moyenneReferenceId) {
                $sequence = $moyenneReferenceId ? Sequence::find($moyenneReferenceId) : null;

                return $sequence ? $service->moyenneSequenceEleve($eleve, $sequence) : null;
            }),
            'annuelle' => $this->calculerMoyennes($eleves, function (Eleve $eleve) use ($service, $classe) {
                $annee = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->first();

                return $annee ? $service->moyenneAnnuelleEleve($eleve, $annee->id) : null;
            }),
            default => collect(),
        };
    }

    /** @return Collection<int, ?float> */
    private function calculerMoyennes(Collection $eleves, \Closure $moyenne): Collection
    {
        return $eleves->mapWithKeys(fn (Eleve $eleve) => [$eleve->id => $moyenne($eleve)]);
    }

    /**
     * Absences non justifiées sur la même période que la moyenne (cf.
     * $moyenneType/$moyenneReferenceId). Le suivi n'existe qu'au grain du
     * trimestre ({@see DisciplineService::grille()}) : une séquence retombe
     * donc sur les absences de tout son trimestre, faute de plus fin.
     *
     * @return Collection<int, float>
     */
    private function absencesParEleve(Classe $classe, ?string $moyenneType, ?int $moyenneReferenceId): Collection
    {
        return match ($moyenneType) {
            'trimestre' => $this->absencesTrimestre($classe, $moyenneReferenceId ? Trimestre::find($moyenneReferenceId) : null),
            'sequence' => $this->absencesTrimestre($classe, $moyenneReferenceId ? Sequence::find($moyenneReferenceId)?->trimestre : null),
            'annuelle' => $this->absencesAnnuelles($classe),
            default => collect(),
        };
    }

    /** @return Collection<int, float> */
    private function absencesTrimestre(Classe $classe, ?Trimestre $trimestre): Collection
    {
        if (! $trimestre) {
            return collect();
        }

        return $this->disciplineService->grille($classe, $trimestre)
            ->mapWithKeys(fn (array $ligne) => [$ligne['eleve_id'] => $ligne['non_justifiees']]);
    }

    /** @return Collection<int, float> */
    private function absencesAnnuelles(Classe $classe): Collection
    {
        $annee = AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->first();

        if (! $annee) {
            return collect();
        }

        $total = collect();

        foreach (Trimestre::where('annee_scolaire_id', $annee->id)->get() as $trimestre) {
            $this->disciplineService->grille($classe, $trimestre)->each(function (array $ligne) use ($total) {
                $total[$ligne['eleve_id']] = ($total[$ligne['eleve_id']] ?? 0) + $ligne['non_justifiees'];
            });
        }

        return $total;
    }

    /** Père ou mère selon le lien de parenté du rattachement — même repérage que AttestationService::parent(). */
    private function tuteurParRole(Eleve $eleve, string $role): ?Tuteur
    {
        $prefixes = $role === 'pere' ? ['pere', 'père', 'father'] : ['mere', 'mère', 'mother'];

        return $eleve->tuteurs->first(function (Tuteur $tuteur) use ($prefixes) {
            $lien = mb_strtolower(trim((string) $tuteur->pivot->lien_parente));

            return $lien !== '' && collect($prefixes)->contains(fn (string $p) => str_starts_with($lien, $p));
        });
    }

    /** Contact principal (pivot `is_principal`), ou à défaut le premier tuteur rattaché. */
    private function tuteurPrincipal(Eleve $eleve): ?Tuteur
    {
        return $eleve->tuteurs->first(fn (Tuteur $t) => (bool) $t->pivot->is_principal) ?? $eleve->tuteurs->first();
    }

    /**
     * @param  Collection<int, mixed>  $dossiersParEleve
     * @param  Collection<int, bool>  $abonnesBus
     * @param  Collection<int, ?float>  $moyennes
     * @param  Collection<int, float>  $absences
     */
    private function valeurColonne(
        string $colonne,
        Eleve $eleve,
        int $rang,
        Collection $dossiersParEleve,
        Collection $abonnesBus,
        Collection $moyennes,
        Collection $absences,
        string $uniteAbsence,
    ): string {
        return match ($colonne) {
            'numero' => (string) $rang,
            'nom_prenom' => $eleve->nom_complet,
            'date_naissance' => $eleve->date_naissance?->format('d/m/Y') ?? '—',
            'lieu_naissance' => $eleve->lieu_naissance ?: '—',
            'sexe' => $eleve->sexe ?: '—',
            'age' => $eleve->age !== null ? (string) $eleve->age : '—',
            'nom_parent' => $this->tuteurPrincipal($eleve)?->nom_complet ?: '—',
            'numero_parent' => $this->tuteurPrincipal($eleve)?->telephone ?: '—',
            'nom_pere' => $this->tuteurParRole($eleve, 'pere')?->nom_complet ?: '—',
            'numero_pere' => $this->tuteurParRole($eleve, 'pere')?->telephone ?: '—',
            'nom_mere' => $this->tuteurParRole($eleve, 'mere')?->nom_complet ?: '—',
            'numero_mere' => $this->tuteurParRole($eleve, 'mere')?->telephone ?: '—',
            'statut_solvabilite' => $this->libelleStatutPaiement($dossiersParEleve->get($eleve->id)?->statut_paiement),
            'reste_scolarite_a_payer' => $this->montant($dossiersParEleve->get($eleve->id)?->reste_a_payer),
            'situation_transport' => $abonnesBus->get($eleve->id) ? 'Abonné' : 'Non abonné',
            'dette_anterieure' => $this->montant($dossiersParEleve->get($eleve->id)?->report_dette),
            'absences' => $absences->has($eleve->id) ? number_format($absences->get($eleve->id), 1, ',', ' ').' '.$uniteAbsence : '—',
            'moyenne' => $moyennes->get($eleve->id) !== null ? number_format((float) $moyennes->get($eleve->id), 2, ',', ' ') : '—',
            default => '—',
        };
    }

    private function libelleStatutPaiement(?string $statut): string
    {
        return match ($statut) {
            'solde' => 'Soldé',
            'partiel' => 'Partiel',
            'impaye' => 'Impayé',
            'avance' => 'Avance',
            'sans_frais' => 'Sans frais',
            default => '—',
        };
    }

    private function montant(?int $valeur): string
    {
        return $valeur === null ? '—' : number_format($valeur, 0, ',', ' ');
    }

    /** Génère le PDF et retourne son contenu binaire — cf. ListeClassePersonnaliseeGenerator. */
    public function genererPdf(Classe $classe, string $titreFr, string $titreEn, array $colonnes, array $lignes): string
    {
        return (new ListeClassePersonnaliseeGenerator)->build($classe, $titreFr, $titreEn, $colonnes, $lignes);
    }

    /**
     * Génère le .docx et retourne le chemin du fichier temporaire (à
     * supprimer après envoi par le contrôleur) — même patron que
     * {@see ListeElevesService::genererWord()}.
     *
     * @param  list<string>  $colonnes
     * @param  list<array<string, string>>  $lignes
     */
    public function genererWord(Classe $classe, string $titreFr, string $titreEn, array $colonnes, array $lignes): string
    {
        $school = $classe->school;

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Montserrat');
        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
            'marginRight' => 720,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);

        $section->addText(mb_strtoupper($titreFr), ['bold' => true, 'size' => 14, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText($titreEn, ['italic' => true, 'size' => 11, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 120]);

        $this->ajouterBandeau($section, $classe, count($lignes));

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
            'alignment' => JcTable::CENTER,
        ]);

        // Largeur imprimable disponible avec les marges étroites ci-dessus,
        // répartie à parts égales entre les colonnes choisies.
        $largeur = intdiv(10466, max(count($colonnes), 1));

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($colonnes as $colonne) {
            [$fr, $en] = ListeClasseColonnes::libelles($colonne);
            $cellule = $table->addCell($largeur, ['bgColor' => self::ACCENT]);
            $ligne = $cellule->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
            $ligne->addText($fr, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
            $ligne->addTextBreak();
            $ligne->addText($en, ['italic' => true, 'color' => 'FFFFFF', 'size' => 7]);
        }

        foreach ($lignes as $donnee) {
            $table->addRow();
            foreach ($colonnes as $colonne) {
                $table->addCell($largeur)->addText((string) ($donnee[$colonne] ?? '—'), ['size' => 9], ['alignment' => JcTable::CENTER]);
            }
        }

        if ($lignes === []) {
            $table->addRow();
            $table->addCell($largeur * count($colonnes), ['gridSpan' => count($colonnes)])
                ->addText('Aucun élève dans cette classe.', ['size' => 9, 'italic' => true], ['alignment' => JcTable::CENTER]);
        }

        $this->ajouterSignature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/liste-classe-personnalisee-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    private function ajouterBandeau(Section $section, Classe $classe, int $effectif): void
    {
        $bandeau = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'cellMargin' => 60, 'alignment' => JcTable::CENTER]);
        $bandeau->addRow();
        $ligne = $bandeau->addCell(null, ['bgColor' => self::ARDOISE])
            ->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);

        $mentions = [
            ['Classe ', '/ Class', ' : '.$classe->nom],
            ['Année scolaire ', '/ Academic year', ' : '.(AnneeScolaire::where('school_id', $classe->school_id)->where('is_active', true)->value('libelle') ?? '—')],
            ['Effectif ', '/ Headcount', ' : '.$effectif],
        ];

        foreach ($mentions as $i => [$fr, $en, $valeur]) {
            if ($i > 0) {
                $ligne->addText('   |   ', ['color' => 'FFFFFF', 'size' => 9]);
            }

            $ligne->addText($fr, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
            $ligne->addText($en, ['italic' => true, 'color' => 'FFFFFF', 'size' => 8]);
            $ligne->addText($valeur, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
    }

    private function ajouterSignature(Section $section, $school): void
    {
        $section->addTextBreak(1);

        $ville = trim(explode(',', (string) $school->address)[0] ?? '');
        $lieu = $ville !== '' ? "Fait à {$ville}, le " : 'Fait le ';

        $table = $section->addTable(['width' => 100 * 50, 'unit' => 'pct', 'alignment' => JcTable::CENTER]);
        $table->addRow();

        $table->addCell(5000, ['valign' => 'top'])
            ->addText($lieu.now()->format('d/m/Y'), ['size' => 9], ['spaceAfter' => 0]);

        $droite = $table->addCell(5000, ['valign' => 'top']);
        $titre = $droite->addTextRun(['alignment' => JcTable::CENTER, 'spaceAfter' => 0]);
        $titre->addText("Le Chef d'Établissement", ['bold' => true, 'size' => 9]);
        $titre->addTextBreak();
        $titre->addText('The Principal', ['italic' => true, 'size' => 8]);

        $droite->addTextBreak(3);
        $droite->addText(
            'Signature et cachet',
            ['size' => 8],
            ['alignment' => JcTable::CENTER, 'spaceAfter' => 0, 'borderTopSize' => 4, 'borderTopColor' => '000000'],
        );
    }
}
