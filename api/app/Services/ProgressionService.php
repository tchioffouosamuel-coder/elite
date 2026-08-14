<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ProgressionItem;
use App\Models\Sequence;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Programme d'enseignement annuel et avancement réel.
 *
 * L'avancement se mesure en leçons : modules et chapitres ne sont que des
 * regroupements, et les compter fausserait le taux — une matière à gros
 * chapitres paraîtrait plus avancée qu'une matière au découpage fin.
 */
class ProgressionService extends BaseService
{
    /**
     * Arbre du programme d'une affectation classe↔matière, chaque leçon
     * portant son état d'avancement.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function arbre(ClasseMatiere $classeMatiere): Collection
    {
        $items = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->with('sequence.trimestre')
            ->withCount('seances')
            ->orderBy('ordre')->orderBy('id')
            ->get();

        // Lu une fois : recompter les séquences par élément multiplierait les
        // requêtes sur un arbre qui en compte souvent plusieurs dizaines.
        $parTrimestre = max((int) Setting::get($classeMatiere->classe->school_id, 'num_sequences', 2), 1);

        return $this->brancher($items, null, $parTrimestre);
    }

    /**
     * @param  Collection<int, ProgressionItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function brancher(Collection $items, ?int $parentId, int $parTrimestre): Collection
    {
        return $items
            ->where('parent_id', $parentId)
            ->map(fn (ProgressionItem $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'titre' => $item->titre,
                'description' => $item->description,
                'ordre' => $item->ordre,
                'duree_prevue' => $item->duree_prevue,
                'sequence' => $item->sequence ? [
                    'id' => $item->sequence->id,
                    'libelle' => $item->sequence->libelle,
                    'trimestre' => $item->sequence->trimestre?->libelle,
                    'numero' => $this->numeroGlobal($item->sequence, $parTrimestre),
                ] : null,
                // Une leçon est traitée dès qu'une séance l'a couverte.
                'traitee' => $item->type === 'lecon' ? $item->seances_count > 0 : null,
                'seances_count' => $item->seances_count,
                'enfants' => $this->brancher($items, $item->id, $parTrimestre),
            ])
            ->values();
    }

    /**
     * Numéro de séquence sur l'année entière : les enseignants raisonnent en
     * « séquence 5 », pas en « deuxième séquence du deuxième trimestre ».
     */
    private function numeroGlobal(Sequence $sequence, int $parTrimestre): int
    {
        $ordreTrimestre = $sequence->trimestre?->ordre ?? 1;

        return ($ordreTrimestre - 1) * $parTrimestre + $sequence->ordre;
    }

    /**
     * Taux d'avancement d'une affectation : part des leçons prévues qui ont
     * été traitées.
     *
     * @return array{lecons: int, traitees: int, taux: float}
     */
    public function tauxAffectation(ClasseMatiere $classeMatiere): array
    {
        $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)->lecons();

        $total = (clone $lecons)->count();
        $traitees = (clone $lecons)->has('seances')->count();

        return [
            'lecons' => $total,
            'traitees' => $traitees,
            'taux' => $total > 0 ? round($traitees / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Avancement matière par matière pour une classe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tauxClasse(Classe $classe): Collection
    {
        return $classe->classeMatieres()->where('statut', 'actif')
            ->with(['matiere', 'enseignant'])->get()
            ->map(fn (ClasseMatiere $cm) => [
                'classe_matiere_id' => $cm->id,
                'matiere' => $cm->matiere->nom,
                'enseignant' => $cm->enseignant?->nom_complet ?? $classe->titulaire?->nom_complet,
                ...$this->tauxAffectation($cm),
            ])
            ->values();
    }

    /**
     * Avancement de tout l'établissement, une ligne par classe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tauxEtablissement(int $schoolId, ?int $anneeScolaireId = null): Collection
    {
        return Classe::forSchool($schoolId)
            ->when($anneeScolaireId, fn ($q, $id) => $q->where('annee_scolaire_id', $id))
            ->with('niveauScolaire')
            ->orderBy('nom')
            ->get()
            ->map(function (Classe $classe) {
                $matieres = $this->tauxClasse($classe);
                $lecons = $matieres->sum('lecons');
                $traitees = $matieres->sum('traitees');

                return [
                    'classe_id' => $classe->id,
                    'classe' => $classe->nom,
                    'niveau' => $classe->niveauScolaire?->libelle,
                    'lecons' => $lecons,
                    'traitees' => $traitees,
                    'taux' => $lecons > 0 ? round($traitees / $lecons * 100, 1) : 0.0,
                    'matieres' => $matieres,
                ];
            })
            ->values();
    }

    /**
     * Enregistre l'arbre complet d'une affectation. Le programme se saisit
     * d'un bloc : on remplace l'existant plutôt que de diffuser des
     * créations/suppressions unitaires, tout en conservant les identifiants
     * déjà connus pour ne pas perdre les leçons déjà rattachées à des séances.
     *
     * @param  array<int, array<string, mixed>>  $noeuds
     */
    public function remplacerArbre(ClasseMatiere $classeMatiere, array $noeuds): int
    {
        return $this->transaction(function () use ($classeMatiere, $noeuds) {
            $conserves = [];
            $compte = $this->enregistrerNiveau($classeMatiere, $noeuds, null, $conserves);

            // Ce qui n'a pas été renvoyé par le client a été supprimé dans
            // l'éditeur : la cascade emporte les sous-éléments et les liens
            // vers les séances.
            ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
                ->whereNotIn('id', $conserves ?: [0])
                ->delete();

            return $compte;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $noeuds
     * @param  array<int, int>  $conserves
     */
    private function enregistrerNiveau(ClasseMatiere $classeMatiere, array $noeuds, ?int $parentId, array &$conserves): int
    {
        $compte = 0;

        foreach (array_values($noeuds) as $ordre => $noeud) {
            $attributs = [
                'classe_matiere_id' => $classeMatiere->id,
                'parent_id' => $parentId,
                'type' => $noeud['type'],
                'titre' => $noeud['titre'],
                'description' => $noeud['description'] ?? null,
                'ordre' => $ordre + 1,
                // Seule une leçon porte une séquence cible.
                'sequence_id' => $noeud['type'] === 'lecon' ? ($noeud['sequence_id'] ?? null) : null,
                'duree_prevue' => $noeud['type'] === 'lecon' ? ($noeud['duree_prevue'] ?? null) : null,
            ];

            $item = isset($noeud['id'])
                ? ProgressionItem::where('classe_matiere_id', $classeMatiere->id)->find($noeud['id'])
                : null;

            if ($item) {
                $item->update($attributs);
            } else {
                $item = ProgressionItem::create($attributs);
            }

            $conserves[] = $item->id;
            $compte++;

            if (! empty($noeud['enfants'])) {
                $compte += $this->enregistrerNiveau($classeMatiere, $noeud['enfants'], $item->id, $conserves);
            }
        }

        return $compte;
    }
}
