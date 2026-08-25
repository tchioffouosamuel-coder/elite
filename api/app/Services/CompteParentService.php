<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Ouverture des accès parent, sur le même principe que {@see CompteAgentService}
 * pour le personnel : mot de passe commun distribué à l'ouverture, à changer
 * dès la première connexion.
 *
 * L'identifiant est le numéro de téléphone, pas une adresse générée : un
 * parent n'a pas forcément d'e-mail, et celui de la fiche tuteur — quand il
 * existe — sert de contact, pas de compte de connexion. Le téléphone, en
 * revanche, est déjà ce que la famille communique à l'inscription.
 */
class CompteParentService extends BaseService
{
    /**
     * Ouvre l'accès du tuteur s'il n'en a pas encore, et le renvoie.
     * Idempotent, comme `CompteAgentService::assurer()`.
     */
    public function assurer(Tuteur $tuteur): User
    {
        if ($tuteur->user_id !== null) {
            return $tuteur->user;
        }

        $telephone = trim((string) $tuteur->telephone);

        if ($telephone === '') {
            throw new RuntimeException("Ce tuteur n'a pas de numéro de téléphone enregistré : renseignez-le avant d'ouvrir son accès.");
        }

        $telephoneNormalise = Telephone::normaliser($telephone);

        if (User::where('phone', $telephoneNormalise)->exists()) {
            throw new RuntimeException("Le numéro {$telephone} est déjà utilisé par un autre compte.");
        }

        return $this->transaction(function () use ($tuteur, $telephoneNormalise) {
            $user = User::create([
                'name' => $tuteur->nom_complet,
                'phone' => $telephoneNormalise,
                'password' => Hash::make($this->motDePasseDefaut($tuteur->school_id)),
                'school_id' => $tuteur->school_id,
                'is_active' => true,
                'doit_changer_mot_de_passe' => true,
            ]);
            $user->assignRole('parent');

            $tuteur->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    /**
     * Ouvre l'accès de tous les tuteurs de l'école qui n'en ont pas encore —
     * le rattrapage pour les familles inscrites avant que le portail parent
     * n'existe. Un tuteur sans numéro, ou dont le numéro est déjà pris par un
     * autre compte, est simplement ignoré plutôt que d'interrompre le lot :
     * une seule fiche mal renseignée ne doit pas priver toutes les autres
     * familles de leur accès.
     *
     * Accepte une liste d'écoles, comme {@see identifiants()} : le super admin
     * en mode agrégé voit les tuteurs de tout le complexe, et le rattrapage
     * doit couvrir le même périmètre que la liste qu'il a sous les yeux.
     *
     * @param  int|list<int>  $schoolId
     * @return array{crees: int, ignores: list<array{tuteur: string, motif: string}>}
     */
    public function assurerLot(int|array $schoolId): array
    {
        $tuteurs = Tuteur::forSchool($schoolId)->whereNull('user_id')->get();

        $crees = 0;
        $ignores = [];

        foreach ($tuteurs as $tuteur) {
            try {
                $this->assurer($tuteur);
                $crees++;
            } catch (RuntimeException $e) {
                $ignores[] = ['tuteur' => $tuteur->nom_complet, 'motif' => $e->getMessage()];
            }
        }

        return ['crees' => $crees, 'ignores' => $ignores];
    }

    /**
     * Identifiants des tuteurs sans accès, dans un ordre stable — calculée
     * une seule fois puis découpée en petits lots côté client (cf.
     * `assurerChunk()`). `Hash::make()` est délibérément coûteux (bcrypt) :
     * plusieurs centaines de tuteurs dans un seul lot dépassent facilement le
     * délai d'exécution du serveur en une requête, exactement le 408 que
     * connaissait `assurerLot()` sur un gros établissement.
     *
     * Un id de la liste n'est jamais retiré au fil des lots, même s'il
     * échoue systématiquement (tuteur sans numéro) : contrairement à
     * `whereNull('user_id')` relu à chaque appel, la liste ne bouge pas
     * d'un lot à l'autre, ce qui évite qu'un tel tuteur reste indéfiniment
     * dans le filtre et fasse tourner le rattrapage en boucle côté client.
     *
     * @param  int|array<int>  $schoolId
     * @return list<int>
     */
    public function tuteursSansCompte(int|array $schoolId): array
    {
        return Tuteur::forSchool($schoolId)->whereNull('user_id')->orderBy('id')->pluck('id')->all();
    }

    /**
     * Ouvre l'accès de ce seul lot de tuteurs — cf. `tuteursSansCompte()`
     * pour obtenir la liste complète à découper avant d'appeler celle-ci.
     *
     * @param  list<int>  $ids
     * @return array{crees: int, ignores: list<array{tuteur: string, motif: string}>}
     */
    public function assurerChunk(array $ids): array
    {
        $tuteurs = Tuteur::whereIn('id', $ids)->get();

        $crees = 0;
        $ignores = [];

        foreach ($tuteurs as $tuteur) {
            try {
                $this->assurer($tuteur);
                $crees++;
            } catch (RuntimeException $e) {
                $ignores[] = ['tuteur' => $tuteur->nom_complet, 'motif' => $e->getMessage()];
            }
        }

        return ['crees' => $crees, 'ignores' => $ignores];
    }

    /**
     * Comptes parents actifs de l'établissement, pour le document
     * d'identifiants — même limite que {@see CompteAgentService::identifiants()} :
     * le mot de passe n'est communicable que tant qu'il n'a pas été personnalisé.
     */
    public function identifiants(int|array $schoolId): array
    {
        $comptes = User::query()
            ->when(is_array($schoolId), fn ($query) => $query->whereIn('school_id', $schoolId), fn ($query) => $query->where('school_id', $schoolId))
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'parent'))
            ->orderBy('name')
            ->get();

        return [
            'comptes' => $comptes,
            'total' => $comptes->count(),
            // Appelée par établissement même en mode agrégé (une fois par
            // école, cf. TuteurController::identifiantsParentPdf) : le
            // premier id suffit dans le cas — improbable — d'un tableau.
            'mot_de_passe_defaut' => $this->motDePasseDefaut(is_array($schoolId) ? $schoolId[0] : $schoolId),
        ];
    }

    /**
     * Réglage de l'établissement plutôt que variable d'environnement : un
     * super admin le change depuis Paramètres sans jamais dépendre du devops
     * ou d'un accès au serveur — cf. SettingsCatalog.
     */
    private function motDePasseDefaut(int $schoolId): string
    {
        return Setting::get($schoolId, 'mot_de_passe_defaut', SettingsCatalog::default('mot_de_passe_defaut'));
    }
}
