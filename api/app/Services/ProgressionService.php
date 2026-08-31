<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ProgressionItem;
use App\Models\Sequence;
use App\Models\Setting;
use App\Models\Trimestre;
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
     * portant sa fiche de préparation et son état d'avancement.
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
     * Vue simplifiée du programme pour le portail parent : titres des leçons
     * uniquement, dans l'ordre du programme, avec la dernière traitée — « où
     * l'enseignant s'est arrêté en classe ». Pas de fiche de préparation :
     * ce n'est pas le registre pédagogique de l'enseignant.
     *
     * @return array{lecons: list<array{id:int, titre:string, traitee:bool}>, derniere_lecon_id: ?int}
     */
    public function programmeParent(ClasseMatiere $classeMatiere): array
    {
        $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->where('type', 'lecon')
            ->withCount('seances')
            ->orderBy('ordre')->orderBy('id')
            ->get()
            ->map(fn (ProgressionItem $item) => [
                'id' => $item->id,
                'titre' => $item->titre,
                'traitee' => $item->seances_count > 0,
            ]);

        $derniereTraitee = $lecons->filter(fn (array $l) => $l['traitee'])->last();

        return [
            'lecons' => $lecons->values()->all(),
            'derniere_lecon_id' => $derniereTraitee['id'] ?? null,
        ];
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
                'a_preparation' => $this->aPreparation($item),
                'sequence' => $item->sequence ? [
                    'id' => $item->sequence->id,
                    'libelle' => $item->sequence->libelle,
                    'trimestre' => $item->sequence->trimestre?->libelle,
                    'numero' => $this->numeroGlobal($item->sequence, $parTrimestre),
                ] : null,
                // Une leçon est traitée dès qu'une séance l'a couverte.
                'traitee' => $item->type === 'lecon' ? $item->seances_count > 0 : null,
                'seances_count' => $item->seances_count,
                /*
                 * La fiche voyage avec la ligne : l'enseignant la remplit dans
                 * la progression, sans second écran. La charger séparément
                 * aurait valu une requête par leçon à chaque dépliage.
                 */
                ...$this->fiche($item),
                'enfants' => $this->brancher($items, $item->id, $parTrimestre),
            ])
            ->values();
    }

    /**
     * Les champs de la fiche d'une leçon, vides ailleurs : un module n'a ni
     * topic ni activités, mais la ligne doit garder la même forme côté client.
     *
     * @return array<string, mixed>
     */
    private function fiche(ProgressionItem $item): array
    {
        $estLecon = $item->type === 'lecon';
        $fiche = [];

        foreach (ProgressionItem::CHAMPS_FICHE as $champ) {
            $fiche[$champ] = $estLecon ? $item->{$champ} : null;
        }

        $fiche['semaine'] = $estLecon ? $item->semaine : null;
        $fiche['duree'] = $estLecon ? $item->duree : null;
        $fiche['date_prevue'] = $estLecon ? $item->date_prevue?->toDateString() : null;
        $fiche['date_realisee'] = $estLecon ? $item->date_realisee?->toDateString() : null;
        $fiche['colonnes_libres'] = $estLecon ? ($item->colonnes_libres ?? []) : [];

        return $fiche;
    }

    /** Une leçon a une fiche de préparation dès qu'un de ses champs a été renseigné. */
    private function aPreparation(ProgressionItem $item): bool
    {
        if ($item->type !== 'lecon') {
            return false;
        }

        foreach (ProgressionItem::CHAMPS_FICHE as $champ) {
            if (filled($item->{$champ})) {
                return true;
            }
        }

        return filled($item->duree) || ($item->colonnes_libres ?? []) !== [];
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
     * `$personnelId` restreint aux seules matières de cet enseignant — un
     * enseignant qui partage la classe avec des collègues sans en être
     * titulaire ne doit voir que ce qui lui est confié.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tauxClasse(Classe $classe, ?int $personnelId = null): Collection
    {
        return $classe->classeMatieres()->where('statut', 'actif')
            ->when($personnelId !== null, fn ($q) => $q->where('personnel_id', $personnelId))
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
     * Taux d'avancement d'une affectation, scopé à un trimestre — distingue
     * ce qui est prévu/couvert sur l'année entière de ce qui l'est pour CE
     * trimestre seulement (rubrique « Couverture des programmes » du rapport
     * de fin de trimestre MINEDUB).
     *
     * On se limite aux séquences retenues du trimestre
     * (`Trimestre::sequencesRetenues()`) plutôt qu'à toutes les séquences en
     * base : des séquences excédentaires non supprimées (cf. son docblock)
     * fausseraient sinon le « prévu ce trimestre ».
     *
     * @return array{lecons_annee: int, taux_annee: float, lecons_trimestre: int, traitees_trimestre: int, taux_trimestre: float}
     */
    public function tauxAffectationTrimestre(ClasseMatiere $classeMatiere, Trimestre $trimestre): array
    {
        $annuel = $this->tauxAffectation($classeMatiere);

        $sequenceIds = $trimestre->sequencesRetenues()->pluck('id');

        $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->lecons()
            ->whereIn('sequence_id', $sequenceIds);

        $totalTrimestre = (clone $lecons)->count();
        $traiteesTrimestre = (clone $lecons)->has('seances')->count();

        return [
            'lecons_annee' => $annuel['lecons'],
            'taux_annee' => $annuel['taux'],
            'lecons_trimestre' => $totalTrimestre,
            'traitees_trimestre' => $traiteesTrimestre,
            'taux_trimestre' => $totalTrimestre > 0 ? round($traiteesTrimestre / $totalTrimestre * 100, 1) : 0.0,
        ];
    }

    /**
     * Avancement matière par matière pour une classe, scopé à un trimestre —
     * même filtre de périmètre (`$personnelId`) que `tauxClasse()`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tauxClasseTrimestre(Classe $classe, Trimestre $trimestre, ?int $personnelId = null): Collection
    {
        return $classe->classeMatieres()->where('statut', 'actif')
            ->when($personnelId !== null, fn ($q) => $q->where('personnel_id', $personnelId))
            ->with(['matiere', 'enseignant'])->get()
            ->map(fn (ClasseMatiere $cm) => [
                'classe_matiere_id' => $cm->id,
                'matiere' => $cm->matiere->nom,
                'enseignant' => $cm->enseignant?->nom_complet ?? $classe->titulaire?->nom_complet,
                ...$this->tauxAffectationTrimestre($cm, $trimestre),
            ])
            ->values();
    }

    /**
     * Avancement de tout l'établissement, une ligne par classe.
     *
     * `$classeIds` restreint les classes listées au périmètre du compte
     * (`null` = tout l'établissement). `$personnelIdPourClasse` décide, classe
     * par classe, s'il faut en plus filtrer les matières affichées.
     *
     * @param  list<int>|null  $classeIds
     * @return Collection<int, array<string, mixed>>
     */
    public function tauxEtablissement(int|array $schoolId, ?array $classeIds = null, ?\Closure $personnelIdPourClasse = null): Collection
    {
        return Classe::forSchool($schoolId)
            ->when($classeIds !== null, fn ($q) => $q->whereIn('id', $classeIds))
            ->with('niveauScolaire')
            ->orderBy('nom')
            ->get()
            ->map(function (Classe $classe) use ($personnelIdPourClasse) {
                $matieres = $this->tauxClasse($classe, $personnelIdPourClasse ? $personnelIdPourClasse($classe) : null);
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
            $estLecon = $noeud['type'] === 'lecon';

            $attributs = [
                'classe_matiere_id' => $classeMatiere->id,
                'parent_id' => $parentId,
                'type' => $noeud['type'],
                'titre' => $noeud['titre'],
                'description' => $noeud['description'] ?? null,
                'ordre' => $ordre + 1,
                // Seule une leçon porte une séquence cible et une fiche.
                'sequence_id' => $estLecon ? ($noeud['sequence_id'] ?? null) : null,
                'duree_prevue' => $estLecon ? ($noeud['duree_prevue'] ?? null) : null,
                'semaine' => $estLecon ? ($noeud['semaine'] ?? null) : null,
                'duree' => $estLecon ? ($noeud['duree'] ?? null) : null,
                'date_prevue' => $estLecon ? ($noeud['date_prevue'] ?? null) : null,
                'date_realisee' => $estLecon ? ($noeud['date_realisee'] ?? null) : null,
                // Colonnes libres de la matière : {colonne_id: valeur}, un
                // module ou chapitre n'en porte pas.
                'colonnes_libres' => $estLecon ? ($noeud['colonnes_libres'] ?? null) : null,
            ];

            /*
             * La fiche de préparation se remplit dans la progression
             * elle-même, au format du gabarit de l'établissement : ses champs
             * arrivent donc avec le nœud plutôt que d'être saisis dans un
             * second écran. Ils ne concernent qu'une leçon — un module n'a ni
             * topic ni activités — et sont remis à null sur les autres types
             * pour qu'un élément converti en chapitre ne traîne pas une fiche
             * orpheline.
             */
            foreach (ProgressionItem::CHAMPS_FICHE as $champ) {
                $attributs[$champ] = $estLecon ? ($noeud[$champ] ?? null) : null;
            }

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
