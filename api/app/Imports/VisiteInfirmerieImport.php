<?php

namespace App\Imports;

use App\Models\Eleve;
use App\Models\InventaireArticle;
use App\Models\MalaiseReferentiel;
use App\Models\VisiteInfirmerie;
use App\Services\InfirmerieService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class VisiteInfirmerieImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    private const COLONNES = [
        'id' => 'id',
        'matriculeeleve' => 'matricule_eleve',
        'matricule' => 'matricule_eleve',
        'nomeleve' => 'nom_eleve',
        'nom' => 'nom_eleve',
        'datevisite' => 'date_visite',
        'date' => 'date_visite',
        'raison' => 'raison',
        'motif' => 'raison',
        'malaises' => 'malaises',
        'symptomes' => 'malaises',
        'soinsprodigues' => 'soins_prodiges',
        'soins' => 'soins_prodiges',
        'typetraitement' => 'type_traitement',
        'type' => 'type_traitement',
        'structureexterne' => 'structure_externe',
        'coutsoins' => 'cout_soins',
        'materielsinventaire' => 'materiels',
        'materiels' => 'materiels',
        'autremateriel' => 'autre_materiel',
        'coutautremateriel' => 'cout_autre_materiel',
        'observations' => 'observations',
    ];

    public function __construct(
        private readonly int $schoolId,
        private readonly InfirmerieService $service,
    ) {}

    public static function enTetes(): array
    {
        return [
            'ID',
            'Matricule élève',
            'Nom élève',
            'Date visite',
            'Raison',
            'Malaises',
            'Soins prodigués',
            'Type traitement',
            'Structure externe',
            'Coût soins',
            'Matériels inventaire',
            'Autre matériel',
            'Coût autre matériel',
            'Observations',
        ];
    }

    public function prepareForValidation(array $data, int $index): array
    {
        $ligne = [];

        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[self::cle($entete)] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : $valeur;
            if ($cle !== null && $valeur !== '') {
                $ligne[$cle] = $valeur;
            }
        }

        if (isset($ligne['date_visite'])) {
            $ligne['date_visite'] = $this->dateHeure($ligne['date_visite']);
        }
        if (isset($ligne['type_traitement'])) {
            $ligne['type_traitement'] = self::cle($ligne['type_traitement']);
        }
        foreach (['cout_soins', 'cout_autre_materiel'] as $cle) {
            if (isset($ligne[$cle])) {
                $ligne[$cle] = (int) preg_replace('/\D+/', '', (string) $ligne[$cle]);
            }
        }

        return $ligne;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $row instanceof Collection ? $row->all() : $row;
            $eleve = $this->eleve($ligne);

            if (! $eleve) {
                continue;
            }

            $donnees = [
                'eleve_id' => $eleve->id,
                'classe_id' => $eleve->classe_id,
                'date_visite' => $ligne['date_visite'],
                'raison' => $ligne['raison'],
                'soins_prodiges' => $ligne['soins_prodiges'],
                'type_traitement' => $ligne['type_traitement'] ?? 'interne',
                'structure_externe' => ($ligne['type_traitement'] ?? 'interne') === 'interne' ? null : ($ligne['structure_externe'] ?? null),
                'cout_soins' => (int) ($ligne['cout_soins'] ?? 0),
                'autre_materiel' => $ligne['autre_materiel'] ?? null,
                'cout_autre_materiel' => (int) ($ligne['cout_autre_materiel'] ?? 0),
                'observations' => $ligne['observations'] ?? null,
            ];

            $malaises = $this->malaises($ligne['malaises'] ?? null, $eleve->school_id);
            $materiels = $this->materiels($ligne['materiels'] ?? null, $eleve->school_id);

            $visite = ! empty($ligne['id'])
                ? VisiteInfirmerie::forSchool($this->schoolId)->find((int) $ligne['id'])
                : null;

            if ($visite) {
                $this->service->modifier($visite, $donnees, $malaises, $materiels);
                $this->updatedCount++;
            } else {
                $this->service->creer($donnees, $malaises, $materiels);
                $this->importedCount++;
            }
        }
    }

    public function rules(): array
    {
        return [
            'date_visite' => ['required', 'date'],
            'raison' => ['required', 'string'],
            'soins_prodiges' => ['required', 'string'],
            'type_traitement' => ['nullable', 'in:interne,externe,mixte'],
            'cout_soins' => ['nullable', 'integer', 'min:0'],
            'cout_autre_materiel' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function eleve(array $ligne): ?Eleve
    {
        $query = Eleve::forSchool($this->schoolId);

        if (! empty($ligne['matricule_eleve'])) {
            $eleve = (clone $query)->where('matricule', $ligne['matricule_eleve'])->first();
            if ($eleve) {
                return $eleve;
            }
        }

        if (! empty($ligne['nom_eleve'])) {
            $cle = self::cle($ligne['nom_eleve']);

            return $query->get()->first(fn (Eleve $eleve) => self::cle($eleve->nom_complet) === $cle);
        }

        return null;
    }

    /** @return list<int> */
    private function malaises(mixed $valeur, int $schoolId): array
    {
        return collect(preg_split('/[;,|]+/', (string) $valeur) ?: [])
            ->map(fn ($label) => trim($label))
            ->filter()
            ->map(fn ($label) => MalaiseReferentiel::firstOrCreate(
                ['school_id' => $schoolId, 'label_fr' => $label],
                ['label_en' => null],
            )->id)
            ->values()
            ->all();
    }

    /** @return list<array{inventaire_article_id: int, quantite: int}> */
    private function materiels(mixed $valeur, int $schoolId): array
    {
        return collect(preg_split('/[;|]+/', (string) $valeur) ?: [])
            ->map(fn ($segment) => trim($segment))
            ->filter()
            ->map(function (string $segment) use ($schoolId) {
                $quantite = 1;
                if (preg_match('/^(.*?)(?:\s*x\s*|\s+)(\d+)$/iu', $segment, $m)) {
                    $segment = trim($m[1]);
                    $quantite = max(1, (int) $m[2]);
                }

                $article = InventaireArticle::where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
                    ->get()
                    ->first(fn (InventaireArticle $article) => self::cle($article->nom) === self::cle($segment) || self::cle($article->code_barre) === self::cle($segment));

                return $article ? ['inventaire_article_id' => $article->id, 'quantite' => $quantite] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function cle(mixed $valeur): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii((string) $valeur))) ?? '';
    }

    private function dateHeure(mixed $valeur): ?string
    {
        if (is_numeric($valeur)) {
            return ExcelDate::excelToDateTimeObject((float) $valeur)->format('Y-m-d H:i:s');
        }

        return $valeur ? date('Y-m-d H:i:s', strtotime((string) $valeur)) : null;
    }
}
