<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\CataloguePermissions;
use App\Support\FonctionRoles;
use App\Support\Perimetre;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'locale', 'is_active', 'school_id', 'niveau_id', 'doit_changer_mot_de_passe'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Périmètre résolu une seule fois par requête (cf. perimetre()). */
    private ?Perimetre $perimetre = null;

    /** @var Collection<int, string>|null */
    private ?Collection $permissionsDeBase = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function estSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** Fonction du référentiel portée par l'agent, quand le compte en représente un. */
    public function fonction(): ?FonctionReferentiel
    {
        return $this->personnel?->fonctionReference;
    }

    /**
     * Libellé humain de « qui est ce compte » pour l'affichage (journal
     * d'activité, listes d'identifiants…) : la fonction réelle (« Directeur »)
     * prime sur le rôle technique, qui ne sert que de repli pour un compte
     * sans fiche personnel (ex. super administrateur).
     */
    public function libelleRole(): ?string
    {
        $fonction = $this->fonction()?->label();

        if ($fonction) {
            return $fonction;
        }

        $role = $this->getRoleNames()->first();

        return $role ? Str::of($role)->replace('_', ' ')->title()->toString() : null;
    }

    /**
     * Exerce une fonction d'enseignement — au sens où « Ma journée » (tableau
     * de bord personnel de saisie d'appel) lui est réservé. Un censeur ou un
     * économe peut porter `appel.manage` sans être enseignant pour autant :
     * la fonction tranche là où le seul privilège ne le permettrait pas.
     */
    public function estEnseignant(): bool
    {
        return FonctionRoles::role($this->fonction()?->label_fr) === 'enseignant';
    }

    /**
     * Rôle vendeur/caissier : son accueil (mobile et web) montre ses ventes
     * et le stock du comptoir, jamais les effectifs élèves/personnel — hors
     * de son périmètre métier. Basé sur le rôle attribué (pas la fonction du
     * personnel) : c'est lui qui porte réellement les privilèges `point_de_vente.*`.
     */
    public function estVendeur(): bool
    {
        return $this->hasRole('vendeur');
    }

    /**
     * Privilèges détenus « en propre » : attribution directe, rôle, et groupe
     * de privilèges de la fonction.
     *
     * Le cumul est volontaire. La fonction dit ce que fait le métier (« un
     * censeur saisit des notes »), le rôle et les attributions directes gèrent
     * les cas particuliers (« ce censeur-là tient aussi la caisse ») sans
     * obliger à créer une fonction par exception.
     *
     * Ces privilèges-là valent sur tout le périmètre du compte, à la
     * différence de ceux qu'une attribution nominative confère classe par
     * classe — d'où la séparation avec {@see permissionsEffectives()}.
     *
     * @return Collection<int, string>
     */
    public function permissionsDeBase(): Collection
    {
        // Mémorisé : le contrôle de périmètre les consulte plusieurs fois par
        // requête (middleware, puis service), et `getAllPermissions()` relit
        // rôles et attributions à chaque appel.
        return $this->permissionsDeBase ??= $this->estSuperAdmin()
            ? collect(CataloguePermissions::codes())
            : $this->getAllPermissions()
                ->pluck('name')
                ->merge($this->fonction()?->codesPermissions() ?? [])
                ->unique()
                ->sort()
                ->values();
    }

    /**
     * Privilèges effectifs, toutes provenances confondues : les précédents,
     * plus ceux que confèrent les responsabilités confiées à l'agent
     * (professeur principal, surveillant général, censeur, conseiller
     * d'orientation, chef de département).
     *
     * C'est ce qui permet à un **enseignant** désigné surveillant général
     * d'une classe de franchir le middleware de `discipline.manage` sans qu'on
     * ait à changer sa fonction : le privilège existe pour lui, mais il ne
     * porte que sur les classes qu'il surveille — cf. {@see peutSurClasse()},
     * seul juge dès qu'une classe est en cause.
     *
     * @return Collection<int, string>
     */
    public function permissionsEffectives(): Collection
    {
        if ($this->estSuperAdmin()) {
            return collect(CataloguePermissions::codes());
        }

        return $this->permissionsDeBase()
            ->merge($this->perimetre()->permissions())
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Étendue du compte : classes enseignées, classes et départements confiés.
     * Mémorisé le temps de la requête — chaque appel interroge sinon les
     * classes, les affectations et les départements.
     */
    public function perimetre(): Perimetre
    {
        return $this->perimetre ??= new Perimetre($this);
    }

    /**
     * Le compte peut-il exercer ce privilège sur cette classe précise ? À
     * préférer à {@see aLaPermission()} partout où une classe est en jeu :
     * c'est la seule vérification qui distingue les six classes d'un
     * surveillant général du reste de l'établissement.
     */
    public function peutSurClasse(string $permission, int $classeId): bool
    {
        return $this->perimetre()->peutSurClasse($permission, $classeId);
    }

    /** Même question, pour les routes qui nomment un département. */
    public function peutSurDepartement(string $permission, int $departementId): bool
    {
        return $this->perimetre()->peutSurDepartement($permission, $departementId);
    }

    /**
     * Seul point de vérité pour « ce compte a-t-il le droit de… ». Le
     * middleware, les services et la ressource JSON s'y réfèrent tous, pour
     * qu'un droit accordé via la fonction se comporte exactement comme un droit
     * accordé via le rôle.
     */
    public function aLaPermission(string $code): bool
    {
        return $this->estSuperAdmin() || $this->permissionsEffectives()->contains($code);
    }

    /**
     * Établissements sur lesquels le compte peut travailler. Le super
     * administrateur voit les trois écoles du complexe et bascule de l'une à
     * l'autre ; tout autre compte reste sur le sien, vers lequel il est
     * directement redirigé à la connexion.
     *
     * @return Collection<int, School>
     */
    public function ecolesAccessibles(): Collection
    {
        if (! $this->hasRole('super_admin')) {
            return $this->school ? collect([$this->school]) : collect();
        }

        return School::where('is_active', true)
            ->when($this->school?->complexe_id, fn ($q, $id) => $q->where('complexe_id', $id))
            ->orderBy('type')
            ->get();
    }
}
