<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Presence;
use App\Models\School;
use App\Models\Seance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Détecte l'élève dont on est sans nouvelles : ni présent ni marqué absent à
 * aucun cours depuis plusieurs jours, ce qui n'est ni une absence justifiée
 * ni une absence non justifiée mais une troisième situation, plus grave —
 * personne ne l'a vu, et personne n'a rien signalé. Alerte l'administration,
 * la famille par SMS, puis bloque l'accès du/des parent(s) : une action forte,
 * volontairement, pour forcer un contact avant de conclure à une simple
 * absence.
 */
class AbsenceNonEnregistreeService extends BaseService
{
    /** Jours de cours consécutifs sans le moindre pointage avant de sonner l'alerte. */
    public const SEUIL_JOURS = 5;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly \App\Services\Sms\SmsService $sms,
        private readonly AuthService $auth,
    ) {}

    /**
     * Parcourt les classes de l'école et signale chaque élève qui vient
     * d'atteindre le seuil. Un élève déjà signalé pour la série en cours
     * n'est pas resignalé — {@see alerte_absence_declenchee_le} — jusqu'à ce
     * qu'un pointage réapparaisse et referme la série.
     *
     * @return int nombre d'élèves nouvellement signalés
     */
    public function detecterEtAlerter(School $school): int
    {
        $signales = 0;

        Classe::forSchool($school->id)->get()->each(function (Classe $classe) use ($school, &$signales) {
            $groupesParJour = $this->derniersJoursDeCours($classe);

            if ($groupesParJour->count() < self::SEUIL_JOURS) {
                // Pas encore assez de jours de cours écoulés pour conclure —
                // une classe qui vient d'ouvrir n'a pas d'historique à juger.
                return;
            }

            $eleves = $classe->eleves()->where('statut', 'actif')->with('tuteurs')->get();
            if ($eleves->isEmpty()) {
                return;
            }

            $joursRecents = $groupesParJour->keys();
            $seanceIds = $groupesParJour->flatMap(fn (Collection $seances) => $seances)->pluck('id');
            $joursCouvertsParEleve = $this->joursCouverts($seanceIds, $eleves);

            foreach ($eleves as $eleve) {
                if ($eleve->alerte_absence_declenchee_le !== null) {
                    // Déjà signalé : on ne resignale que si l'élève a été vu
                    // depuis (un pointage, quel qu'en soit le statut) — sans
                    // quoi ce test se contenterait de vérifier que la fenêtre
                    // des derniers jours reste couverte au moment précis où
                    // elle bascule, et manquerait le retour si personne n'a
                    // relancé la détection entre-temps.
                    if (! $this->revuDepuis($classe, $eleve)) {
                        continue;
                    }
                    $eleve->forceFill(['alerte_absence_declenchee_le' => null])->save();
                }

                $joursCouverts = $joursCouvertsParEleve->get($eleve->id, collect());
                $serieComplete = $joursRecents->every(fn (string $jour) => ! $joursCouverts->contains($jour));

                if (! $serieComplete) {
                    continue;
                }

                $this->alerter($school, $classe, $eleve);
                $eleve->forceFill(['alerte_absence_declenchee_le' => Carbon::today()])->save();
                $signales++;
            }
        });

        return $signales;
    }

    /**
     * Les derniers jours de cours effectivement tenus pour cette classe,
     * avant aujourd'hui — la journée en cours n'est jamais jugée tant qu'elle
     * n'est pas terminée. Regroupé par date plutôt que resollicité plus tard
     * par une seconde requête sur `date_seance` : sous SQLite (tests), le
     * cast `date` de `Seance` sérialise la colonne avec un suffixe horaire,
     * ce qui casserait un `whereIn(dates ISO)` ultérieur — cf. le même
     * correctif déjà posé sur `Remuneration.date_effet`.
     *
     * @return Collection<string, Collection<int, Seance>> séances groupées par date ISO, la plus récente en premier
     */
    private function derniersJoursDeCours(Classe $classe): Collection
    {
        return Seance::where('classe_id', $classe->id)
            ->where('statut', 'effectuee')
            ->where('date_seance', '<', Carbon::today())
            ->orderByDesc('date_seance')
            ->get(['id', 'date_seance'])
            ->groupBy(fn (Seance $s) => $s->date_seance->toDateString())
            ->take(self::SEUIL_JOURS);
    }

    /**
     * Pour chaque élève, les jours de la fenêtre où il a fait l'objet d'au
     * moins un pointage (présent, absent, en retard… peu importe le statut :
     * ce qui compte ici est qu'on ait une trace de lui).
     *
     * @param  Collection<int, int>  $seanceIds
     * @param  Collection<int, Eleve>  $eleves
     * @return Collection<int, Collection<int, string>> indexée par eleve_id
     */
    private function joursCouverts(Collection $seanceIds, Collection $eleves): Collection
    {
        return Presence::whereIn('seance_id', $seanceIds)
            ->whereIn('eleve_id', $eleves->pluck('id'))
            ->with('seance:id,date_seance')
            ->get()
            ->groupBy('eleve_id')
            ->map(fn (Collection $lignes) => $lignes
                ->pluck('seance.date_seance')
                ->filter()
                ->map(fn ($date) => $date->toDateString())
                ->unique());
    }

    /**
     * Un pointage existe-t-il pour cet élève depuis le jour où l'alerte a été
     * déclenchée ? C'est ce qui referme la série précédente — indépendamment
     * de la fenêtre glissante des derniers jours, qui aurait fini par
     * l'oublier sans qu'aucune exécution n'ait observé le retour.
     */
    private function revuDepuis(Classe $classe, Eleve $eleve): bool
    {
        return Presence::where('eleve_id', $eleve->id)
            ->whereHas('seance', fn ($q) => $q->where('classe_id', $classe->id)
                ->where('date_seance', '>=', $eleve->alerte_absence_declenchee_le))
            ->exists();
    }

    /**
     * Alerte l'administration en interne, la ou les familles par SMS, puis
     * bloque chaque compte parent lié à cet élève — sans distinction de
     * fratrie : un tuteur rattaché à d'autres enfants perd aussi l'accès à
     * leur suivi, c'est le prix volontairement payé pour forcer le contact.
     */
    private function alerter(School $school, Classe $classe, Eleve $eleve): void
    {
        $this->notifications->notifierParPermission(
            $school->id,
            'discipline.manage',
            'absence_non_enregistree',
            'Absence prolongée sans pointage',
            "{$eleve->nom_complet} ({$classe->nom}) n'a fait l'objet d'aucun pointage depuis ".self::SEUIL_JOURS
                .' jours de cours consécutifs : ni présent, ni marqué absent. Vérifier la situation avant de conclure à une absence simple.',
            "eleve:{$eleve->id}",
        );

        foreach ($eleve->tuteurs as $tuteur) {
            if ($tuteur->telephone) {
                $this->sms->envoyer(
                    $tuteur->telephone,
                    "Alerte — {$eleve->nom_complet} n'a plus été pointé(e) en classe depuis ".self::SEUIL_JOURS
                        ." jours. Contactez l'établissement d'urgence ; votre accès à l'espace parent est suspendu dans l'attente.",
                );
            }

            if ($tuteur->user && $tuteur->user->is_active) {
                $tuteur->user->update(['is_active' => false]);
                $this->auth->revoquerTousLesJetons($tuteur->user);
            }
        }
    }
}
