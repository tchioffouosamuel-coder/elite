<?php

namespace App\Services;

use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\ProgressionItem;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Journée de l'enseignant : ce qu'il a enseigné et qui était là.
 *
 * Uniforme sur tous les cycles : l'emploi du temps fait foi partout. « Ma
 * journée » ne propose donc que les créneaux réellement prévus ce jour-là, à
 * leur horaire réel — jamais un cours ou un horaire inventé, que ce soit en
 * maternelle, au primaire ou au secondaire.
 */
class MaJourneeService extends BaseService
{
    /** Rôles notifiés à chaque validation de leçon — cf. User::estPersonnelDirection(). */
    private const ROLES_DIRECTION = ['super_admin', 'admin_ecole', 'admin_college', 'censeur_sg'];

    public function __construct(
        private readonly EmploiDuTempsService $emploiDuTemps,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Affectations sur lesquelles l'enseignant peut travailler à la date
     * donnée (aujourd'hui par défaut) — uniquement celles prévues ce jour-là
     * dans l'emploi du temps.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function mesAffectations(User $user, int $schoolId, ?string $date = null): Collection
    {
        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return collect();
        }

        $classeMatieres = ClasseMatiere::forSchool($schoolId)
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                // Le titulaire du primaire enseigne toutes les matières de sa
                // classe sans être nommé sur chaque affectation.
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->with(['classe', 'matiere'])
            ->get();

        $jour = Carbon::parse($date ?? now())->dayOfWeekIso;

        // Le premier créneau du jour par affectation : « Ma journée » ne garde
        // qu'une séance par (classe_matiere, date) quel que soit le nombre de
        // périodes ce jour-là — lister chaque période séparément ferait
        // croire à des séances distinctes qui n'existeraient pas.
        $creneaux = EmploiDuTemps::whereIn('classe_matiere_id', $classeMatieres->pluck('id'))
            ->where('jour', $jour)
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('classe_matiere_id')
            ->map(fn ($groupe) => $groupe->first());

        return $classeMatieres
            ->filter(fn (ClasseMatiere $cm) => $creneaux->has($cm->id))
            ->map(function (ClasseMatiere $cm) use ($creneaux) {
                $creneau = $creneaux->get($cm->id);

                return [
                    'classe_matiere_id' => $cm->id,
                    'classe_id' => $cm->classe->id,
                    'classe' => $cm->classe->nom,
                    'matiere' => $cm->matiere->nom,
                    'heure_debut' => substr((string) $creneau->heure_debut, 0, 5),
                    'heure_fin' => substr((string) $creneau->heure_fin, 0, 5),
                ];
            })
            ->sortBy('heure_debut')
            ->values();
    }

    /**
     * Séance du jour pour une affectation, créée à la volée si l'enseignant
     * n'en a pas encore ouvert une : il déclare ce qu'il vient de faire, il
     * n'a pas à planifier d'abord. L'horaire vient toujours du créneau réel
     * de l'emploi du temps — jamais d'un horaire par défaut inventé, qui
     * rendrait la déclaration incohérente avec la grille.
     */
    public function seanceDuJour(ClasseMatiere $classeMatiere, string $date): Seance
    {
        $classe = $classeMatiere->classe;

        $creneau = EmploiDuTemps::where('classe_matiere_id', $classeMatiere->id)
            ->where('jour', Carbon::parse($date)->dayOfWeekIso)
            ->orderBy('heure_debut')
            ->first();

        if (! $creneau) {
            throw new RuntimeException(
                "Aucun créneau n'est prévu pour {$classeMatiere->matiere->nom} ce jour-là dans l'emploi du temps."
            );
        }

        // whereDate plutôt que firstOrCreate() : le cast `date` écrit
        // « Y-m-d H:i:s », qu'une égalité stricte sur « Y-m-d » ne retrouve pas
        // hors MySQL — chaque enregistrement recréerait alors une séance.
        $existante = Seance::where('classe_matiere_id', $classeMatiere->id)
            ->whereDate('date_seance', $date)
            ->first();

        return $existante ?? Seance::create(
            [
                'classe_matiere_id' => $classeMatiere->id,
                'date_seance' => $date,
                'school_id' => $classe->school_id,
                'classe_id' => $classe->id,
                'trimestre_id' => $this->trimestreDe($classe, $date)?->id,
                'heure_debut' => $creneau->heure_debut,
                'heure_fin' => $creneau->heure_fin,
                'statut' => 'prevue',
            ]
        );
    }

    /**
     * Vue globale, pour l'administration : tous les cours prévus dans l'école
     * à une date donnée, avec leur statut réel — le pendant transverse de
     * `mesAffectations()`, qui ne montre que celles de l'utilisateur
     * connecté. Sert la page de consultation quotidienne de la direction,
     * qui peut ensuite ouvrir n'importe lequel via `feuilleDuJour()` /
     * `enregistrer()` ci-dessus (cf. `peutIntervenir()`, déjà ouvert à ces
     * rôles pour toute affectation).
     *
     * Un seul créneau par affectation ce jour-là, comme `mesAffectations()` :
     * lister chaque période séparément ferait croire à des cours distincts
     * qui n'existeraient pas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function coursDuJour(int $schoolId, string $date): Collection
    {
        $jour = Carbon::parse($date)->dayOfWeekIso;

        $creneaux = EmploiDuTemps::forSchool($schoolId)
            ->where('jour', $jour)
            ->whereNotNull('classe_matiere_id')
            ->with(['classe.titulaire', 'classeMatiere.matiere', 'classeMatiere.enseignant'])
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('classe_matiere_id')
            ->map(fn (Collection $groupe) => $groupe->first());

        $seances = Seance::forSchool($schoolId)
            ->whereDate('date_seance', $date)
            ->whereIn('classe_matiere_id', $creneaux->pluck('classe_matiere_id'))
            ->withCount(['lecons', 'presences'])
            ->get()
            ->keyBy('classe_matiere_id');

        $estAujourdhui = Carbon::parse($date)->isToday();
        $maintenant = Carbon::now()->format('H:i:s');

        return $creneaux
            ->map(function (EmploiDuTemps $creneau) use ($schoolId, $date, $seances, $estAujourdhui, $maintenant) {
                // Un seul créneau mal formé (donnée orpheline, relation
                // inattendue) ne doit pas faire échouer toute la vue
                // transverse de la direction — on l'écarte et on garde une
                // trace exploitable plutôt que de laisser planter la requête.
                try {
                    $classeMatiere = $creneau->classeMatiere;
                    $classe = $creneau->classe;
                    $seance = $seances->get($creneau->classe_matiere_id);
                    $enseignant = $classeMatiere?->enseignant ?? $classe?->titulaire;

                    // Sans séance déclarée, le cours reste « prévu » jusqu'à
                    // son heure de fin passée, au-delà de laquelle il est en
                    // retard — uniquement pertinent pour aujourd'hui, une
                    // date passée sans séance étant simplement restée non
                    // couverte.
                    $enRetard = $estAujourdhui && ! $seance && $maintenant > (string) $creneau->heure_fin;

                    return [
                        'classe_matiere_id' => $creneau->classe_matiere_id,
                        'classe_id' => $classe?->id,
                        'classe' => $classe?->nom,
                        'matiere' => $classeMatiere?->matiere?->nom,
                        'enseignant' => $enseignant?->nom_complet,
                        'heure_debut' => substr((string) $creneau->heure_debut, 0, 5),
                        'heure_fin' => substr((string) $creneau->heure_fin, 0, 5),
                        'salle' => $creneau->salle,
                        'seance_id' => $seance?->id,
                        'statut' => $seance?->statut ?? ($enRetard ? 'en_retard' : 'prevue'),
                        'lecons_traitees' => $seance?->lecons_count ?? 0,
                        'eleves_pointes' => $seance?->presences_count ?? 0,
                        'verrouille' => $seance?->appelVerrouille() ?? false,
                    ];
                } catch (Throwable $e) {
                    Log::error('ma-journee.ecole : créneau ignoré après erreur', [
                        'school_id' => $schoolId,
                        'date' => $date,
                        'emploi_du_temps_id' => $creneau->id,
                        'classe_matiere_id' => $creneau->classe_matiere_id,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile().':'.$e->getLine(),
                    ]);

                    return null;
                }
            })
            ->filter()
            ->sortBy('heure_debut')
            ->values();
    }

    /** Trimestre couvrant la date, sinon celui qui est actif. */
    private function trimestreDe(Classe $classe, string $date): ?Trimestre
    {
        $query = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->where('school_id', $classe->school_id)
        );

        return (clone $query)->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first()
            ?? (clone $query)->where('is_active', true)->first();
    }

    /**
     * Feuille du jour : leçons du programme à cocher et appel de la classe.
     */
    public function feuilleDuJour(ClasseMatiere $classeMatiere, Seance $seance, User $user): array
    {
        // Leçon cochée sur cette séance => auteur de la validation (null pour
        // les validations antérieures à la colonne `valide_par`).
        $faites = $seance->lecons()->get(['progression_items.id'])
            ->mapWithKeys(fn (ProgressionItem $l) => [$l->id => $l->pivot->valide_par]);
        $validateurs = User::with('personnel')->whereIn('id', $faites->filter()->unique())->get()->keyBy('id');
        $validees = $faites->map(fn (?int $userId) => $userId !== null && $validateurs->has($userId) ? self::nomDe($validateurs->get($userId)) : null);

        $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->lecons()
            ->with('parent.parent', 'sequence')
            ->withCount('seances')
            ->orderBy('ordre')->orderBy('id')
            ->get()
            ->map(fn (ProgressionItem $lecon) => [
                'id' => $lecon->id,
                'titre' => $lecon->titre,
                // Le chemin situe la leçon dans le programme : sans lui, une
                // liste plate de titres est illisible dès qu'ils se ressemblent.
                'chemin' => collect([$lecon->parent?->parent?->titre, $lecon->parent?->titre])
                    ->filter()->implode(' › '),
                'sequence' => $lecon->sequence?->libelle,
                'faite_aujourdhui' => $faites->has($lecon->id),
                // Enseignant ou direction : la leçon peut être validée par l'un
                // comme par l'autre, l'écran doit dire lequel.
                'validee_par' => $validees->get($lecon->id),
                'deja_traitee' => $lecon->seances_count > 0,
            ]);

        return [
            'seance' => [
                'id' => $seance->id,
                'date' => $seance->date_seance->format('Y-m-d'),
                'heure_debut' => $seance->heure_debut,
                'heure_fin' => $seance->heure_fin,
                'statut' => $seance->statut,
                'observations' => $seance->observations,
                'donnees_personnalisees' => $seance->donnees_personnalisees ?? [],
                // L'appel ET les leçons cochées se soumettent ensemble ici : le
                // même verrou couvre les deux, un délai après la première
                // déclaration de cette séance (cf. `enregistrer()`), réglable
                // par école/sous-système — cf. `RegleValidationSeance`.
                'verrouille' => $seance->appelVerrouillePour($user),
                'aujourdhui' => $seance->estAujourdhui(),
                'modifiable_jusqua' => $seance->appel_verrouille_le
                    ?->addMinutes($seance->minutesVerrouillageAppel())
                    ?->toIso8601String(),
            ],
            'lecons' => $lecons,
            'appel' => $this->emploiDuTemps->feuilleAppel($seance)->map(fn ($ligne) => [
                'eleve_id' => $ligne['eleve']->id,
                'nom_complet' => $ligne['eleve']->nom_complet,
                'matricule' => $ligne['eleve']->matricule,
                'statut' => $ligne['statut'],
                'motif' => $ligne['motif'],
                'pointe' => $ligne['pointe'],
            ])->values(),
            // Tableaux d'informations spécifiques à la matière : définis une fois
            // par l'enseignant, remplis à chaque séance.
            'champs_personnalises' => ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
                ->orderBy('ordre')->orderBy('id')
                ->get(['id', 'libelle', 'type']),
        ];
    }

    /**
     * Enregistre la journée : leçons traitées, appel, observations et champs
     * personnalisés.
     *
     * @param  array<int, int>  $leconIds
     * @param  array<int, array<string, mixed>>  $appel
     * @param  array<string, mixed>  $donneesPersonnalisees
     * @return array{lecons: int, eleves: int}
     */
    public function enregistrer(
        ClasseMatiere $classeMatiere,
        Seance $seance,
        array $leconIds,
        array $appel,
        User $user,
        ?string $observations = null,
        array $donneesPersonnalisees = [],
        bool $qrVerifie = false,
    ): array {
        // L'appel et les leçons cochées se soumettent depuis le même écran :
        // un seul verrou couvre les deux, sans quoi un enseignant pourrait
        // continuer à ajouter des leçons « traitées » bien après la fenêtre de
        // correction de l'appel — ou l'inverse. Le super admin peut outrepasser
        // ce verrou (cf. Seance::appelVerrouillePour()).
        abort_if(
            $seance->appelVerrouillePour($user),
            403,
            'La déclaration de cette séance est verrouillée depuis plus de '.$seance->minutesVerrouillageAppel().' minutes. Contactez le Surveillant Général pour une correction.'
        );

        [$resultat, $nouvelles] = $this->transaction(function () use ($classeMatiere, $seance, $leconIds, $appel, $user, $observations, $donneesPersonnalisees, $qrVerifie) {
            // Une leçon d'un autre programme n'a rien à faire dans cette séance.
            $valides = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
                ->lecons()
                ->whereIn('id', $leconIds)
                ->pluck('id');

            // Pas de sync() : il réécrirait `valide_par` sur les leçons déjà
            // cochées. Une correction ultérieure (par la direction, par
            // exemple) ne doit pas s'approprier ce que l'enseignant a validé —
            // seules les leçons nouvellement cochées portent l'auteur actuel.
            $existantes = $seance->lecons()->pluck('progression_items.id');
            $nouvelles = $valides->diff($existantes)->values();

            $seance->lecons()->detach($existantes->diff($valides)->all());
            $seance->lecons()->attach($nouvelles->all(), ['valide_par' => $user->id]);

            // « Date Taught » suit désormais la séance qui a réellement couvert
            // la leçon plutôt que de rester à la charge du professeur : c'est
            // exactement l'information que cette déclaration vient d'apporter.
            if ($valides->isNotEmpty()) {
                ProgressionItem::whereIn('id', $valides)->update(['date_realisee' => $seance->date_seance]);
            }

            $eleves = $appel === [] ? 0 : $this->emploiDuTemps->enregistrerAppel($seance, $appel);

            $seance->update([
                'statut' => 'effectuee',
                'observations' => $observations,
                'donnees_personnalisees' => $donneesPersonnalisees ?: null,
                // Figé une seule fois, que l'appel ait été rempli ou non : une
                // déclaration de leçons seule doit tout autant se verrouiller.
                'appel_verrouille_le' => $seance->appel_verrouille_le ?? now(),
                // Preuve de présence : figée au premier scan validé, jamais
                // effacée par une correction ultérieure sans scan (direction).
                'qr_verifie_le' => $qrVerifie ? ($seance->qr_verifie_le ?? now()) : $seance->qr_verifie_le,
            ]);

            return [['lecons' => $valides->count(), 'eleves' => $eleves], $nouvelles];
        });

        // Hors transaction : un échec d'envoi (push) ne doit pas annuler la
        // déclaration elle-même.
        if ($nouvelles->isNotEmpty()) {
            $this->notifierValidation($classeMatiere, $seance, $nouvelles, $user);
        }

        return $resultat;
    }

    /**
     * Prévient la direction de l'école qu'une ou plusieurs leçons viennent
     * d'être validées, en précisant qui l'a fait — l'enseignant lui-même ou un
     * administrateur à sa place. L'auteur n'est pas notifié de sa propre action.
     *
     * @param  Collection<int, int>  $leconIds
     */
    private function notifierValidation(ClasseMatiere $classeMatiere, Seance $seance, Collection $leconIds, User $auteur): void
    {
        $destinataires = User::where('school_id', $seance->school_id)
            ->where('id', '!=', $auteur->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ROLES_DIRECTION))
            ->pluck('id');

        if ($destinataires->isEmpty()) {
            return;
        }

        $titres = ProgressionItem::whereIn('id', $leconIds)->orderBy('ordre')->orderBy('id')->pluck('titre');
        $classeMatiere->loadMissing('classe', 'matiere');
        $cours = "{$classeMatiere->classe?->nom} — {$classeMatiere->matiere?->nom}";
        $date = $seance->date_seance->format('Y-m-d');

        $this->notifications->notifier(
            $seance->school_id,
            $destinataires,
            'lecon_validee',
            $titres->count() === 1 ? 'Leçon validée' : "{$titres->count()} leçons validées",
            self::nomDe($auteur)." a validé en {$cours} (séance du ".$seance->date_seance->format('d/m/Y').') : '
                .$titres->implode(', ').'.',
            "/journee-ecole?date={$date}&classe_matiere_id={$classeMatiere->id}",
        );
    }

    /** Nom affiché d'un utilisateur : celui de sa fiche personnel, à défaut celui du compte. */
    private static function nomDe(User $user): string
    {
        return $user->personnel?->nom_complet ?? $user->name;
    }

    /**
     * Heures de couverture de l'enseignant depuis le début de l'année : ce qui
     * était prévu à son emploi du temps jusqu'à aujourd'hui, comparé à ce
     * qu'il a réellement déclaré. Une séance « prévue » restée sans appel
     * après sa date signale un cours non couvert.
     *
     * @return array{heures_prevues: float, heures_realisees: float, taux: float, seances_en_retard: int}
     */
    public function heuresCouverture(User $user, int $schoolId): array
    {
        $personnelId = $user->personnel?->id;

        if ($personnelId === null) {
            return ['heures_prevues' => 0.0, 'heures_realisees' => 0.0, 'taux' => 0.0, 'seances_en_retard' => 0];
        }

        $classeMatiereIds = ClasseMatiere::forSchool($schoolId)
            ->where('statut', 'actif')
            ->where(fn ($q) => $q
                ->where('personnel_id', $personnelId)
                ->orWhereHas('classe', fn ($c) => $c->where('titulaire_id', $personnelId)))
            ->pluck('id');

        $seances = Seance::whereIn('classe_matiere_id', $classeMatiereIds)
            ->whereDate('date_seance', '<=', now())
            ->get(['statut', 'heure_debut', 'heure_fin', 'date_seance']);

        $prevues = (float) $seances->sum(fn (Seance $s) => $s->dureeHeures());
        $realisees = (float) $seances->where('statut', 'effectuee')->sum(fn (Seance $s) => $s->dureeHeures());
        $enRetard = $seances->where('statut', 'prevue')
            ->filter(fn (Seance $s) => $s->date_seance->lt(now()->startOfDay()))
            ->count();

        return [
            'heures_prevues' => round($prevues, 1),
            'heures_realisees' => round($realisees, 1),
            'taux' => $prevues > 0 ? round($realisees / $prevues * 100, 1) : 0.0,
            'seances_en_retard' => $enRetard,
        ];
    }

    /**
     * Créneau en cours dans une salle, résolu depuis son emploi du temps —
     * c'est ce que le QR code affiché au mur ouvre en le faisant scanner.
     * Une marge de 10 minutes de part et d'autre couvre le temps d'installation.
     */
    public function creneauActuel(Classe $classe): ?ClasseMatiere
    {
        $maintenant = now();

        $creneau = EmploiDuTemps::where('classe_id', $classe->id)
            ->where('jour', $maintenant->dayOfWeekIso)
            ->get()
            ->first(function (EmploiDuTemps $c) use ($maintenant) {
                $debut = Carbon::parse($c->heure_debut)->subMinutes(10);
                $fin = Carbon::parse($c->heure_fin)->addMinutes(10);
                $heure = Carbon::parse($maintenant->format('H:i:s'));

                return $heure->between($debut, $fin);
            });

        return $creneau?->classeMatiere;
    }

    /** L'enseignant ne peut déclarer que sur ses propres affectations. */
    public function peutIntervenir(User $user, ClasseMatiere $classeMatiere): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin_ecole', 'admin_college', 'censeur_sg'])) {
            return true;
        }

        $personnelId = $user->personnel?->id;

        return $personnelId !== null && (
            $classeMatiere->personnel_id === $personnelId
            || $classeMatiere->classe->titulaire_id === $personnelId
        );
    }
}
