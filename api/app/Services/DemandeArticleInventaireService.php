<?php

namespace App\Services;

use App\Models\DemandeArticleInventaire;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Demandes d'ajout d'article d'inventaire soumises par un employé lui-même —
 * tout matériel que l'établissement lui a remis pour son travail, pas
 * seulement le médical — même logique de proposition/validation que
 * {@see DemandeAvanceSalaireService} : rien n'atteint `inventaire_articles`
 * avant que {@see valider()} ne crée réellement l'article via
 * InventaireService.
 */
class DemandeArticleInventaireService extends BaseService
{
    public function __construct(
        private readonly InventaireService $inventaire,
        private readonly NotificationService $notifications,
    ) {}

    /** @return Collection<int, DemandeArticleInventaire> */
    public function pourPersonnel(int $personnelId): Collection
    {
        return DemandeArticleInventaire::where('personnel_id', $personnelId)
            ->with('inventaireArticle')->latest()->get();
    }

    /** @param array{nom: string, categorie: string, quantite: int, etat?: ?string, localisation?: ?string, notes?: ?string} $donnees */
    public function soumettre(Personnel $personnel, array $donnees): DemandeArticleInventaire
    {
        $demande = DemandeArticleInventaire::create([
            'school_id' => $personnel->school_id,
            'personnel_id' => $personnel->id,
            'donnees' => $donnees,
            'statut' => 'en_attente',
        ]);

        $this->notifications->notifierParPermission(
            $personnel->school_id,
            'inventaire.manage',
            'demande_article_inventaire',
            "Demande d'article d'inventaire",
            "{$personnel->nom_complet} signale du matériel reçu de l'établissement : {$donnees['nom']} (x{$donnees['quantite']}).",
        );

        return $demande;
    }

    public function valider(DemandeArticleInventaire $demande, ?int $adminUserId = null): DemandeArticleInventaire
    {
        if ($demande->statut !== 'en_attente') {
            throw new RuntimeException('Cette demande a déjà été traitée.');
        }

        return $this->transaction(function () use ($demande, $adminUserId) {
            $article = $this->inventaire->creer($demande->school_id, [
                'nom' => $demande->donnees['nom'],
                'categorie' => $demande->donnees['categorie'] ?? 'autre',
                'quantite' => $demande->donnees['quantite'],
                'etat' => $demande->donnees['etat'] ?? 'bon',
                'localisation' => $demande->donnees['localisation'] ?? null,
                'notes' => $demande->donnees['notes'] ?? null,
            ]);

            $demande->update([
                'statut' => 'validee',
                'inventaire_article_id' => $article->id,
                'traite_par' => $adminUserId,
                'traite_le' => now(),
            ]);

            return $demande->fresh(['inventaireArticle']);
        });
    }

    public function rejeter(DemandeArticleInventaire $demande, string $motif, ?int $adminUserId = null): DemandeArticleInventaire
    {
        if ($demande->statut !== 'en_attente') {
            throw new RuntimeException('Cette demande a déjà été traitée.');
        }

        $demande->update([
            'statut' => 'rejetee',
            'motif_rejet' => $motif,
            'traite_par' => $adminUserId,
            'traite_le' => now(),
        ]);

        return $demande->fresh();
    }
}
