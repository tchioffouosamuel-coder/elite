<?php

namespace App\Exports;

use App\Models\BusAffectation;
use App\Services\BusService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Souscriptions bus actives : une ligne par élève transporté, avec sa situation de paiement. */
class BusSouscriptionExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param int|array<int> $schoolId */
    public function __construct(private readonly int|array $schoolId, private readonly BusService $service) {}

    public function collection(): Collection
    {
        return $this->service->listerAffectations($this->schoolId)
            ->filter(fn (BusAffectation $a) => $a->statut === 'actif')
            ->values();
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom', 'Classe', 'Bus', 'Arrêt', 'Option', 'Tarif', 'Total payé', 'Reste à payer', 'Statut paiement'];
    }

    public function map($affectation): array
    {
        /** @var BusAffectation $affectation */
        return [
            $affectation->eleve->matricule,
            $affectation->eleve->nom_complet,
            $affectation->eleve->classe?->nom,
            $affectation->trajet->nom,
            $affectation->arret?->nom,
            $affectation->option_trajet,
            $affectation->tarif_mensuel,
            $affectation->total_paye,
            $affectation->reste_a_payer,
            $affectation->statut_paiement,
        ];
    }
}
