<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\ArchiveClasseAnnee;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\Eleve;
use App\Models\Presence;
use App\Models\Sanction;
use App\Models\Trimestre;
use App\Models\VisiteInfirmerie;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Fige les données pédagogiques d'une classe pour une année révolue, dans
 * `archives_classe_annee`. Une classe est un gabarit permanent (jamais
 * rattachée à une année) : sans cette photographie, ses notes/absences
 * d'une année passée deviendraient indiscernables de celles de l'année
 * suivante dès que son effectif tourne — ou pire, un coefficient modifié
 * plus tard changerait rétroactivement une moyenne déjà publiée.
 *
 * Le JSON stocké est un instantané de scalaires (`json_decode(json_encode(...), true)`
 * sur les tableaux déjà construits par BulletinService/BulletinPrimaireService),
 * jamais un dump direct de modèles Eloquent : une fois écrit, il ne dépend
 * plus de la forme future de ces classes.
 */
class ArchivageService extends BaseService
{
    public function __construct(
        private readonly BulletinService $bulletins,
        private readonly BulletinPrimaireService $bulletinsPrimaire,
        private readonly MoyenneService $moyennes,
        private readonly MoyennePrimaireService $moyennesPrimaire,
    ) {}

    /**
     * Archive une classe pour l'année donnée — appelée par
     * ConseilClasseService::valider() AVANT toute mutation des fiches élèves
     * (le roster capturé ici est celui d'avant le passage en classe
     * supérieure), et par archiverAnnee() pour les classes sans conseil
     * (effectif vide, ou cycle sans notion de conseil).
     */
    public function archiverClasse(Classe $classe, AnneeScolaire $annee, ?ConseilClasse $conseil, ?int $archiveParUserId): ArchiveClasseAnnee
    {
        $eleves = Eleve::where('classe_id', $classe->id)->where('statut', 'actif')->orderBy('nom_complet')->get();

        $decisionsParEleve = $conseil
            ? $conseil->decisions()->get()->keyBy('eleve_id')
            : collect();

        $rosterJson = $eleves->map(function (Eleve $eleve) use ($decisionsParEleve) {
            $decision = $decisionsParEleve->get($eleve->id);

            return [
                'eleve_id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom_complet' => $eleve->nom_complet,
                'sexe' => $eleve->sexe,
                'decision' => $decision?->decision_finale,
                'gracie' => (bool) $decision?->gracie,
                'moyenne_annuelle' => $decision?->moyenne_annuelle,
                'motif' => $decision?->motif,
            ];
        })->values()->all();

        return ArchiveClasseAnnee::updateOrCreate(
            ['annee_scolaire_id' => $annee->id, 'classe_id' => $classe->id],
            [
                'school_id' => $classe->school_id,
                'classe_nom' => $classe->nom,
                'niveau_libelle' => $classe->niveauScolaire?->libelle ?? $classe->niveau_classe,
                'conseil_classe_id' => $conseil?->id,
                'effectif' => $eleves->count(),
                'roster_json' => $rosterJson,
                'notes_json' => $this->notesJson($classe, $annee, $eleves),
                'absences_json' => $this->absencesJson($eleves, $annee),
                'discipline_json' => $this->disciplineJson($eleves, $annee),
                'infirmerie_json' => $this->infirmerieJson($eleves, $annee),
                'archive_par' => $archiveParUserId,
                'archive_le' => now(),
            ],
        );
    }

    /**
     * Archive toute l'année : chaque classe non vide de l'école doit avoir un
     * conseil validé, sinon on refuse d'un bloc plutôt que de clôturer une
     * année partiellement traitée.
     */
    public function archiverAnnee(AnneeScolaire $annee, ?int $userId): void
    {
        $classes = Classe::forSchool($annee->school_id)->get();

        $manquantes = [];
        foreach ($classes as $classe) {
            $aDesEleves = Eleve::where('classe_id', $classe->id)->where('statut', 'actif')->exists();
            $dejaArchivee = ArchiveClasseAnnee::where('annee_scolaire_id', $annee->id)->where('classe_id', $classe->id)->exists();
            $conseilValide = ConseilClasse::where('annee_scolaire_id', $annee->id)
                ->where('classe_id', $classe->id)->where('statut', 'valide')->exists();

            if ($dejaArchivee) {
                continue;
            }

            if ($aDesEleves && ! $conseilValide) {
                $manquantes[] = $classe->nom;

                continue;
            }

            $this->archiverClasse($classe, $annee, null, $userId);
        }

        if ($manquantes !== []) {
            throw new RuntimeException(
                'Conseil de classe non validé pour : '.implode(', ', $manquantes).'. Validez-les avant d\'archiver l\'année.'
            );
        }

        $annee->update(['archivee_le' => now()]);
    }

    /**
     * Extrait, depuis `notes_json`, la vue « bulletin d'un seul élève » que
     * consomme {@see \App\Support\Pdf\BulletinArchiveGenerator} — le JSON
     * archivé porte la classe entière par trimestre (même forme que
     * BulletinService::donneesClasse()), ce n'est qu'ici qu'on la réduit à
     * un élève.
     */
    public function donneesBulletinArchive(ArchiveClasseAnnee $archive, int $eleveId): ?array
    {
        $roster = collect($archive->roster_json)->firstWhere('eleve_id', $eleveId);
        if (! $roster) {
            return null;
        }

        $trimestres = [];
        foreach ($archive->notes_json['trimestres'] ?? [] as $trimestreDonnees) {
            $ligneEleve = collect($trimestreDonnees['eleves'] ?? [])
                ->first(fn ($e) => ($e['eleve']['id'] ?? null) === $eleveId);

            if (! $ligneEleve) {
                continue;
            }

            $matieres = collect($ligneEleve['groupes'] ?? [])
                ->flatMap(fn ($groupe) => $groupe)
                ->map(fn ($ligne) => [
                    'matiere' => $ligne['matiere'] ?? ($ligne['competence'] ?? '—'),
                    'coefficient' => $ligne['coefficient'] ?? null,
                    'moyenne' => $ligne['moyenne'] ?? null,
                    'rang' => $ligne['rang'] ?? null,
                ])->values()->all();

            $trimestres[] = [
                'libelle' => $trimestreDonnees['trimestre']['libelle'] ?? '',
                'matieres' => $matieres,
                'moyenne_generale' => $ligneEleve['moyenne_generale'] ?? null,
                'rang_general' => $ligneEleve['rang'] ?? null,
            ];
        }

        return [
            'eleve' => ['nom_complet' => $roster['nom_complet'], 'matricule' => $roster['matricule'], 'sexe' => $roster['sexe']],
            'classe' => ['nom' => $archive->classe_nom],
            'annee' => ['libelle' => $archive->anneeScolaire->libelle],
            'school' => $archive->school,
            'trimestres' => $trimestres,
            'moyenne_annuelle' => $archive->notes_json['annuel'][$eleveId]['moyenne_annuelle'] ?? null,
            'rang_annuel' => $archive->notes_json['annuel'][$eleveId]['rang_annuel'] ?? null,
        ];
    }

    /**
     * Un instantané par trimestre (mêmes tableaux que BulletinService/BulletinPrimaireService,
     * aplatis en scalaires), plus une synthèse annuelle par élève.
     */
    private function notesJson(Classe $classe, AnneeScolaire $annee, Collection $eleves): array
    {
        $secondaire = $classe->school->estSecondaire();
        $trimestres = Trimestre::where('annee_scolaire_id', $annee->id)->orderBy('ordre')->get();
        $eleveIds = $eleves->pluck('id')->all();

        $parTrimestre = $trimestres->mapWithKeys(function (Trimestre $trimestre) use ($classe, $secondaire, $eleveIds) {
            $donnees = $secondaire
                ? $this->bulletins->donneesClasse($classe, $trimestre, $eleveIds)
                : $this->bulletinsPrimaire->donneesClasse($classe, $trimestre, $eleveIds);

            return [$trimestre->id => json_decode(json_encode($donnees), true)];
        });

        $classement = $secondaire
            ? $this->moyennes->classementAnnuel($classe, $annee->id)
            : $this->moyennesPrimaire->classementAnnuel($classe, $annee->id);
        $rangsParEleve = $classement->mapWithKeys(fn ($ligne) => [$ligne['eleve']->id => $ligne['rang']]);

        // Tableau PHP simple, pas une Collection : une Collection n'accepte pas
        // la mutation imbriquée `$annuel[$id]['rang_annuel'] = ...` (elle
        // n'implémente qu'ArrayAccess sur le premier niveau, pas la référence).
        $annuel = [];
        foreach ($eleves as $eleve) {
            $moyenne = $secondaire
                ? $this->moyennes->moyenneAnnuelleEleve($eleve, $annee->id)
                : $this->moyennesPrimaire->moyenneAnnuelleEleve($eleve, $annee->id);

            $annuel[$eleve->id] = [
                'moyenne_annuelle' => $moyenne,
                'rang_annuel' => $rangsParEleve->get($eleve->id),
            ];
        }

        return [
            'trimestres' => $parTrimestre->all(),
            'annuel' => $annuel,
        ];
    }

    /** Absences/présences de l'année, par élève — via Seance.date_seance, seule datation fiable (Classe n'a pas d'année). */
    private function absencesJson(Collection $eleves, AnneeScolaire $annee): array
    {
        return $eleves->mapWithKeys(function (Eleve $eleve) use ($annee) {
            $presences = Presence::where('eleve_id', $eleve->id)
                ->whereIn('statut', ['absent', 'retard'])
                ->whereHas('seance', fn ($q) => $q->whereBetween('date_seance', [$annee->date_debut, $annee->date_fin]))
                ->with('seance:id,date_seance')
                ->get()
                ->map(fn (Presence $p) => [
                    'date' => $p->seance->date_seance?->format('Y-m-d'),
                    'statut' => $p->statut,
                    'motif' => $p->motif,
                    'justifie' => (bool) $p->justifie,
                    'remarque' => $p->remarque,
                ])->values()->all();

            return [$eleve->id => $presences];
        })->all();
    }

    /** Sanctions de l'année, par élève — datées directement via trimestre_id. */
    private function disciplineJson(Collection $eleves, AnneeScolaire $annee): array
    {
        $trimestreIds = Trimestre::where('annee_scolaire_id', $annee->id)->pluck('id');

        return $eleves->mapWithKeys(function (Eleve $eleve) use ($trimestreIds) {
            $sanctions = Sanction::where('eleve_id', $eleve->id)
                ->whereIn('trimestre_id', $trimestreIds)
                ->get()
                ->map(fn (Sanction $s) => [
                    'type' => $s->type,
                    'motif' => $s->motif,
                    'commentaire' => $s->commentaire,
                    'date_sanction' => $s->date_sanction?->format('Y-m-d'),
                    'statut' => $s->statut,
                    'impacte_bulletin' => (bool) $s->impacte_bulletin,
                ])->values()->all();

            return [$eleve->id => $sanctions];
        })->all();
    }

    /** Visites à l'infirmerie de l'année, par élève — via date_visite (pas de trimestre_id sur ce modèle). */
    private function infirmerieJson(Collection $eleves, AnneeScolaire $annee): array
    {
        return $eleves->mapWithKeys(function (Eleve $eleve) use ($annee) {
            $visites = VisiteInfirmerie::where('eleve_id', $eleve->id)
                ->whereBetween('date_visite', [$annee->date_debut, $annee->date_fin])
                ->get()
                ->map(fn (VisiteInfirmerie $v) => [
                    'date_visite' => $v->date_visite?->format('Y-m-d H:i'),
                    'raison' => $v->raison,
                    'soins_prodiges' => $v->soins_prodiges,
                    'observations' => $v->observations,
                ])->values()->all();

            return [$eleve->id => $visites];
        })->all();
    }
}
