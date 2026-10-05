<?php

namespace App\Exports;

use App\Imports\CompetenceImport;
use App\Models\ClasseCompetence;
use App\Models\Competence;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export des compétences, dans la forme exacte que relit {@see CompetenceImport} :
 * le fichier produit ici se corrige dans un tableur et se réimporte tel quel.
 * C'est la seule façon de rendre l'import utilisable en pratique — un
 * établissement ne saisit pas son référentiel à la main dans un gabarit vide,
 * il part de ce qu'il a déjà et le retouche.
 *
 * **Une ligne par attribution**, pas par compétence : le barème et la
 * répartition des volets varient d'une classe à l'autre, et une ligne unique
 * portant « CP-A ; CE1-B ; CM2 » ne saurait pas dire lequel s'applique où.
 * Une compétence encore attribuée à aucune classe sort sur une seule ligne,
 * colonnes de classe et de barème vides.
 */
class CompetenceExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId) {}

    public function headings(): array
    {
        // Ces intitulés sont relus par l'import après passage au slug
        // (« Savoir-etre » => `savoir_etre`) : les renommer casserait
        // l'aller-retour, cf. CompetenceImport::COLONNES.
        return CompetenceImport::enTetes();
    }

    public function collection(): Collection
    {
        $competences = Competence::forSchool($this->schoolId)
            ->orderBy('ordre')
            ->orderBy('label_fr')
            ->get();

        $attributions = ClasseCompetence::whereIn('competence_id', $competences->pluck('id'))
            ->with('classe:id,nom')
            ->get()
            ->groupBy('competence_id');

        return $competences->flatMap(function (Competence $competence) use ($attributions) {
            $lignes = ($attributions->get($competence->id) ?? collect())
                ->sortBy(fn (ClasseCompetence $cc) => $cc->classe?->nom)
                ->map(fn (ClasseCompetence $cc) => $this->ligne($competence, $cc))
                ->values();

            return $lignes->isEmpty() ? collect([$this->ligne($competence, null)]) : $lignes;
        });
    }

    /** @return list<mixed> */
    private function ligne(Competence $competence, ?ClasseCompetence $attribution): array
    {
        // Répartition réellement enregistrée, pas celle calculée à parts
        // égales : réexporter un partage implicite le figerait en réglage
        // explicite au réimport suivant.
        $volets = $attribution?->repartition_volets ?? [];

        return [
            $competence->label_fr,
            $competence->label_en,
            $competence->abbreviation,
            $competence->ordre,
            $attribution?->classe?->nom,
            $attribution?->notation,
            $volets['oral'] ?? null,
            $volets['ecrit'] ?? null,
            $volets['savoir_etre'] ?? null,
            $volets['pratique'] ?? null,
        ];
    }
}
