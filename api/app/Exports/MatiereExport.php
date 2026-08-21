<?php

namespace App\Exports;

use App\Models\ClasseMatiere;
use App\Models\Matiere;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export des matières, dans la forme exacte que relit MatiereImport : le
 * fichier produit ici se corrige dans un tableur et se réimporte tel quel.
 * C'est la seule façon de rendre l'import utilisable en pratique — un
 * établissement ne saisit pas son catalogue à la main dans un gabarit vide,
 * il part de ce qu'il a déjà et le retouche.
 *
 * **Une ligne par affectation**, pas par matière : le coefficient, le quota
 * horaire et l'enseignant varient d'une classe à l'autre, et une ligne unique
 * portant « 6e A ; 6e B ; 5e C » ne saurait pas dire lequel s'applique où. Une
 * matière encore rattachée à aucune classe sort sur une seule ligne, colonnes
 * d'affectation vides.
 *
 * Les colonnes des deux cycles cohabitent (département d'un côté, volets
 * d'évaluation de l'autre) : chacune reste vide là où elle n'a pas de sens, et
 * l'import ne lit que celles du cycle qu'on lui désigne. Deux exports séparés
 * auraient obligé à choisir un cycle pour un complexe qui en opère trois.
 */
class MatiereExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /** @param int|array<int> $schoolId */
    public function __construct(
        private readonly int|array $schoolId,
        private readonly ?int $classeId = null,
    ) {}

    public function headings(): array
    {
        // Ces intitulés sont relus par l'import après passage au slug
        // (« Nom (EN) » => `nom_en`, « Savoir-être » => `savoir_etre`) : les
        // renommer casserait l'aller-retour, cf. MatiereImport::COLONNES.
        return [
            'Nom',
            'Nom (EN)',
            'Abreviation',
            'Departement',
            'Classes',
            'Coefficient',
            'Periodes',
            'Enseignant',
            'Oral',
            'Ecrit',
            'Savoir-etre',
            'Pratique',
        ];
    }

    public function collection(): Collection
    {
        $matieres = Matiere::forSchool($this->schoolId)
            ->with('departement')
            ->when($this->classeId, fn($query) => $query->whereHas('classeMatieres', fn($affectations) => $affectations->where('classe_id', $this->classeId)))
            ->orderBy('nom')
            ->get();

        $affectations = ClasseMatiere::whereIn('matiere_id', $matieres->pluck('id'))
            ->when($this->classeId, fn($query) => $query->where('classe_id', $this->classeId))
            ->with(['classe:id,nom', 'enseignant:id,nom_complet'])
            ->get()
            ->groupBy('matiere_id');

        return $matieres->flatMap(function (Matiere $matiere) use ($affectations) {
            $lignes = ($affectations->get($matiere->id) ?? collect())
                ->sortBy(fn(ClasseMatiere $a) => $a->classe?->nom)
                ->map(fn(ClasseMatiere $a) => $this->ligne($matiere, $a))
                ->values();

            return $lignes->isEmpty() ? collect([$this->ligne($matiere, null)]) : $lignes;
        });
    }

    /** @return list<mixed> */
    private function ligne(Matiere $matiere, ?ClasseMatiere $affectation): array
    {
        $volets = $matiere->repartition_volets ?? [];

        return [
            $matiere->nom,
            $matiere->nom_en,
            $matiere->abbreviation,
            $matiere->departement?->nom,
            $affectation?->classe?->nom,
            $affectation?->coefficient,
            $affectation?->quota_horaire,
            $affectation?->enseignant?->nom_complet,
            $volets['oral'] ?? null,
            $volets['ecrit'] ?? null,
            $volets['savoir_etre'] ?? null,
            $volets['pratique'] ?? null,
        ];
    }
}
