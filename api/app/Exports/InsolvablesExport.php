<?php

namespace App\Exports;

use App\Services\ScolariteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** Liste des insolvables : une ligne par élève, avec son retard, son reste à payer et son moratoire éventuel. */
class InsolvablesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param list<int> $schoolIds */
    public function __construct(
        private readonly array $schoolIds,
        private readonly ?int $classeId,
        private readonly ScolariteService $service,
    ) {}

    public function collection(): Collection
    {
        return $this->service->insolvables($this->schoolIds, ['classe_id' => $this->classeId])['lignes'];
    }

    public function headings(): array
    {
        return ['Matricule', 'Nom', 'École', 'Classe', 'Reste à payer', 'Retard', 'Moratoire (expire le)'];
    }

    public function map($ligne): array
    {
        return [
            $ligne['eleve']['matricule'],
            $ligne['eleve']['nom_complet'],
            $ligne['school']['name'],
            $ligne['eleve']['classe'],
            $ligne['reste_a_payer'],
            $ligne['retard'],
            $ligne['moratoire']['date_expiration'] ?? null,
        ];
    }
}
