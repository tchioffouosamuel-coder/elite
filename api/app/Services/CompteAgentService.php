<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\Setting;
use App\Models\User;
use App\Support\Telephone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ouverture automatique des accès du personnel.
 *
 * Un agent recevait son compte à la main, fiche par fiche, en choisissant son
 * adresse et son rôle : sur un effectif repris d'un coup par import, personne
 * ne pouvait se connecter tant que quelqu'un n'avait pas répété l'opération
 * quarante fois. L'accès est désormais ouvert à la création comme à l'import.
 *
 * Aucun rôle n'est attribué : les droits viennent de la fonction de l'agent
 * (cf. `fonction_permission` et User::permissionsEffectives). Un enseignant a
 * les prérogatives d'un enseignant parce qu'il exerce cette fonction, pas
 * parce qu'on a pensé à cocher le bon rôle — et changer les droits d'une
 * catégorie entière reste une seule opération, depuis l'écran des privilèges.
 */
class CompteAgentService extends BaseService
{
    /**
     * Comptes actifs de l'établissement, pour le document d'identifiants de
     * connexion — le mot de passe n'est communicable que pour les comptes qui
     * portent encore celui, commun, distribué à l'ouverture de l'accès : une
     * fois personnalisé, il est haché et personne, pas même l'administrateur,
     * ne peut plus le connaître.
     */
    public function identifiants(int|array $schoolId): array
    {
        $comptes = User::query()
            ->when(is_array($schoolId), fn($query) => $query->whereIn('school_id', $schoolId), fn($query) => $query->where('school_id', $schoolId))
            ->where('is_active', true)
            ->whereHas('personnel')
            ->with('personnel.fonctionReference')
            ->orderBy('name')
            ->get();

        return [
            'comptes' => $comptes,
            'total' => $comptes->count(),
            // Appelée par établissement même en mode agrégé (une fois par
            // école, cf. PersonnelController::identifiants) : le premier id
            // suffit dans le cas — improbable — d'un tableau.
            'mot_de_passe_defaut' => $this->motDePasseDefaut(is_array($schoolId) ? $schoolId[0] : $schoolId),
        ];
    }

    /**
     * Ouvre l'accès de l'agent s'il n'en a pas encore, et le renvoie.
     *
     * Idempotent : rappelée sur un agent déjà pourvu, elle ne fait rien. C'est
     * ce qui permet de la brancher sur l'import, où la même fiche est
     * réenregistrée à chaque passage.
     */
    public function assurer(Personnel $personnel): ?User
    {
        if ($personnel->user_id !== null) {
            return $personnel->user;
        }

        // Un agent sorti des effectifs n'a pas à recevoir un accès : l'import
        // reprend aussi les contrats terminés.
        if ($personnel->statut !== 'actif') {
            return null;
        }

        $email = $this->email($personnel);
        $phone = $this->phone($personnel);

        return $this->transaction(function () use ($personnel, $email, $phone) {
            $user = User::create([
                'name' => $personnel->nom_complet,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($this->motDePasseDefaut($personnel->school_id)),
                'school_id' => $personnel->school_id,
                'is_active' => true,
                // Le mot de passe est commun à tout l'établissement : il ne
                // vaut que pour entrer une fois et le remplacer.
                'doit_changer_mot_de_passe' => true,
            ]);

            $personnel->forceFill(['user_id' => $user->id, 'email' => $email])->save();

            return $user;
        });
    }

    /**
     * Numéro de la fiche, normalisé, pour permettre au personnel de se
     * connecter par téléphone comme les parents (cf. {@see CompteParentService}).
     * L'email reste l'identifiant garanti — un numéro absent ou déjà pris par
     * un autre compte (parent ou agent) ne bloque donc pas l'ouverture de
     * l'accès, il est simplement laissé vide.
     */
    private function phone(Personnel $personnel): ?string
    {
        $saisi = trim((string) $personnel->telephone);

        if ($saisi === '') {
            return null;
        }

        $normalise = Telephone::normaliser($saisi);

        return User::where('phone', $normalise)->exists() ? null : $normalise;
    }

    /**
     * Rattrape les comptes personnel ouverts avant que la connexion par
     * téléphone n'existe : leur `User::phone` est resté vide alors que la
     * fiche porte un numéro. Contrairement à {@see CompteParentService::assurerLot()},
     * aucun compte n'est créé ici — seule une colonne est mise à jour sur des
     * comptes existants — donc pas de découpage en lots : `Hash::make()`
     * (le coût qui imposait les lots côté parents) n'entre pas en jeu.
     *
     * @param  int|array<int>  $schoolId
     * @return array{maj: int, ignores: list<array{personnel: string, motif: string}>}
     */
    public function rattraperTelephones(int|array $schoolId): array
    {
        $personnels = Personnel::query()
            ->forSchool($schoolId)
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        $maj = 0;
        $ignores = [];

        foreach ($personnels as $personnel) {
            $user = $personnel->user;

            if ($user === null || $user->phone !== null) {
                continue;
            }

            $saisi = trim((string) $personnel->telephone);

            if ($saisi === '') {
                $ignores[] = ['personnel' => $personnel->nom_complet, 'motif' => 'Aucun numéro renseigné sur la fiche.'];

                continue;
            }

            $normalise = Telephone::normaliser($saisi);

            if (User::where('phone', $normalise)->exists()) {
                $ignores[] = ['personnel' => $personnel->nom_complet, 'motif' => "Le numéro {$saisi} est déjà utilisé par un autre compte."];

                continue;
            }

            $user->forceFill(['phone' => $normalise])->save();
            $maj++;
        }

        return ['maj' => $maj, 'ignores' => $ignores];
    }

    /**
     * Adresse déjà portée par la fiche si elle est libre, sinon une adresse
     * dérivée du nom sur le domaine de l'établissement.
     */
    public function email(Personnel $personnel): string
    {
        $saisie = trim((string) $personnel->email);

        if ($saisie !== '' && ! $this->prise($saisie)) {
            return $saisie;
        }

        return $this->emailGenerique($personnel->nom_complet, $personnel->school_id);
    }

    /**
     * « AGBORNDE CATHERINE BESONG » → « agbornde.catherine.besong@elite.school ».
     *
     * Le nom complet est repris en entier plutôt que tronqué à un patronyme :
     * les homonymes sur le premier mot sont fréquents, et cette adresse sert
     * d'identifiant de connexion — l'agent doit pouvoir la retrouver seul. En
     * cas de collision malgré tout, un rang est ajouté.
     */
    private function emailGenerique(string $nomComplet, int $schoolId): string
    {
        $domaine = Setting::get($schoolId, 'domaine_email', SettingsCatalog::default('domaine_email'));
        $base = Str::slug($nomComplet, '.') ?: 'agent';

        $email = $base . '@' . $domaine;
        $rang = 1;

        while ($this->prise($email)) {
            $rang++;
            $email = $base . '.' . $rang . '@' . $domaine;
        }

        return $email;
    }

    private function prise(string $email): bool
    {
        return User::where('email', $email)->exists();
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
