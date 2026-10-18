<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\Sequence;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Models\User;
use App\Support\Progression\ResolveurFicheProgression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
        $items = ResolveurFicheProgression::itemsScope($classeMatiere)
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
        $lecons = ResolveurFicheProgression::itemsScope($classeMatiere)
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
     * Leçons prévues (`date_prevue`) de la classe, groupées en trois
     * semaines calendaires — précédente, en cours, suivante — toutes
     * matières confondues. Vue resserrée pour le portail parent : la liste
     * complète du programme (cf. {@see programmeParent()}) ne concerne que
     * l'école, pas ce que la famille a besoin de suivre au quotidien.
     *
     * Les leçons sans `date_prevue` renseignée n'apparaissent dans aucune
     * des trois semaines.
     *
     * @return array<string, array{debut:string, fin:string, lecons:list<array<string, mixed>>}>
     */
    public function leconsSemaine(Classe $classe): array
    {
        $lundiCourant = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $semaines = [
            'precedente' => $lundiCourant->clone()->subWeek(),
            'en_cours' => $lundiCourant->clone(),
            'suivante' => $lundiCourant->clone()->addWeek(),
        ];

        $debut = $semaines['precedente'];
        $fin = $semaines['suivante']->clone()->endOfWeek(Carbon::SUNDAY);

        // Interrogée affectation par affectation (et non en un seul whereIn) :
        // une affectation à fiche partagée (cf. BUG1) ne vit pas sous son
        // propre classe_matiere_id, un whereIn la raterait.
        $lecons = $classe->classeMatieres()->where('statut', 'actif')->get()
            ->flatMap(fn (ClasseMatiere $cm) => ResolveurFicheProgression::itemsScope($cm)
                ->lecons()
                ->whereBetween('date_prevue', [$debut->toDateString(), $fin->toDateString()])
                ->with('classeMatiere.matiere', 'matiere')
                ->withCount('seances')
                ->get()
                ->each(fn (ProgressionItem $item) => $item->setAttribute('_matiere_nom', $item->classeMatiere?->matiere->nom ?? $item->matiere?->nom)))
            ->unique('id');

        $resultat = [];
        foreach ($semaines as $cle => $lundi) {
            $dimanche = $lundi->clone()->endOfWeek(Carbon::SUNDAY);

            $resultat[$cle] = [
                'debut' => $lundi->toDateString(),
                'fin' => $dimanche->toDateString(),
                'lecons' => $lecons
                    ->filter(fn (ProgressionItem $l) => $l->date_prevue?->betweenIncluded($lundi, $dimanche))
                    ->map(fn (ProgressionItem $l) => [
                        'id' => $l->id,
                        'matiere' => $l->_matiere_nom,
                        'titre' => $l->titre,
                        'date_prevue' => $l->date_prevue->toDateString(),
                        'traitee' => $l->seances_count > 0,
                    ])
                    ->sortBy('date_prevue')
                    ->values()
                    ->all(),
            ];
        }

        return $resultat;
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
        $lecons = ResolveurFicheProgression::itemsScope($classeMatiere)->lecons();

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
                'matiere_id' => $cm->matiere_id,
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

        $lecons = ResolveurFicheProgression::itemsScope($classeMatiere)
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
    /**
     * Classes candidates pour une copie de fiche depuis `$source` : même
     * matière, même école, pas déjà la même fiche partagée (cf.
     * ResolveurFicheProgression — la copier ne changerait rien, elles voient
     * déjà le même contenu en direct).
     *
     * @return Collection<int, array{classe_matiere_id: int, classe: string, enseignant: ?string, a_deja_une_fiche: bool}>
     */
    public function ciblesCopie(ClasseMatiere $source): Collection
    {
        $cleSource = ResolveurFicheProgression::cle($source);

        return ClasseMatiere::where('matiere_id', $source->matiere_id)
            ->where('id', '!=', $source->id)
            ->where('statut', 'actif')
            ->whereHas('classe', fn ($q) => $q->where('school_id', $source->classe->school_id))
            ->with(['classe', 'enseignant'])
            ->get()
            ->reject(fn (ClasseMatiere $cm) => $cleSource !== null && ResolveurFicheProgression::cle($cm) === $cleSource)
            ->map(fn (ClasseMatiere $cm) => [
                'classe_matiere_id' => $cm->id,
                'classe' => $cm->classe->nom,
                'enseignant' => $cm->enseignant?->nom_complet,
                'a_deja_une_fiche' => ResolveurFicheProgression::itemsScope($cm)->exists(),
            ])
            ->values();
    }

    /**
     * Copie les leçons et colonnes d'une matière enseignée dans une classe
     * vers une ou plusieurs autres classes où la même matière est également
     * enseignée — un point de départ commun quand les classes ne partagent
     * pas déjà la même fiche (enseignants différents, par ex.). Seul le
     * contenu pédagogique est repris (titre, description, consignes...) ;
     * ce qui relève du déroulé réel de LA CLASSE SOURCE ne l'est pas : date
     * prévue/réalisée, semaine, durée, séquence, ni les séances déjà faites.
     *
     * N'écrase jamais une fiche qui a déjà du contenu — la classe cible est
     * ignorée et signalée dans le résultat plutôt que fusionnée à l'aveugle.
     *
     * @param  list<int>  $classeMatiereIdsCibles
     * @return array{copiees: list<string>, ignorees: list<string>}
     */
    public function copierVers(ClasseMatiere $source, array $classeMatiereIdsCibles): array
    {
        $cibles = ClasseMatiere::whereIn('id', $classeMatiereIdsCibles)
            ->where('matiere_id', $source->matiere_id)
            ->where('id', '!=', $source->id)
            ->with('classe')
            ->get();

        $itemsSource = ResolveurFicheProgression::itemsScope($source)->orderBy('ordre')->orderBy('id')->get();
        $colonnesSource = ResolveurFicheProgression::colonnesScope($source)->orderBy('ordre')->get();

        $copiees = [];
        $ignorees = [];

        foreach ($cibles as $cible) {
            $libelle = $cible->classe->nom;

            if (ResolveurFicheProgression::itemsScope($cible)->exists()) {
                $ignorees[] = $libelle;

                continue;
            }

            $this->transaction(function () use ($cible, $itemsSource, $colonnesSource) {
                $porteurItems = ResolveurFicheProgression::attributsCreation($cible, ProgressionItem::class);
                $porteurColonnes = ResolveurFicheProgression::attributsCreation($cible, ProgressionColonne::class);

                foreach ($colonnesSource as $colonne) {
                    ProgressionColonne::create([
                        ...$porteurColonnes,
                        'libelle' => $colonne->libelle,
                        'ordre' => $colonne->ordre,
                    ]);
                }

                // Les parents se recréent avant leurs enfants pour que
                // `parent_id` se remappe vers la copie plutôt que l'original
                // — une boucle à passes multiples plutôt qu'un simple tri,
                // l'arbre pouvant compter modules > chapitres > leçons.
                $restants = $itemsSource->keyBy('id');
                $correspondance = [];

                while ($restants->isNotEmpty()) {
                    $traites = [];

                    foreach ($restants as $item) {
                        if ($item->parent_id !== null && ! array_key_exists($item->parent_id, $correspondance)) {
                            continue;
                        }

                        $copie = ProgressionItem::create([
                            ...$porteurItems,
                            'parent_id' => $item->parent_id ? ($correspondance[$item->parent_id] ?? null) : null,
                            'type' => $item->type,
                            'titre' => $item->titre,
                            'description' => $item->description,
                            'ordre' => $item->ordre,
                            'expected_learning_outcomes' => $item->expected_learning_outcomes,
                            'topic' => $item->topic,
                            'sous_topic' => $item->sous_topic,
                            'competence' => $item->competence,
                            'entry_behaviour' => $item->entry_behaviour,
                            'teaching_aids' => $item->teaching_aids,
                            'teaching_learning_strategies' => $item->teaching_learning_strategies,
                            'learners_activities' => $item->learners_activities,
                            'facilitators_activities' => $item->facilitators_activities,
                            'assessment' => $item->assessment,
                            'assignment' => $item->assignment,
                            'remarks' => $item->remarks,
                            'duree_prevue' => $item->duree_prevue,
                            'colonnes_libres' => $item->colonnes_libres,
                        ]);

                        $correspondance[$item->id] = $copie->id;
                        $traites[] = $item->id;
                    }

                    if ($traites === []) {
                        break; // garde-fou : ne devrait jamais arriver sur un arbre bien formé
                    }

                    $restants = $restants->except($traites);
                }
            });

            $copiees[] = $libelle;
        }

        return ['copiees' => $copiees, 'ignorees' => $ignorees];
    }

    public function remplacerArbre(ClasseMatiere $classeMatiere, array $noeuds, ?User $auteur = null): int
    {
        // « Date Taught » ne se saisit pas à la main par celui qui doit
        // prouver sa présence pour enseigner : sinon toute la règle de
        // validation des séances se contourne depuis l'éditeur de
        // progression — on coche la date, la leçon compte comme réalisée,
        // sans séance, sans appel et sans QR. Elle suit la déclaration faite
        // dans « Ma journée » (cf. `MaJourneeService::enregistrer()`, qui la
        // pose sur la séance qui a réellement couvert la leçon). La direction
        // garde la main, comme elle est déjà dispensée de la preuve pour
        // pouvoir corriger à distance.
        $dateRealiseeEditable = $auteur === null || $auteur->estPersonnelDirection();

        return $this->transaction(function () use ($classeMatiere, $noeuds, $dateRealiseeEditable) {
            // Résolu une seule fois pour tout l'arbre : historique ou
            // partagé (cf. BUG1), la même fiche ne bascule pas de mode entre
            // deux nœuds d'un même enregistrement.
            $porteur = ResolveurFicheProgression::attributsCreation($classeMatiere, ProgressionItem::class);

            $conserves = [];
            $compte = $this->enregistrerNiveau($classeMatiere, $porteur, $noeuds, null, $conserves, $dateRealiseeEditable);

            // Ce qui n'a pas été renvoyé par le client a été supprimé dans
            // l'éditeur : la cascade emporte les sous-éléments et les liens
            // vers les séances.
            $this->requeteSelonPorteur($porteur)
                ->whereNotIn('id', $conserves ?: [0])
                ->delete();

            return $compte;
        });
    }

    /** Reconstruit la portée (historique ou partagée) à partir des attributs résolus par `ResolveurFicheProgression`. */
    private function requeteSelonPorteur(array $porteur): Builder
    {
        if ($porteur['classe_matiere_id'] !== null) {
            return ProgressionItem::where('classe_matiere_id', $porteur['classe_matiere_id']);
        }

        return ProgressionItem::whereNull('classe_matiere_id')
            ->where('matiere_id', $porteur['matiere_id'])
            ->where('personnel_id', $porteur['personnel_id'])
            ->when(
                $porteur['niveau_scolaire_id'] !== null,
                fn ($q) => $q->where('niveau_scolaire_id', $porteur['niveau_scolaire_id']),
                fn ($q) => $q->whereNull('niveau_scolaire_id'),
            )
            ->when(
                $porteur['niveau_classe'] !== null,
                fn ($q) => $q->where('niveau_classe', $porteur['niveau_classe']),
                fn ($q) => $q->whereNull('niveau_classe'),
            );
    }

    /**
     * @param  array<string, mixed>  $porteur  Attributs de rattachement résolus (cf. ResolveurFicheProgression)
     * @param  array<int, array<string, mixed>>  $noeuds
     * @param  array<int, int>  $conserves
     */
    private function enregistrerNiveau(ClasseMatiere $classeMatiere, array $porteur, array $noeuds, ?int $parentId, array &$conserves, bool $dateRealiseeEditable = true): int
    {
        $compte = 0;

        foreach (array_values($noeuds) as $ordre => $noeud) {
            $estLecon = $noeud['type'] === 'lecon';

            $attributs = [
                ...$porteur,
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
                ? $this->requeteSelonPorteur($porteur)->find($noeud['id'])
                : null;

            // Hors direction, la date de réalisation reste celle que le
            // serveur a posée : on garde la valeur stockée plutôt que celle
            // renvoyée par l'éditeur, et une leçon neuve naît non traitée.
            if (! $dateRealiseeEditable) {
                $attributs['date_realisee'] = $item?->date_realisee;
            }

            if ($item) {
                $item->update($attributs);
            } else {
                $item = ProgressionItem::create($attributs);
            }

            $conserves[] = $item->id;
            $compte++;

            if (! empty($noeud['enfants'])) {
                $compte += $this->enregistrerNiveau($classeMatiere, $porteur, $noeud['enfants'], $item->id, $conserves, $dateRealiseeEditable);
            }
        }

        return $compte;
    }
}
