<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DossierScolariteResource extends JsonResource
{
    /**
     * `avecRubriques` n'est demandé que sur la fiche d'un seul dossier (pour
     * l'écran d'encaissement) — l'inclure par défaut alourdirait chaque ligne
     * de la liste de recouvrement, qui n'en a pas l'usage.
     */
    public function __construct($resource, private readonly bool $avecRubriques = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve' => [
                'id' => $this->eleve?->id,
                'matricule' => $this->eleve?->matricule,
                'nom_complet' => $this->eleve?->nom_complet,
                'classe' => $this->eleve?->classe?->nom,
                'classe_id' => $this->eleve?->classe_id,
                // Sert aux relances : le contact principal de la famille.
                'contact' => $this->whenLoaded('eleve', fn () => $this->eleve->tuteurs->first()?->telephone),
            ],
            'montant_scolarite' => $this->montant_scolarite,
            'remise' => $this->remise,
            'report_dette' => $this->report_dette,
            'frais_annexes' => $this->whenLoaded('fraisAnnexes', fn () => $this->fraisAnnexes->map(fn ($f) => [
                'id' => $f->id,
                'libelle' => $f->libelle,
                'montant' => $f->montant,
            ])->values()),
            'bus' => $this->when($this->montant_bus > 0, fn () => [
                'trajet' => $this->bus_actif?->trajet?->nom,
                'option_trajet' => $this->bus_actif?->option_trajet,
                'montant' => $this->montant_bus,
            ]),
            'rubriques' => $this->when($this->avecRubriques, fn () => $this->rubriques),
            'total_du' => $this->total_du,
            'total_paye' => $this->total_paye,
            'reste_a_payer' => $this->reste_a_payer,
            'avance' => $this->avance,
            'statut_paiement' => $this->statut_paiement,
            'taux_recouvrement' => $this->taux_recouvrement,
            'versements' => $this->whenLoaded('versements', fn () => $this->versements->map(fn ($v) => [
                'id' => $v->id,
                'numero_recu' => $v->numero_recu,
                'date_versement' => $v->date_versement?->format('Y-m-d'),
                'montant' => $v->montant,
                'mode' => $v->mode,
                'annule' => $v->estAnnule(),
            ])->values()),
            'observation' => $this->observation,
        ];
    }
}
