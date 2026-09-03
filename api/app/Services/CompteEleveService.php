<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Ouverture des accès du portail élève, sur le même principe que
 * {@see CompteParentService} pour le portail parent.
 *
 * L'identifiant est le matricule de l'élève, pas un téléphone ni un e-mail :
 * un élève n'en a pas forcément un à lui, alors que le matricule existe déjà
 * pour chacun ({@see Eleve::genererMatricule()}), imprimé sur sa carte
 * scolaire. Le matricule n'est unique que PAR ÉCOLE (compteur remis à zéro
 * par établissement) : c'est l'unicité de connexion — un seul compte actif
 * par valeur de matricule, tous établissements confondus — qui est vérifiée
 * ici avant toute ouverture, pas l'unicité du matricule lui-même.
 */
class CompteEleveService extends BaseService
{
    /**
     * Ouvre l'accès de l'élève s'il n'en a pas encore, et le renvoie.
     * Idempotent, comme {@see CompteParentService::assurer()}.
     */
    public function assurer(Eleve $eleve): User
    {
        if ($eleve->user_id !== null) {
            return $eleve->user;
        }

        $matricule = trim((string) $eleve->matricule);

        if ($matricule === '') {
            throw new RuntimeException("Cet élève n'a pas de matricule : impossible d'ouvrir son accès.");
        }

        if (Eleve::where('matricule', $matricule)->whereNotNull('user_id')->exists()) {
            throw new RuntimeException("Le matricule {$matricule} est déjà utilisé par un compte actif sur un autre dossier.");
        }

        return $this->transaction(function () use ($eleve) {
            $user = User::create([
                'name' => $eleve->nom_complet,
                'password' => Hash::make($this->motDePasseDefaut($eleve->school_id)),
                'school_id' => $eleve->school_id,
                'is_active' => true,
                'doit_changer_mot_de_passe' => true,
            ]);
            $user->assignRole('eleve');

            $eleve->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    /**
     * Ouvre l'accès de tous les élèves de l'école qui n'en ont pas encore —
     * même logique de rattrapage que {@see CompteParentService::assurerLot()}.
     *
     * @param  int|list<int>  $schoolId
     * @return array{crees: int, ignores: list<array{eleve: string, motif: string}>}
     */
    public function assurerLot(int|array $schoolId): array
    {
        $eleves = Eleve::forSchool($schoolId)->whereNull('user_id')->get();

        $crees = 0;
        $ignores = [];

        foreach ($eleves as $eleve) {
            try {
                $this->assurer($eleve);
                $crees++;
            } catch (RuntimeException $e) {
                $ignores[] = ['eleve' => $eleve->nom_complet, 'motif' => $e->getMessage()];
            }
        }

        return ['crees' => $crees, 'ignores' => $ignores];
    }

    /**
     * Comptes élèves actifs de l'établissement, pour le document
     * d'identifiants — même limite que {@see CompteParentService::identifiants()} :
     * le mot de passe n'est communicable que tant qu'il n'a pas été personnalisé.
     */
    public function identifiants(int|array $schoolId): array
    {
        $comptes = User::query()
            ->when(is_array($schoolId), fn ($query) => $query->whereIn('school_id', $schoolId), fn ($query) => $query->where('school_id', $schoolId))
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'eleve'))
            ->with('eleve:id,user_id,matricule')
            ->orderBy('name')
            ->get();

        return [
            'comptes' => $comptes,
            'total' => $comptes->count(),
            'mot_de_passe_defaut' => $this->motDePasseDefaut(is_array($schoolId) ? $schoolId[0] : $schoolId),
        ];
    }

    private function motDePasseDefaut(int $schoolId): string
    {
        return Setting::get($schoolId, 'mot_de_passe_defaut', SettingsCatalog::default('mot_de_passe_defaut'));
    }
}
