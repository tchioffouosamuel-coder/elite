<?php

namespace App\Exports;

use App\Models\BusArret;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export des arrêts, dans la forme exacte que relit {@see \App\Imports\BusArretImport} :
 * le fichier produit ici se corrige dans un tableur et se réimporte tel quel.
 * C'est la seule façon de rendre l'import utilisable en pratique — un
 * établissement ne ressaisit pas ses circuits dans un gabarit vide, il part
 * de ce qu'il a déjà et le retouche (même principe que {@see MatiereExport}).
 *
 * Le nom du trajet, et non son id, sert de rattachement : c'est la clé que
 * l'import résout (les arrêts n'ont pas de `school_id` propre, cf.
 * {@see \App\Models\BusTrajet::scopeForSchool} — la flotte est partagée par
 * tout le complexe, l'export n'a donc rien à cloisonner).
 */
class BusArretExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function headings(): array
    {
        // Relus par l'import après passage au slug (« Lieu dit » => `lieu_dit`,
        // « Tarif aller simple » => `tarif_aller_simple`) : les renommer
        // casserait l'aller-retour, cf. BusArretImport.
        return [
            'Trajet',
            'Nom',
            'Lieu dit',
            'Ordre',
            'Heure passage',
            'Tarif aller simple',
            'Tarif retour simple',
            'Tarif aller retour',
        ];
    }

    public function collection(): Collection
    {
        return BusArret::with('trajet:id,nom')
            ->join('bus_trajets', 'bus_trajets.id', '=', 'bus_arrets.trajet_id')
            ->orderBy('bus_trajets.nom')
            ->orderBy('bus_arrets.ordre')
            ->select('bus_arrets.*')
            ->get();
    }

    /** @param BusArret $ligne */
    public function map($ligne): array
    {
        return [
            $ligne->trajet?->nom,
            $ligne->nom,
            $ligne->lieu_dit,
            $ligne->ordre,
            $ligne->heure_passage,
            $ligne->tarif_aller_simple,
            $ligne->tarif_retour_simple,
            $ligne->tarif_aller_retour,
        ];
    }
}
