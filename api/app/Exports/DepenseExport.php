<?php

namespace App\Exports;

use App\Models\Depense;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Excel des dépenses de la période — le pendant tableur du bilan PDF
 * ({@see \App\Support\Pdf\BilanDepensesGenerator}) : le PDF se classe, le
 * tableur se retrie et se recoupe avec la comptabilité.
 *
 * Reçoit la collection déjà filtrée par {@see \App\Services\DepenseService::bilan()}
 * plutôt que de refaire la requête : l'export rend exactement ce que l'écran
 * affiche, filtres de période, de statut et de recherche compris — un export
 * qui ignore les filtres visibles à l'écran donne un fichier que personne ne
 * sait rapprocher de ce qu'il vient de lire.
 *
 * Les en-têtes reprennent ceux que reconnaît {@see \App\Imports\DepenseImport}
 * (« Reference facture », « Compte comptable »…). Les colonnes qu'il ignore
 * (libellé du compte, saisi par, motif d'annulation) restent présentes : elles
 * servent à la lecture, et l'import les laisse simplement de côté.
 */
class DepenseExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    private const MODES = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
        'depot_bancaire' => 'Dépôt bancaire',
    ];

    private const SOURCES = [
        'caisse' => 'Caisse',
        'revenu_personnel' => 'Revenu personnel',
        'budget_personnel' => 'Budget alloué',
    ];

    private const STATUTS = [
        'engagee' => 'Engagée',
        'payee' => 'Payée',
        'annulee' => 'Annulée',
    ];

    /** @param Collection<int, Depense> $depenses */
    public function __construct(private readonly Collection $depenses) {}

    public function headings(): array
    {
        return [
            'Date',
            'Libellé',
            'Montant',
            'Compte',
            'Libellé du compte',
            'Source',
            'Mode',
            'Statut',
            'Bénéficiaire',
            'Reference facture',
            'Responsable',
            'Saisi par',
            'Motif annulation',
        ];
    }

    public function collection(): Collection
    {
        return $this->depenses;
    }

    /** @param Depense $ligne */
    public function map($ligne): array
    {
        return [
            $ligne->date_depense?->format('Y-m-d'),
            $ligne->libelle,
            // Montant brut, sans séparateur ni « FCFA » : une cellule que le
            // tableur doit pouvoir sommer, pas une étiquette à relire.
            (int) $ligne->montant,
            $ligne->compte?->code,
            $ligne->compte?->libelle,
            self::SOURCES[$ligne->source] ?? $ligne->source,
            self::MODES[$ligne->mode] ?? $ligne->mode,
            self::STATUTS[$ligne->statut] ?? $ligne->statut,
            $ligne->beneficiaire,
            $ligne->reference_facture,
            $ligne->responsable,
            $ligne->saisisseur?->name,
            $ligne->motif_annulation,
        ];
    }
}
