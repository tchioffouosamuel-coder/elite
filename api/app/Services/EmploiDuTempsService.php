<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Trimestre;
use App\Services\Sms\SmsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmploiDuTempsService extends BaseService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly SmsService $sms,
        private readonly JustificationAbsenceService $justifications,
    ) {}

    /**
     * Emploi du temps d'une classe : ses propres créneaux, plus ceux qu'elle
     * rejoint en tronc commun. Une classe associée suit bien le cours — son
     * emploi du temps doit le montrer, même si le créneau est porté ailleurs.
     */
    public function grille(Classe $classe): Collection
    {
        return EmploiDuTemps::where(fn ($q) => $q
            ->where('classe_id', $classe->id)
            ->orWhereHas('classesAssociees', fn ($c) => $c->where('classes.id', $classe->id)))
            ->with(['classe', 'classesAssociees', 'classeMatiere.matiere', 'classeMatiere.enseignant'])
            ->orderBy('jour')->orderBy('heure_debut')
            ->get();
    }

    /**
     * Représentation d'un créneau pour l'API — partagée entre la vue
     * personnel (`EmploiDuTempsController`) et la vue lecture seule du
     * portail parent, pour ne pas faire dériver la liste des champs exposés
     * entre les deux.
     */
    public static function presenter(EmploiDuTemps $creneau): array
    {
        $creneau->loadMissing('classe', 'classesAssociees');

        return [
            'id' => $creneau->id,
            'jour' => $creneau->jour,
            'heure_debut' => substr((string) $creneau->heure_debut, 0, 5),
            'heure_fin' => substr((string) $creneau->heure_fin, 0, 5),
            'salle' => $creneau->salle,
            'classe_matiere_id' => $creneau->classe_matiere_id,
            'matiere' => $creneau->classeMatiere?->matiere?->nom,
            'enseignant' => $creneau->classeMatiere?->enseignant?->nom_complet,
            // La classe porteuse : sur la grille d'une classe associée, le
            // créneau vient d'ailleurs et l'écran doit pouvoir le dire.
            'classe_id' => $creneau->classe_id,
            'classe' => $creneau->classe?->nom,
            'classes_associees' => $creneau->classesAssociees
                ->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom])->values(),
            'tronc_commun' => $creneau->classesAssociees->isNotEmpty(),
        ];
    }

    /**
     * Copie des créneaux vers une autre classe, sans reprendre l'enseignant :
     * la classe cible garde ou choisit son propre professeur pour la
     * matière — recopier celui de la classe source imposerait un enseignant
     * qui n'y donne peut-être pas cours. La matière doit déjà être affectée
     * à la classe cible ({@see ClasseMatiere}) ; sinon elle y est créée sans
     * enseignant plutôt que d'échouer la copie.
     *
     * Un créneau qui chevaucherait un cours existant chez la cible, ou ferait
     * dépasser son quota horaire, est ignoré plutôt que d'interrompre la
     * copie des autres.
     *
     * @param  Collection<int, EmploiDuTemps>  $creneaux
     * @return array{0: int, 1: int} [copiés, ignorés]
     */
    public function copierVers(Collection $creneaux, Classe $classeCible): array
    {
        $copies = 0;
        $ignores = 0;

        foreach ($creneaux as $source) {
            $matiereId = $source->classeMatiere->matiere_id;

            $classeMatiereCible = ClasseMatiere::firstOrCreate(
                ['classe_id' => $classeCible->id, 'matiere_id' => $matiereId],
                ['coefficient' => $source->classeMatiere->coefficient],
            );

            if ($this->chevauche($classeCible, $source->jour, (string) $source->heure_debut, (string) $source->heure_fin)) {
                $ignores++;

                continue;
            }

            if ($this->depasseQuota($classeMatiereCible, (string) $source->heure_debut, (string) $source->heure_fin)) {
                $ignores++;

                continue;
            }

            EmploiDuTemps::create([
                'school_id' => $classeCible->school_id,
                'classe_id' => $classeCible->id,
                'classe_matiere_id' => $classeMatiereCible->id,
                'jour' => $source->jour,
                'heure_debut' => $source->heure_debut,
                'heure_fin' => $source->heure_fin,
                'salle' => $source->salle,
            ]);
            $copies++;
        }

        return [$copies, $ignores];
    }

    /**
     * Refuse un créneau qui empiète sur un autre créneau du même jour pour
     * l'une des classes concernées : deux cours simultanés rendraient l'appel
     * ambigu.
     *
     * Le contrôle porte sur toute la liste — classe porteuse et classes
     * associées — et regarde des deux côtés : un créneau existant gêne aussi
     * s'il ne fait que *rejoindre* l'une de ces classes en tronc commun.
     *
     * @param  list<int>  $classesAssociees
     */
    public function chevauche(
        Classe $classe,
        int $jour,
        string $debut,
        string $fin,
        ?int $ignorerId = null,
        array $classesAssociees = [],
    ): bool {
        $ids = collect([$classe->id])->concat($classesAssociees)->unique()->all();

        return EmploiDuTemps::where('jour', $jour)
            ->where(fn ($q) => $q
                ->whereIn('classe_id', $ids)
                ->orWhereHas('classesAssociees', fn ($c) => $c->whereIn('classes.id', $ids)))
            ->when($ignorerId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('heure_debut', '<', $fin)
            ->where('heure_fin', '>', $debut)
            ->exists();
    }

    /**
     * Refuse un créneau qui ferait dépasser le quota horaire hebdomadaire
     * défini pour la matière sur cette classe ({@see ClasseMatiere::$quota_horaire}).
     * Un quota non renseigné (null) n'impose aucune limite.
     *
     * @return string|null message d'erreur si le quota serait dépassé, sinon null
     */
    public function depasseQuota(
        ClasseMatiere $classeMatiere,
        string $debut,
        string $fin,
        ?int $ignorerId = null,
    ): ?string {
        if ($classeMatiere->quota_horaire === null) {
            return null;
        }

        $heuresExistantes = EmploiDuTemps::where('classe_matiere_id', $classeMatiere->id)
            ->when($ignorerId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get()
            ->sum(fn (EmploiDuTemps $c) => $this->dureeHeures((string) $c->heure_debut, (string) $c->heure_fin));

        $total = $heuresExistantes + $this->dureeHeures($debut, $fin);

        if ($total > $classeMatiere->quota_horaire) {
            $matiere = $classeMatiere->matiere?->nom ?? 'Cette matière';

            return sprintf(
                '%s dépasse son quota horaire hebdomadaire (%dh) : %sh seraient programmées.',
                $matiere,
                $classeMatiere->quota_horaire,
                rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.'),
            );
        }

        return null;
    }

    private function dureeHeures(string $debut, string $fin): float
    {
        return (strtotime($fin) - strtotime($debut)) / 3600;
    }

    /**
     * Matérialise les créneaux de la semaine en séances datées, prêtes à
     * recevoir l'appel. Les séances déjà créées ne sont pas dupliquées.
     *
     * @return int nombre de séances créées
     */
    public function genererSeances(Classe $classe, Carbon $debut, Carbon $fin, ?Trimestre $trimestre): int
    {
        /*
         * Seuls les créneaux *portés* par la classe engendrent des séances.
         * Un cours en tronc commun est porté une fois et une seule : générer
         * aussi depuis les classes associées créerait une séance par classe
         * pour un cours unique, donc autant d'appels que de classes.
         */
        $creneaux = EmploiDuTemps::where('classe_id', $classe->id)->get()->groupBy('jour');
        $creees = 0;

        for ($jour = $debut->copy()->startOfDay(); $jour->lte($fin); $jour->addDay()) {
            foreach ($creneaux->get($jour->dayOfWeekIso) ?? [] as $creneau) {
                // whereDate plutôt qu'une égalité : la colonne porte une heure à
                // zéro, qu'une comparaison avec 'Y-m-d' ne retrouverait pas.
                $existe = Seance::where('classe_id', $classe->id)
                    ->where('emploi_du_temps_id', $creneau->id)
                    ->whereDate('date_seance', $jour->toDateString())
                    ->exists();

                if ($existe) {
                    continue;
                }

                Seance::create([
                    'classe_id' => $classe->id,
                    'emploi_du_temps_id' => $creneau->id,
                    'date_seance' => $jour->toDateString(),
                    'school_id' => $classe->school_id,
                    'classe_matiere_id' => $creneau->classe_matiere_id,
                    'trimestre_id' => $trimestre?->id,
                    'heure_debut' => $creneau->heure_debut,
                    'heure_fin' => $creneau->heure_fin,
                    'salle' => $creneau->salle,
                    'statut' => 'prevue',
                ]);

                $creees++;
            }
        }

        return $creees;
    }

    /**
     * Générère les séances de plusieurs classes d'un coup — le pendant « en
     * masse » de {@see genererSeances()}, pour ouvrir un trimestre ou une
     * année sans repasser classe par classe.
     *
     * @param Collection<int, Classe> $classes
     * @return array{creees: int, classes: int}
     */
    public function genererSeancesPourClasses(Collection $classes, Carbon $debut, Carbon $fin, ?Trimestre $trimestre): array
    {
        $creees = 0;

        foreach ($classes as $classe) {
            $creees += $this->genererSeances($classe, $debut, $fin, $trimestre);
        }

        return ['creees' => $creees, 'classes' => $classes->count()];
    }

    /**
     * Feuille d'appel : tous les élèves actifs convoqués, avec leur pointage
     * s'il a déjà été saisi. Les élèves non encore pointés remontent en
     * « present » — l'appel se fait par exception, comme sur une feuille papier.
     *
     * Sur un cours en tronc commun, la feuille réunit les élèves de toutes les
     * classes du regroupement : c'est un seul cours, donc un seul appel. La
     * classe de chaque élève accompagne la ligne, sans quoi l'enseignant ne
     * saurait plus qui il pointe.
     */
    public function feuilleAppel(Seance $seance): Collection
    {
        $pointages = $seance->presences()->get()->keyBy('eleve_id');

        return $seance->elevesAttendus()
            ->load('classe')
            ->map(fn ($eleve) => [
                'classe' => $eleve->classe?->only(['id', 'nom']),
                'eleve' => $eleve,
                // Tous présents par défaut : l'appel ne relève que les écarts.
                'statut' => $pointages->get($eleve->id)?->statut ?? 'present',
                'motif' => $pointages->get($eleve->id)?->motif,
                'justifie' => (bool) ($pointages->get($eleve->id)?->justifie ?? false),
                'remarque' => $pointages->get($eleve->id)?->remarque,
                'pointe' => $pointages->has($eleve->id),
            ]);
    }

    /**
     * @param  array<int, array{eleve_id:int, statut:string, motif?:?string, remarque?:?string}>  $lignes
     */
    public function enregistrerAppel(Seance $seance, array $lignes): int
    {
        /*
         * Le périmètre autorisé est celui des classes convoquées, pas de la
         * seule classe porteuse. Sur un tronc commun, s'en tenir à la classe
         * porteuse écarterait sans le dire tous les élèves des autres classes
         * — l'appel les afficherait puis n'en enregistrerait aucun.
         */
        $elevesAttendus = $seance->elevesAttendus()->pluck('id');

        // État avant écriture, pour ne signaler que les absences non
        // justifiées qui viennent d'apparaître — un appel réenregistré
        // (correction d'un seul élève) ne doit pas relancer une alerte pour
        // tous les autres qui étaient déjà absents.
        $etatAvant = Presence::where('seance_id', $seance->id)
            ->get(['eleve_id', 'statut', 'justifie'])
            ->keyBy('eleve_id');

        $nouvellesAbsencesNonJustifiees = [];

        $enregistres = $this->transaction(function () use ($seance, $lignes, $elevesAttendus, $etatAvant, &$nouvellesAbsencesNonJustifiees) {
            $enregistres = 0;

            foreach ($lignes as $ligne) {
                if (! $elevesAttendus->contains($ligne['eleve_id'])) {
                    continue; // un élève qui n'est pas convoqué n'a rien à faire dans cet appel
                }

                // Le motif ne qualifie qu'une absence : le conserver sur un
                // élève repassé présent laisserait une trace fausse.
                $motif = $ligne['statut'] === 'absent' ? ($ligne['motif'] ?? null) : null;
                $remarque = $ligne['remarque'] ?? null;

                /*
                 * Une absence marquée sans motif explicite peut déjà avoir
                 * été justifiée par un parent, par anticipation : c'est elle
                 * qui tranche par défaut plutôt que de laisser l'absence
                 * « inconnue » alors que la famille avait prévenu.
                 * L'enseignant garde la main s'il saisit lui-même un motif.
                 */
                $justificationParent = null;
                if ($ligne['statut'] === 'absent' && $motif === null) {
                    $justificationParent = $this->justifications->trouverPour($ligne['eleve_id'], $seance->date_seance->toDateString());

                    if ($justificationParent) {
                        $motif = $justificationParent->motif;
                        $remarque = trim('Justifiée par le parent'
                            .($justificationParent->description ? ' — '.$justificationParent->description : '')
                            .($remarque ? ' · '.$remarque : ''));
                    }
                }

                // Maladie, permission et raison scolaire sont des motifs
                // reconnus par l'établissement ; « inconnu » ne l'est pas
                // encore et compte comme une absence non justifiée.
                $justifie = $motif !== null && $motif !== 'inconnu';

                $presence = Presence::updateOrCreate(
                    ['seance_id' => $seance->id, 'eleve_id' => $ligne['eleve_id']],
                    [
                        'statut' => $ligne['statut'],
                        'motif' => $motif,
                        'justifie' => $justifie,
                        'remarque' => $remarque,
                    ]
                );
                $enregistres++;

                if ($justificationParent) {
                    $this->justifications->marquerAppliquee($justificationParent, $presence);
                }

                $avant = $etatAvant->get($ligne['eleve_id']);
                $etaitDejaAbsentNonJustifie = $avant && $avant->statut === 'absent' && ! $avant->justifie;

                if ($ligne['statut'] === 'absent' && ! $justifie && ! $etaitDejaAbsentNonJustifie) {
                    $nouvellesAbsencesNonJustifiees[] = $ligne['eleve_id'];
                }
            }

            $seance->update([
                'statut' => 'effectuee',
                // Figé une seule fois : une correction dans les 15 minutes ne
                // doit pas repousser la fenêtre, sinon elle ne se referme
                // jamais tant que l'enseignant continue à corriger.
                'appel_verrouille_le' => $seance->appel_verrouille_le ?? now(),
            ]);

            return $enregistres;
        });

        // Hors transaction : le SMS est un appel réseau, il ne doit pas
        // tenir la connexion à la base ouverte pendant son exécution.
        if ($nouvellesAbsencesNonJustifiees !== []) {
            $this->alerterAbsences($seance, $nouvellesAbsencesNonJustifiees);
        }

        return $enregistres;
    }

    /**
     * Alerte immédiate sur une absence non justifiée : en interne pour qui
     * tient la discipline, par SMS pour la famille — le rapport hebdomadaire
     * revient dessus, mais un parent doit pouvoir réagir le jour même.
     *
     * @param  array<int, int>  $eleveIds
     */
    private function alerterAbsences(Seance $seance, array $eleveIds): void
    {
        $classe = $seance->classe;
        $eleves = Eleve::with('tuteurs')->whereIn('id', $eleveIds)->get();

        if ($eleves->isEmpty()) {
            return;
        }

        $this->notifications->notifierParPermission(
            $classe->school_id,
            'discipline.manage',
            'absence',
            'Absence non justifiée',
            $eleves->count() === 1
                ? "{$eleves->first()->nom_complet} est absent(e) sans motif connu en {$classe->nom}."
                : "{$eleves->count()} élèves sont absents sans motif connu en {$classe->nom} : ".$eleves->pluck('nom_complet')->implode(', ').'.',
        );

        foreach ($eleves as $eleve) {
            $tuteur = $eleve->tuteurs->firstWhere('pivot.is_principal', true) ?? $eleve->tuteurs->first();

            if ($tuteur?->telephone) {
                $this->sms->envoyer(
                    $tuteur->telephone,
                    "Absence — {$eleve->nom_complet} est marqué(e) absent(e) aujourd'hui en {$classe->nom}, sans motif connu."
                );
            }
        }
    }

    /**
     * Heures d'absence cumulées par élève sur un trimestre, déduites des
     * appels : chaque séance manquée compte pour sa durée réelle
     * (`Seance::dureeHeures()`), classée justifiée ou non selon
     * `Presence::justifie`. C'est le calcul par défaut de la grille
     * disciplinaire du secondaire — {@see DisciplineService::grille()} — tant
     * qu'un Surveillant Général n'a pas corrigé l'élève à la main.
     *
     * @return Collection<int, array{eleve_id:int, heures_justifiees:float, heures_non_justifiees:float}>
     */
    public function cumulAbsences(Classe $classe, Trimestre $trimestre): Collection
    {
        $seances = Seance::where('classe_id', $classe->id)
            ->where('trimestre_id', $trimestre->id)
            ->where('statut', 'effectuee')
            ->with('presences')
            ->get();

        $cumuls = [];

        foreach ($seances as $seance) {
            $duree = $seance->dureeHeures();

            foreach ($seance->presences->whereIn('statut', ['absent', 'renvoye']) as $presence) {
                $cumuls[$presence->eleve_id] ??= ['heures_justifiees' => 0.0, 'heures_non_justifiees' => 0.0];
                $cle = $presence->justifie ? 'heures_justifiees' : 'heures_non_justifiees';
                $cumuls[$presence->eleve_id][$cle] += $duree;
            }
        }

        return collect($cumuls)->map(fn ($valeurs, $eleveId) => [
            'eleve_id' => $eleveId,
            'heures_justifiees' => round($valeurs['heures_justifiees'], 2),
            'heures_non_justifiees' => round($valeurs['heures_non_justifiees'], 2),
        ])->values();
    }

    /** Nombre de colonnes « période » affichées par jour sur la fiche d'appel hebdomadaire. */
    public const PERIODES_PAR_JOUR = 8;

    /**
     * Grille hebdomadaire élève × jour × période, base de la fiche d'appel
     * papier reconstituée en PDF : une période, c'est une séance de la classe
     * ce jour-là, dans son ordre chronologique — il n'existe pas de grille
     * horaire fixe (1ʳᵉ heure = 7h, etc.) dans l'emploi du temps actuel, donc
     * le rang de la séance dans la journée en tient lieu.
     *
     * @return array{jours: list<\Illuminate\Support\Carbon>, lignes: Collection<int, array{eleve: Eleve, jours: array<string, list<bool>>, total_absences: int}>}
     */
    public function ficheAppelHebdomadaire(Classe $classe, \Illuminate\Support\Carbon $lundi): array
    {
        $lundi = $lundi->copy()->startOfDay();
        $vendredi = $lundi->copy()->addDays(4)->endOfDay();

        $seancesParJour = Seance::where('classe_id', $classe->id)
            ->whereBetween('date_seance', [$lundi->toDateString(), $vendredi->toDateString()])
            ->with('presences')
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn (Seance $s) => $s->date_seance->toDateString());

        $jours = [];
        for ($i = 0; $i < 5; $i++) {
            $jours[] = $lundi->copy()->addDays($i);
        }

        $eleves = $classe->eleves()->where('statut', 'actif')->orderBy('nom_complet')->get();

        $lignes = $eleves->map(function (Eleve $eleve) use ($jours, $seancesParJour) {
            $joursData = [];
            $totalAbsences = 0;

            foreach ($jours as $jour) {
                $seancesDuJour = ($seancesParJour->get($jour->toDateString()) ?? collect())
                    ->take(self::PERIODES_PAR_JOUR);

                $periodes = $seancesDuJour->map(function (Seance $seance) use ($eleve, &$totalAbsences) {
                    $presence = $seance->presences->firstWhere('eleve_id', $eleve->id);
                    $absent = $presence !== null && in_array($presence->statut, ['absent', 'renvoye'], true);
                    if ($absent) {
                        $totalAbsences++;
                    }

                    return $absent;
                })->values()->all();

                $joursData[$jour->toDateString()] = $periodes;
            }

            return ['eleve' => $eleve, 'jours' => $joursData, 'total_absences' => $totalAbsences];
        });

        return ['jours' => $jours, 'lignes' => $lignes];
    }
}
