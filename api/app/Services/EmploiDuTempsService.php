<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Trimestre;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmploiDuTempsService extends BaseService
{
    public function grille(Classe $classe): Collection
    {
        return EmploiDuTemps::where('classe_id', $classe->id)
            ->with(['classeMatiere.matiere', 'classeMatiere.enseignant'])
            ->orderBy('jour')->orderBy('heure_debut')
            ->get();
    }

    /**
     * Refuse un créneau qui empiète sur un autre créneau du même jour pour la
     * classe : deux cours simultanés rendraient l'appel ambigu.
     */
    public function chevauche(Classe $classe, int $jour, string $debut, string $fin, ?int $ignorerId = null): bool
    {
        return EmploiDuTemps::where('classe_id', $classe->id)
            ->where('jour', $jour)
            ->when($ignorerId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('heure_debut', '<', $fin)
            ->where('heure_fin', '>', $debut)
            ->exists();
    }

    /**
     * Matérialise les créneaux de la semaine en séances datées, prêtes à
     * recevoir l'appel. Les séances déjà créées ne sont pas dupliquées.
     *
     * @return int nombre de séances créées
     */
    public function genererSeances(Classe $classe, Carbon $debut, Carbon $fin, ?Trimestre $trimestre): int
    {
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
     * Feuille d'appel : tous les élèves actifs de la classe, avec leur pointage
     * s'il a déjà été saisi. Les élèves non encore pointés remontent en
     * « present » — l'appel se fait par exception, comme sur une feuille papier.
     */
    public function feuilleAppel(Seance $seance): Collection
    {
        $pointages = $seance->presences()->get()->keyBy('eleve_id');

        return $seance->classe->eleves()->where('statut', 'actif')
            ->orderBy('nom_complet')->get()
            ->map(fn ($eleve) => [
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
        $elevesDeLaClasse = $seance->classe->eleves()->where('statut', 'actif')->pluck('id');

        return $this->transaction(function () use ($seance, $lignes, $elevesDeLaClasse) {
            $enregistres = 0;

            foreach ($lignes as $ligne) {
                if (! $elevesDeLaClasse->contains($ligne['eleve_id'])) {
                    continue; // un élève d'une autre classe n'a rien à faire dans cet appel
                }

                // Le motif ne qualifie qu'une absence : le conserver sur un
                // élève repassé présent laisserait une trace fausse.
                $motif = $ligne['statut'] === 'absent' ? ($ligne['motif'] ?? null) : null;

                Presence::updateOrCreate(
                    ['seance_id' => $seance->id, 'eleve_id' => $ligne['eleve_id']],
                    [
                        'statut' => $ligne['statut'],
                        'motif' => $motif,
                        // Maladie, permission et raison scolaire sont des motifs
                        // reconnus par l'établissement ; « inconnu » ne l'est pas
                        // encore et compte comme une absence non justifiée.
                        'justifie' => $motif !== null && $motif !== 'inconnu',
                        'remarque' => $ligne['remarque'] ?? null,
                    ]
                );
                $enregistres++;
            }

            $seance->update(['statut' => 'effectuee']);

            return $enregistres;
        });
    }

    /**
     * Heures d'absence cumulées par élève sur un trimestre, déduites des appels.
     * Sert de contrôle face aux heures saisies manuellement dans AbsenceTrimestre.
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
}
