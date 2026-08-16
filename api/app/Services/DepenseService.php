<?php

namespace App\Services;

use App\Models\CompteComptable;
use App\Models\Depense;
use App\Models\EcritureComptable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Dépenses de l'établissement.
 *
 * Une dépense engagée n'est pas une dépense payée : la première grève un
 * budget, la seconde sort de la caisse. Seule la seconde mouvemente la
 * trésorerie — c'est ce qui permet au suivi de distinguer ce qui est promis de
 * ce qui est parti.
 *
 * Comme les encaissements, une dépense ne se supprime pas : elle porte un
 * justificatif et une écriture, on l'annule par contrepassation.
 */
class DepenseService extends BaseService
{
    private const COMPTES_TRESORERIE = [
        'especes' => '571',
        'mobile_money' => '578',
        'virement' => '521',
        'cheque' => '521',
        'depot_bancaire' => '521',
    ];

    /** Repli quand aucun compte n'est choisi : achats de fournitures. */
    private const COMPTE_PAR_DEFAUT = '601';

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function enregistrer(int $schoolId, array $donnees, ?int $saisiPar = null, ?UploadedFile $justificatif = null): Depense
    {
        return $this->transaction(function () use ($schoolId, $donnees, $saisiPar, $justificatif) {
            $depense = Depense::create([
                'school_id' => $schoolId,
                'annee_scolaire_id' => $donnees['annee_scolaire_id'] ?? null,
                'compte_comptable_id' => $donnees['compte_comptable_id'] ?? $this->compte(self::COMPTE_PAR_DEFAUT),
                'date_depense' => $donnees['date_depense'] ?? Carbon::today()->toDateString(),
                'libelle' => $donnees['libelle'],
                'montant' => (int) $donnees['montant'],
                'mode' => $donnees['mode'] ?? 'especes',
                'beneficiaire' => $donnees['beneficiaire'] ?? null,
                'reference_facture' => $donnees['reference_facture'] ?? null,
                'responsable' => $donnees['responsable'] ?? null,
                'saisi_par' => $saisiPar,
                'statut' => $donnees['statut'] ?? 'payee',
                'justificatif_path' => $justificatif ? $this->stocker($schoolId, $justificatif) : null,
            ]);

            if ($depense->statut === 'payee') {
                $this->comptabiliser($depense);
            }

            return $depense;
        });
    }

    /**
     * Bascule une dépense engagée en dépense payée : c'est à ce moment que la
     * trésorerie bouge.
     */
    public function payer(Depense $depense, ?string $mode = null, ?string $date = null): Depense
    {
        if ($depense->statut === 'annulee') {
            throw new RuntimeException('Cette dépense est annulée.');
        }

        if ($depense->statut === 'payee') {
            throw new RuntimeException('Cette dépense est déjà payée.');
        }

        return $this->transaction(function () use ($depense, $mode, $date) {
            $depense->update([
                'statut' => 'payee',
                'mode' => $mode ?? $depense->mode,
                'date_depense' => $date ?? $depense->date_depense,
            ]);

            $this->comptabiliser($depense->fresh());

            return $depense->fresh();
        });
    }

    /** Le justificatif reste attaché à l'école : c'est une pièce comptable. */
    private function stocker(int $schoolId, UploadedFile $fichier): string
    {
        return $fichier->store("ecoles/{$schoolId}/justificatifs", 'public');
    }

    /**
     * Charge au débit, trésorerie au crédit : l'argent quitte la caisse pour
     * alimenter un poste de dépense.
     */
    private function comptabiliser(Depense $depense): void
    {
        $commun = [
            'school_id' => $depense->school_id,
            'annee_scolaire_id' => $depense->annee_scolaire_id,
            'date_ecriture' => $depense->date_depense,
            'montant' => $depense->montant,
            'origine_type' => $depense->getMorphClass(),
            'origine_id' => $depense->id,
        ];

        EcritureComptable::create($commun + [
            'libelle' => $depense->libelle,
            'sens' => 'debit',
            'compte_comptable_id' => $depense->compte_comptable_id,
        ]);

        EcritureComptable::create($commun + [
            'libelle' => 'Règlement — '.$depense->libelle,
            'sens' => 'credit',
            'compte_comptable_id' => $this->compte(self::COMPTES_TRESORERIE[$depense->mode] ?? '571'),
        ]);
    }

    public function annuler(Depense $depense, string $motif, ?int $annulePar = null): Depense
    {
        if ($depense->statut === 'annulee') {
            throw new RuntimeException('Cette dépense est déjà annulée.');
        }

        return $this->transaction(function () use ($depense, $motif, $annulePar) {
            $etaitPayee = $depense->statut === 'payee';

            $depense->update([
                'statut' => 'annulee',
                'annule_le' => now(),
                'annule_par' => $annulePar,
                'motif_annulation' => $motif,
            ]);

            // Une dépense seulement engagée n'a rien mouvementé : il n'y a
            // aucune écriture à contrepasser.
            if ($etaitPayee) {
                foreach (EcritureComptable::where('origine_type', $depense->getMorphClass())
                    ->where('origine_id', $depense->id)->get() as $ecriture) {
                    EcritureComptable::create([
                        'school_id' => $ecriture->school_id,
                        'annee_scolaire_id' => $ecriture->annee_scolaire_id,
                        'date_ecriture' => now()->toDateString(),
                        'libelle' => 'Annulation — '.$ecriture->libelle,
                        'montant' => $ecriture->montant,
                        'sens' => $ecriture->sens === 'debit' ? 'credit' : 'debit',
                        'compte_comptable_id' => $ecriture->compte_comptable_id,
                        'origine_type' => $depense->getMorphClass(),
                        'origine_id' => $depense->id,
                    ]);
                }
            }

            return $depense->fresh();
        });
    }

    public function supprimerJustificatif(Depense $depense): Depense
    {
        if ($depense->justificatif_path) {
            Storage::disk('public')->delete($depense->justificatif_path);
            $depense->update(['justificatif_path' => null]);
        }

        return $depense->fresh();
    }

    /**
     * Dépenses de la période, ventilées par compte : c'est la vue qu'attend le
     * bilan, et celle que lit le comptable.
     *
     * @param  array{du?: ?string, au?: ?string, compte_comptable_id?: ?int, statut?: ?string}  $filtres
     * @return array{depenses: Collection, par_compte: array<string, array>, totaux: array<string, int>}
     */
    public function bilan(int $schoolId, array $filtres = []): array
    {
        $depenses = Depense::forSchool($schoolId)
            ->when($filtres['du'] ?? null, fn ($q, $du) => $q->whereDate('date_depense', '>=', $du))
            ->when($filtres['au'] ?? null, fn ($q, $au) => $q->whereDate('date_depense', '<=', $au))
            ->when($filtres['compte_comptable_id'] ?? null, fn ($q, $id) => $q->where('compte_comptable_id', $id))
            ->when($filtres['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->with(['compte', 'saisisseur'])
            ->orderByDesc('date_depense')
            ->get();

        $retenues = $depenses->where('statut', '!=', 'annulee');

        $parCompte = $retenues
            ->groupBy(fn (Depense $d) => $d->compte?->code ?? '—')
            ->map(fn ($lot, $code) => [
                // PHP convertit les clés de tableau numériques en entiers :
                // sans ce cast, « 611 » sortirait en int et « — » en chaîne.
                'code' => (string) $code,
                'libelle' => $lot->first()->compte?->libelle ?? 'Non imputé',
                'nombre' => $lot->count(),
                'montant' => (int) $lot->sum('montant'),
            ])
            ->sortByDesc('montant')
            ->values()
            ->all();

        return [
            'depenses' => $depenses,
            'par_compte' => $parCompte,
            'totaux' => [
                'nombre' => $retenues->count(),
                'engage' => (int) $retenues->where('statut', 'engagee')->sum('montant'),
                'paye' => (int) $retenues->where('statut', 'payee')->sum('montant'),
                'total' => (int) $retenues->sum('montant'),
                'annule' => (int) $depenses->where('statut', 'annulee')->sum('montant'),
            ],
        ];
    }

    private function compte(string $code): ?int
    {
        return CompteComptable::where('code', $code)->value('id');
    }
}
