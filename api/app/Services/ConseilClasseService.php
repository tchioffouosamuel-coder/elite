<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\ConseilClasseDecision;
use App\Models\Eleve;
use App\Models\HistoriqueScolariteEleve;
use App\Models\Setting;
use App\Models\User;
use RuntimeException;

/**
 * Conseil de classe de fin d'année : décide, par élève, admis/redouble/exclu
 * à partir de la moyenne annuelle vs un seuil (ajustable, motivé), avec
 * grâce et exclusion en ajustements manuels. Une fois validé (immuable), la
 * classe est archivée et les fiches élèves sont mutées en conséquence — cf.
 * {@see valider()}.
 */
class ConseilClasseService extends BaseService
{
    public function __construct(
        private readonly MoyenneService $moyennes,
        private readonly MoyennePrimaireService $moyennesPrimaire,
        private readonly ArchivageService $archivage,
    ) {}

    /**
     * Idempotent : retourne le brouillon existant s'il y en a un, sinon en
     * crée un avec les décisions par défaut calculées pour chaque élève
     * actif de la classe.
     */
    public function preparer(Classe $classe, AnneeScolaire $annee): ConseilClasse
    {
        $existant = ConseilClasse::where('annee_scolaire_id', $annee->id)->where('classe_id', $classe->id)->first();

        if ($existant) {
            return $existant;
        }

        $seuil = (float) Setting::get($classe->school_id, 'passage_moyenne_min', 10);

        return $this->transaction(function () use ($classe, $annee, $seuil) {
            $conseil = ConseilClasse::create([
                'school_id' => $classe->school_id,
                'annee_scolaire_id' => $annee->id,
                'classe_id' => $classe->id,
                'seuil_moyenne' => $seuil,
                'statut' => 'brouillon',
            ]);

            $eleves = Eleve::where('classe_id', $classe->id)->where('statut', 'actif')->get();
            $secondaire = $classe->school->estSecondaire();

            foreach ($eleves as $eleve) {
                $moyenne = $secondaire
                    ? $this->moyennes->moyenneAnnuelleEleve($eleve, $annee->id)
                    : $this->moyennesPrimaire->moyenneAnnuelleEleve($eleve, $annee->id);

                $defaut = $moyenne !== null && $moyenne >= $seuil ? 'admis' : 'redouble';

                ConseilClasseDecision::create([
                    'conseil_classe_id' => $conseil->id,
                    'eleve_id' => $eleve->id,
                    'moyenne_annuelle' => $moyenne,
                    'decision_defaut' => $defaut,
                    'decision_finale' => $defaut,
                ]);
            }

            return $conseil->fresh('decisions.eleve');
        });
    }

    private function assertBrouillon(ConseilClasse $conseil): void
    {
        if ($conseil->statut !== 'brouillon') {
            throw new RuntimeException('Ce conseil de classe est déjà validé, il ne peut plus être modifié.');
        }
    }

    /**
     * Change le seuil de passage pour ce conseil — motif obligatoire dès que
     * le seuil s'écarte du défaut de l'école. Recalcule `decision_finale`
     * pour toute décision qui n'a pas été ajustée manuellement (ni exclue, ni
     * graciée) : un ajustement individuel du conseil ne doit jamais être
     * écrasé par un simple changement de seuil.
     */
    public function definirSeuil(ConseilClasse $conseil, float $seuil, ?string $motif): ConseilClasse
    {
        $this->assertBrouillon($conseil);

        $seuilDefaut = (float) Setting::get($conseil->school_id, 'passage_moyenne_min', 10);
        if (abs($seuil - $seuilDefaut) > 0.001 && ! $motif) {
            throw new RuntimeException("Un motif est requis pour s'écarter du seuil par défaut de l'école.");
        }

        $this->transaction(function () use ($conseil, $seuil, $motif) {
            $conseil->update(['seuil_moyenne' => $seuil, 'motif_seuil' => $motif]);

            foreach ($conseil->decisions()->get() as $decision) {
                if ($decision->gracie || $decision->decision_finale === 'exclu') {
                    continue; // ajustement individuel déjà posé, on ne l'écrase pas
                }

                $defaut = $decision->moyenne_annuelle !== null && $decision->moyenne_annuelle >= $seuil ? 'admis' : 'redouble';
                $decision->update(['decision_defaut' => $defaut, 'decision_finale' => $defaut]);
            }
        });

        return $conseil->fresh('decisions.eleve');
    }

    /** Null = fin de cycle : les admis seront diplômés plutôt que déplacés. */
    public function definirClasseDestination(ConseilClasse $conseil, ?int $classeDestinationId): ConseilClasse
    {
        $this->assertBrouillon($conseil);
        $conseil->update(['classe_destination_id' => $classeDestinationId]);

        return $conseil->fresh();
    }

    public function exclure(ConseilClasseDecision $decision, string $motif): ConseilClasseDecision
    {
        $this->assertBrouillon($decision->conseilClasse);
        $decision->update(['decision_finale' => 'exclu', 'gracie' => false, 'motif' => $motif]);

        return $decision->fresh();
    }

    public function gracier(ConseilClasseDecision $decision, string $motif): ConseilClasseDecision
    {
        $this->assertBrouillon($decision->conseilClasse);

        if ($decision->decision_defaut !== 'redouble') {
            throw new RuntimeException('Seul un élève redoublant par défaut peut être gracié.');
        }

        $decision->update(['decision_finale' => 'admis', 'gracie' => true, 'motif' => $motif]);

        return $decision->fresh();
    }

    /** Revient à la décision par défaut — annule une exclusion ou une grâce posée par erreur. */
    public function annulerAjustement(ConseilClasseDecision $decision): ConseilClasseDecision
    {
        $this->assertBrouillon($decision->conseilClasse);
        $decision->update(['decision_finale' => $decision->decision_defaut, 'gracie' => false, 'motif' => null]);

        return $decision->fresh();
    }

    /**
     * Valide le conseil : archive la classe telle qu'elle était (roster
     * d'avant mutation), applique chaque décision aux fiches élèves, et
     * inscrit l'historique de parcours. Irréversible — cf. assertBrouillon().
     */
    public function valider(ConseilClasse $conseil, User $user): ConseilClasse
    {
        $this->assertBrouillon($conseil);

        $decisions = $conseil->decisions()->with('eleve')->get();
        foreach ($decisions as $decision) {
            if (($decision->decision_finale === 'exclu' || $decision->gracie) && ! $decision->motif) {
                throw new RuntimeException("Un motif est requis pour chaque exclusion et chaque grâce ({$decision->eleve->nom_complet}).");
            }
        }

        return $this->transaction(function () use ($conseil, $user, $decisions) {
            $classe = $conseil->classe;
            $annee = $conseil->anneeScolaire;

            // Archiver AVANT de muter les fiches : le roster capturé doit être
            // celui d'avant le passage en classe supérieure.
            $this->archivage->archiverClasse($classe, $annee, $conseil, $user->id);

            $secondaire = $classe->school->estSecondaire();
            $classement = $secondaire
                ? $this->moyennes->classementAnnuel($classe, $annee->id)
                : $this->moyennesPrimaire->classementAnnuel($classe, $annee->id);
            $rangsParEleve = $classement->mapWithKeys(fn ($ligne) => [$ligne['eleve']->id => $ligne['rang']]);

            foreach ($decisions as $decision) {
                $eleve = $decision->eleve;

                match ($decision->decision_finale) {
                    'exclu' => $eleve->update(['statut' => 'exclu']),
                    'admis' => $conseil->classe_destination_id !== null
                        ? $eleve->update(['classe_id' => $conseil->classe_destination_id, 'redoublant' => false])
                        : $eleve->update(['statut' => 'diplome', 'classe_id' => null, 'redoublant' => false]),
                    'redouble' => $eleve->update(['redoublant' => true]),
                };

                HistoriqueScolariteEleve::updateOrCreate(
                    ['eleve_id' => $eleve->id, 'annee_scolaire_id' => $annee->id],
                    [
                        'school_id' => $classe->school_id,
                        'classe_id' => $classe->id,
                        'classe_nom' => $classe->nom,
                        'niveau_libelle' => $classe->niveauScolaire?->libelle ?? $classe->niveau_classe,
                        'moyenne_annuelle' => $decision->moyenne_annuelle,
                        'rang_annuel' => $rangsParEleve->get($eleve->id),
                        'decision' => $decision->decision_finale === 'admis' && $conseil->classe_destination_id === null
                            ? 'diplome'
                            : $decision->decision_finale,
                        'gracie' => $decision->gracie,
                        'motif' => $decision->motif,
                    ],
                );
            }

            $conseil->update(['statut' => 'valide', 'valide_le' => now(), 'valide_par' => $user->id]);

            return $conseil->fresh(['decisions.eleve', 'classe', 'classeDestination']);
        });
    }
}
