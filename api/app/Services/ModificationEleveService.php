<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\ModificationEleve;
use App\Models\Tuteur;
use RuntimeException;

/**
 * Révisions d'identité/santé proposées par un parent pour un enfant déjà
 * scolarisé — même logique de proposition/validation que
 * {@see PreinscriptionService}, mais bornée aux champs non financiers d'une
 * fiche existante : rien n'atteint `eleves` avant que {@see valider()} ne
 * l'applique.
 */
class ModificationEleveService extends BaseService
{
    public function __construct(private readonly NotificationService $notifications) {}

    private const CHAMPS_MODIFIABLES = [
        'nom_complet',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'adresse',
        'numero_acte_naissance',
        'lieu_delivrance_acte',
        'officier_etat_civil',
        'groupe_sanguin',
        'situation_sanitaire',
        'aptitude',
        'allergies',
    ];

    public function enAttentePour(int $eleveId): ?ModificationEleve
    {
        return ModificationEleve::where('eleve_id', $eleveId)->where('statut', 'en_attente')->latest()->first();
    }

    public function soumettre(Tuteur $tuteur, Eleve $eleve, array $donnees): ModificationEleve
    {
        if ($this->enAttentePour($eleve->id)) {
            throw new RuntimeException('Une demande de modification est déjà en attente de validation pour cet élève.');
        }

        $donnees = array_intersect_key($donnees, array_flip(self::CHAMPS_MODIFIABLES));

        if (empty($donnees)) {
            throw new RuntimeException('Aucune modification à transmettre.');
        }

        $modification = ModificationEleve::create([
            'school_id' => $eleve->school_id,
            'eleve_id' => $eleve->id,
            'tuteur_id' => $tuteur->id,
            'donnees' => $donnees,
            'statut' => 'en_attente',
        ]);

        $this->notifications->notifierParPermission(
            $eleve->school_id,
            'eleves.manage',
            'modification_eleve',
            'Modification de fiche proposée',
            "{$tuteur->nom_complet} propose une modification de la fiche de {$eleve->nom_complet}.",
        );

        return $modification;
    }

    public function valider(ModificationEleve $modification, ?int $adminUserId = null): ModificationEleve
    {
        if ($modification->statut !== 'en_attente') {
            throw new RuntimeException('Cette demande a déjà été traitée.');
        }

        return $this->transaction(function () use ($modification, $adminUserId) {
            $modification->eleve->update($modification->donnees);

            $modification->update([
                'statut' => 'validee',
                'traite_par' => $adminUserId,
                'traite_le' => now(),
            ]);

            return $modification->fresh();
        });
    }

    public function rejeter(ModificationEleve $modification, string $motif, ?int $adminUserId = null): ModificationEleve
    {
        if ($modification->statut !== 'en_attente') {
            throw new RuntimeException('Cette demande a déjà été traitée.');
        }

        $modification->update([
            'statut' => 'rejetee',
            'motif_rejet' => $motif,
            'traite_par' => $adminUserId,
            'traite_le' => now(),
        ]);

        return $modification->fresh();
    }
}
