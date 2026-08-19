<?php

namespace App\Support;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Étendue réelle d'un compte : les classes et les départements sur lesquels il
 * a quelque chose à faire, et ce qu'il peut y faire.
 *
 * Le catalogue des privilèges répond à « ce compte a-t-il le droit de saisir
 * une sanction ? ». Il ne répond pas à « … pour cette classe-là ? », qui est
 * pourtant la vraie question dès qu'un établissement répartit ses classes
 * entre plusieurs surveillants généraux ou plusieurs censeurs. C'est ce que
 * cette classe ajoute, en deux temps :
 *
 * 1. **Le périmètre** — l'union des classes où l'agent enseigne, de celles
 *    qu'on lui a nommément attribuées, et de celles couvertes par un
 *    département qu'il dirige.
 * 2. **Les privilèges d'attribution** — ceux que confère chaque attribution,
 *    valables sur ses seules classes ({@see peutSurClasse()}).
 *
 * Un compte qui administre l'établissement (`ecoles.manage`) ou une fonction
 * transverse (économe, infirmier, secrétaire) n'est pas borné : son travail
 * porte sur l'école entière, pas sur une liste de classes.
 */
class Perimetre
{
    /**
     * Fonctions dont l'étendue est nominative : elles n'ouvrent que ce qui a
     * été confié à l'agent. Les autres (économe, infirmier, documentaliste…)
     * travaillent sur l'établissement entier et restent hors bornage — leur
     * appliquer un périmètre de classes viderait la caisse ou l'infirmerie de
     * leurs listes.
     */
    private const ROLES_BORNES = ['enseignant', 'censeur_sg', 'surveillant_general'];

    /** @var array<string, list<int>> code d'attribution => ids de classes */
    private ?array $attributions = null;

    /** @var list<int>|null */
    private ?array $classesEnseignees = null;

    /** @var list<int>|null */
    private ?array $departements = null;

    public function __construct(private readonly User $user) {}

    /** Fiche personnel du compte, seule porteuse des attributions. */
    private function personnelId(): ?int
    {
        return $this->user->personnel?->id;
    }

    /**
     * Classes attribuées nominativement, par code d'attribution. Les codes
     * sans aucune classe sont absents : « il est surveillant général » et
     * « il ne surveille rien » ne doivent pas se ressembler à la lecture.
     *
     * @return array<string, list<int>>
     */
    public function attributions(): array
    {
        if ($this->attributions !== null) {
            return $this->attributions;
        }

        $personnelId = $this->personnelId();

        if ($personnelId === null) {
            return $this->attributions = [];
        }

        $codes = Attributions::surClasse();
        $colonnes = array_map(Attributions::colonne(...), $codes);

        // Une seule lecture pour les quatre responsabilités de classe : le
        // périmètre est consulté à chaque requête, en multiplier les allers
        // vers la base pour quatre colonnes de la même ligne serait gratuit.
        $lignes = Classe::query()
            ->where(function ($query) use ($colonnes, $personnelId) {
                foreach ($colonnes as $colonne) {
                    $query->orWhere($colonne, $personnelId);
                }
            })
            ->get(['id', ...$colonnes]);

        $attributions = [];

        foreach ($codes as $code) {
            $ids = $lignes
                ->where(Attributions::colonne($code), $personnelId)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $attributions[$code] = $ids;
            }
        }

        if ($this->departementsDiriges() !== []) {
            $attributions[Attributions::CHEF_DEPARTEMENT] = $this->classesDuDepartement();
        }

        return $this->attributions = $attributions;
    }

    /** @return list<int> */
    public function departementsDiriges(): array
    {
        if ($this->departements !== null) {
            return $this->departements;
        }

        $personnelId = $this->personnelId();

        return $this->departements = $personnelId === null
            ? []
            : Departement::where('head_personnel_id', $personnelId)->pluck('id')->all();
    }

    /**
     * Classes où l'agent enseigne : affectation matière au secondaire,
     * titulariat au primaire et en maternelle — où il tient toute la classe
     * sans être nommé sur chaque matière.
     *
     * @return list<int>
     */
    public function classesEnseignees(): array
    {
        if ($this->classesEnseignees !== null) {
            return $this->classesEnseignees;
        }

        $personnelId = $this->personnelId();

        if ($personnelId === null) {
            return $this->classesEnseignees = [];
        }

        $affectees = ClasseMatiere::where('personnel_id', $personnelId)
            ->where('statut', 'actif')
            ->pluck('classe_id');

        $tenues = Classe::where('titulaire_id', $personnelId)->pluck('id');

        return $this->classesEnseignees = $affectees->merge($tenues)->unique()->values()->all();
    }

    /**
     * Classes où intervient au moins une matière d'un département dirigé par
     * l'agent : le chef de département suit ses disciplines partout où elles
     * sont enseignées, pas seulement là où il enseigne lui-même.
     *
     * @return list<int>
     */
    public function classesDuDepartement(): array
    {
        $departements = $this->departementsDiriges();

        if ($departements === []) {
            return [];
        }

        return ClasseMatiere::whereIn(
            'matiere_id',
            Matiere::whereIn('departement_id', $departements)->select('id')
        )->distinct()->pluck('classe_id')->all();
    }

    /**
     * Le compte ne voit-il que ce qui lui est confié ?
     *
     * Non pour un super administrateur et pour qui administre l'établissement
     * (`ecoles.manage` : principal, directeur) ; non plus pour les fonctions
     * transverses. Oui pour les quatre métiers dont l'étendue est nominative :
     * enseignant, censeur, surveillant général, conseiller d'orientation.
     */
    public function estBorne(): bool
    {
        if ($this->user->estSuperAdmin() || $this->user->permissionsDeBase()->contains('ecoles.manage')) {
            return false;
        }

        return in_array(FonctionRoles::role($this->user->fonction()?->label_fr), self::ROLES_BORNES, true);
    }

    /**
     * Toutes les classes du périmètre, ou `null` quand le compte n'est pas
     * borné — `null` et « aucune classe » sont deux réponses opposées, un
     * tableau vide ne peut pas porter les deux.
     *
     * @return list<int>|null
     */
    public function classes(): ?array
    {
        if (! $this->estBorne()) {
            return null;
        }

        return collect($this->attributions())
            ->flatten()
            ->merge($this->classesEnseignees())
            ->unique()
            ->values()
            ->all();
    }

    public function couvre(int $classeId): bool
    {
        $classes = $this->classes();

        return $classes === null || in_array($classeId, $classes, true);
    }

    /**
     * Attributions qui relèvent de la fonction même du compte : c'est par
     * elles qu'un agent dont le métier *est* surveillant général ou censeur
     * reçoit ses classes. Un enseignant, lui, tient les siennes de son
     * service d'enseignement, pas d'une attribution.
     *
     * @return list<string>
     */
    private function attributionsDeSaFonction(): array
    {
        return match (FonctionRoles::role($this->user->fonction()?->label_fr)) {
            'censeur_sg' => [Attributions::CENSEUR],
            // « Conseiller d'orientation » partage le rôle du surveillant
            // général : les deux attributions lui ouvrent son propre métier.
            'surveillant_general' => [Attributions::SURVEILLANT_GENERAL, Attributions::CONSEILLER_ORIENTATION],
            default => [],
        };
    }

    /**
     * Classes sur lesquelles portent les privilèges de la **fonction** —
     * distinctes de celles que couvre une attribution.
     *
     * C'est la nuance qui fait tout le flux : un enseignant nommé surveillant
     * général d'une classe garde ses prérogatives d'enseignant « dans les
     * classes où il intervient » et non partout où il a affaire. Il ne notera
     * donc pas la classe qu'il surveille sans y enseigner, quand bien même
     * elle est dans son périmètre. Un agent dont la fonction est surveillant
     * général ou censeur, lui, n'enseigne pas : ses classes de base sont
     * exactement celles qui lui ont été assignées à ce titre.
     *
     * `null` quand le compte n'est pas borné : ses privilèges valent partout.
     *
     * @return list<int>|null
     */
    public function classesDeBase(): ?array
    {
        if (! $this->estBorne()) {
            return null;
        }

        $classes = $this->classesEnseignees();

        foreach ($this->attributionsDeSaFonction() as $code) {
            $classes = array_merge($classes, $this->attributions()[$code] ?? []);
        }

        return array_values(array_unique($classes));
    }

    /** Porte-t-il cette attribution — sur cette classe, ou sur au moins une ? */
    public function aLAttribution(string $code, ?int $classeId = null): bool
    {
        $classes = $this->attributions()[$code] ?? [];

        return $classeId === null ? $classes !== [] : in_array($classeId, $classes, true);
    }

    /**
     * Privilèges conférés par les attributions, toutes classes confondues.
     * Ils entrent dans les privilèges effectifs du compte — c'est ce qui fait
     * qu'un enseignant nommé surveillant général franchit le middleware de
     * `discipline.manage` — mais restent bornés à leurs classes par
     * {@see peutSurClasse()}.
     *
     * @return Collection<int, string>
     */
    public function permissions(): Collection
    {
        return collect(array_keys($this->attributions()))
            ->flatMap(Attributions::permissions(...))
            ->unique()
            ->values();
    }

    /**
     * Le compte peut-il exercer ce privilège **sur cette classe** ?
     *
     * Deux voies, cumulatives :
     * - le privilège vient de sa fonction ou de son rôle, et la classe relève
     *   de ce métier ({@see classesDeBase()}) ;
     * - le privilège vient d'une attribution qui couvre précisément cette
     *   classe — un surveillant général tient la discipline des classes qu'il
     *   surveille, pas de celles où il se trouve enseigner ; et réciproquement
     *   il n'y saisit pas de notes.
     */
    public function peutSurClasse(string $permission, int $classeId): bool
    {
        if ($this->user->estSuperAdmin()) {
            return true;
        }

        $deBase = $this->classesDeBase();

        if ($this->user->permissionsDeBase()->contains($permission)
            && ($deBase === null || in_array($classeId, $deBase, true))) {
            return true;
        }

        foreach ($this->attributions() as $code => $classes) {
            if (in_array($classeId, $classes, true) && in_array($permission, Attributions::permissions($code), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Départements que le compte peut consulter, ou `null` s'il les voit tous.
     *
     * Restreint au seul cas où l'accès au module vient de l'attribution :
     * l'enseignant nommé chef de département y entre par cette porte et n'a
     * affaire qu'au sien. Une fonction qui ouvre déjà le personnel (censeur,
     * direction) garde sa vue d'ensemble — la lui retirer serait une
     * régression, pas une clarification.
     *
     * @return list<int>|null
     */
    public function departements(): ?array
    {
        if (! $this->estBorne() || $this->user->permissionsDeBase()->contains('personnel.view')) {
            return null;
        }

        return $this->departementsDiriges();
    }

    /**
     * Pendant de {@see peutSurClasse()} pour les routes qui nomment un
     * département (fiche, statistiques pédagogiques).
     */
    public function peutSurDepartement(string $permission, int $departementId): bool
    {
        if ($this->user->estSuperAdmin() || $this->user->permissionsDeBase()->contains($permission)) {
            return true;
        }

        return in_array($departementId, $this->departementsDiriges(), true)
            && in_array($permission, Attributions::permissions(Attributions::CHEF_DEPARTEMENT), true);
    }

    /**
     * Résumé destiné aux clients : ce que l'agent s'est vu confier, pour qu'ils
     * composent sa navigation sans recharger chaque liste. Le chef de
     * département y porte en plus ses départements — ce sont eux qu'il
     * administre, les classes n'en sont que la retombée.
     *
     * @return list<array{code: string, libelle: string, portee: string, classes: list<int>, departements: list<int>}>
     */
    public function resume(?string $locale = null): array
    {
        return collect($this->attributions())
            ->map(fn (array $classes, string $code) => [
                'code' => $code,
                'libelle' => Attributions::libelle($code, $locale),
                'portee' => Attributions::portee($code),
                'classes' => $classes,
                'departements' => $code === Attributions::CHEF_DEPARTEMENT ? $this->departementsDiriges() : [],
            ])
            ->values()
            ->all();
    }
}
