<?php

namespace App\Exports;

use App\Models\DetteAnterieure;
use App\Models\Eleve;
use App\Models\Moratoire;
use App\Models\Remise;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Dettes antérieures, remises accordées et moratoires de paiement — trois régimes distincts, réunis ici par type pour garder une vue d'ensemble en une seule feuille. */
class EleveHistoriqueFinancierSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param Collection<int, Eleve> $eleves */
    public function __construct(private readonly Collection $eleves) {}

    public function array(): array
    {
        $eleveIds = $this->eleves->pluck('id');
        $eleveParId = $this->eleves->keyBy('id');
        $lignes = [];

        $dettes = DetteAnterieure::whereIn('eleve_id', $eleveIds)->with('accordePar')->get();
        foreach ($dettes as $dette) {
            $eleve = $eleveParId->get($dette->eleve_id);
            $lignes[] = [
                $eleve?->matricule, $eleve?->nom_complet, 'Dette antérieure',
                $dette->montant, $dette->motif, null, null,
                $dette->imputee_dossier_id ? 'Imputée' : 'Non imputée', $dette->accordePar?->name,
            ];
        }

        $remises = Remise::whereIn('eleve_id', $eleveIds)->with(['accordePar', 'anneeScolaire'])->get();
        foreach ($remises as $remise) {
            $eleve = $eleveParId->get($remise->eleve_id);
            $lignes[] = [
                $eleve?->matricule, $eleve?->nom_complet, 'Remise',
                $remise->montant, $remise->motif, $remise->anneeScolaire?->libelle, null,
                null, $remise->accordePar?->name,
            ];
        }

        $moratoires = Moratoire::whereIn('eleve_id', $eleveIds)->with('accordePar')->get();
        foreach ($moratoires as $moratoire) {
            $eleve = $eleveParId->get($moratoire->eleve_id);
            $lignes[] = [
                $eleve?->matricule, $eleve?->nom_complet, 'Moratoire',
                null, $moratoire->motif, null,
                $moratoire->date_delivrance?->format('Y-m-d').' → '.$moratoire->date_expiration?->format('Y-m-d'),
                $moratoire->valide ? 'Valide' : 'Expiré', $moratoire->accordePar?->name,
            ];
        }

        return $lignes;
    }

    public function headings(): array
    {
        return [
            'Matricule', 'Nom complet', 'Type', 'Montant', 'Motif', 'Année scolaire',
            'Période de validité', 'Statut', 'Accordé par',
        ];
    }

    public function title(): string
    {
        return 'Dettes, remises, moratoires';
    }
}
