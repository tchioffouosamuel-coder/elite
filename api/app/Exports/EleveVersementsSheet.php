<?php

namespace App\Exports;

use App\Models\Eleve;
use App\Models\Versement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Historique complet des encaissements de scolarité, y compris les versements annulés (pour traçabilité). */
class EleveVersementsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveParId = $this->eleves->keyBy('id');

        $versements = Versement::whereHas('dossier', fn ($q) => $q->whereIn('eleve_id', $this->eleves->pluck('id')))
            ->with(['dossier.anneeScolaire', 'encaisseur'])
            ->orderByDesc('date_versement')
            ->get();

        return $versements->map(function (Versement $versement) use ($eleveParId) {
            $eleve = $eleveParId->get($versement->dossier?->eleve_id);

            return [
                $eleve?->matricule,
                $eleve?->nom_complet,
                $versement->dossier?->anneeScolaire?->libelle,
                $versement->date_versement?->format('Y-m-d'),
                $versement->numero_recu,
                $versement->montant,
                $versement->mode,
                $versement->encaisseur?->name,
                $versement->estAnnule() ? 'Oui' : 'Non',
                $versement->motif_annulation,
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom complet', 'Année scolaire', 'Date de versement', 'N° reçu', 'Montant',
            'Mode de paiement', 'Encaissé par', 'Annulé', 'Motif annulation',
        ];
    }

    public function title(): string
    {
        return 'Versements';
    }
}
