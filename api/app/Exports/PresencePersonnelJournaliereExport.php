<?php

namespace App\Exports;

use App\Imports\PresencePersonnelJournaliereImport;
use App\Models\Personnel;
use App\Models\PresencePersonnelJournaliere;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PresencePersonnelJournaliereExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $schoolId,
        private readonly ?string $date = null,
        private readonly ?string $dateDebut = null,
        private readonly ?string $dateFin = null,
        private readonly bool $modeleDuJour = false,
    ) {}

    public function collection(): Collection
    {
        if ($this->modeleDuJour) {
            $date = $this->date ?? now()->format('Y-m-d');

            return Personnel::forSchool($this->schoolId)
                ->where('statut', 'actif')
                ->orderBy('nom_complet')
                ->get()
                ->map(fn (Personnel $personnel) => (object) [
                    'id' => null,
                    'date_presence' => $date,
                    'personnel' => $personnel,
                    'heure_arrivee' => null,
                    'heure_depart' => null,
                ]);
        }

        return PresencePersonnelJournaliere::forSchool($this->schoolId)
            ->with('personnel')
            ->when($this->date, fn ($q, $date) => $q->whereDate('date_presence', $date))
            ->when($this->dateDebut, fn ($q, $date) => $q->whereDate('date_presence', '>=', $date))
            ->when($this->dateFin, fn ($q, $date) => $q->whereDate('date_presence', '<=', $date))
            ->orderByDesc('date_presence')
            ->orderBy(Personnel::select('nom_complet')->whereColumn('personnels.id', 'presences_personnel_journalieres.personnel_id'))
            ->get();
    }

    public function headings(): array
    {
        return PresencePersonnelJournaliereImport::enTetes();
    }

    public function map($presence): array
    {
        return [
            $presence->id,
            is_string($presence->date_presence) ? $presence->date_presence : $presence->date_presence?->format('Y-m-d'),
            $presence->personnel?->matricule,
            $presence->personnel?->nom_complet,
            $presence->heure_arrivee ? substr((string) $presence->heure_arrivee, 0, 5) : null,
            $presence->heure_depart ? substr((string) $presence->heure_depart, 0, 5) : null,
        ];
    }
}
