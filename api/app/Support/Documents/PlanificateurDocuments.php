<?php

namespace App\Support\Documents;

use App\Models\AnneeScolaire;
use App\Models\ArchiveClasseAnnee;
use App\Models\BudgetPersonnel;
use App\Models\BulletinPaie;
use App\Models\BusVehicule;
use App\Models\BusVersement;
use App\Models\Classe;
use App\Models\ConseilClasse;
use App\Models\Departement;
use App\Models\Eleve;
use App\Models\Personnel;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\SousSysteme;
use App\Models\Trimestre;
use App\Models\VenteFourniture;
use App\Models\Versement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Traduit une sélection — documents × formats, sur un périmètre de la
 * hiérarchie Toutes les écoles › École › Sous-système › Niveau › Classe —
 * en la liste exacte des fichiers à produire, chacun avec la route à
 * appeler et son dossier dans le ZIP.
 *
 * Les identifiants de trimestre et d'année diffèrent d'une école à l'autre :
 * l'utilisateur choisit donc un trimestre par son rang (ou « actif ») et une
 * année par son libellé, résolus ici école par école.
 */
class PlanificateurDocuments
{
    public const MAX_FICHIERS = 5000;

    /** @var array<int, array<string, mixed>> */
    private array $contextesEcole = [];

    /** @var Collection<int, Classe>|null */
    private ?Collection $classes = null;

    /**
     * @param  list<int>  $schoolIdsAccessibles
     * @param  array{school_id?: ?int, sous_systeme_id?: ?int, niveau_id?: ?int, classe_id?: ?int, eleve_id?: ?int, personnel_id?: ?int}  $perimetre
     * @param  array<string, mixed>  $parametres
     */
    public function __construct(
        private readonly array $schoolIdsAccessibles,
        private readonly array $perimetre,
        private readonly array $parametres,
    ) {}

    /**
     * @param  list<array{code: string, formats: list<string>}>  $selection
     * @return list<array<string, mixed>>
     */
    public function planifier(array $selection): array
    {
        $this->verifierParametres($selection);

        $items = [];

        foreach ($selection as $choix) {
            $definition = CatalogueDocuments::trouver($choix['code']);

            foreach ($choix['formats'] as $format) {
                $chemin = $definition['formats'][$format] ?? null;
                if ($chemin === null) {
                    continue;
                }

                foreach ($this->cibles($definition) as $cible) {
                    $items[] = $this->item($choix['code'], $definition, $format, $chemin, $cible);

                    if (count($items) > self::MAX_FICHIERS) {
                        throw ValidationException::withMessages(['documents' => [
                            'Plus de '.self::MAX_FICHIERS.' fichiers pour cette sélection : réduisez le périmètre ou le nombre de documents.',
                        ]]);
                    }
                }
            }
        }

        return $items;
    }

    /** @param  list<array{code: string, formats: list<string>}>  $selection */
    private function verifierParametres(array $selection): void
    {
        $manquants = [];

        foreach ($selection as $choix) {
            $definition = CatalogueDocuments::trouver($choix['code']);
            foreach ($definition['parametres'] ?? [] as $parametre) {
                $ok = match ($parametre) {
                    'periode' => ! empty($this->parametres['du']) && ! empty($this->parametres['au']),
                    'paie' => ! empty($this->parametres['mois']) && ! empty($this->parametres['annee_paie']),
                    'liste_classe', 'liste_personnel', 'liste_transport' => ! empty($this->parametres[$parametre]['colonnes'] ?? null),
                    default => true, // trimestre/annee/date/semaine : « actif »/« aujourd'hui » par défaut
                };

                if (! $ok) {
                    $manquants[] = "« {$definition['libelle']} » exige : ".self::libelleParametre($parametre).'.';
                }
            }
        }

        if ($manquants !== []) {
            throw ValidationException::withMessages(['parametres' => array_values(array_unique($manquants))]);
        }
    }

    public static function libelleParametre(string $parametre): string
    {
        return match ($parametre) {
            'periode' => 'une période (du / au)',
            'paie' => 'un mois et une année de paie',
            'liste_classe' => 'des colonnes de liste de classe',
            'liste_personnel' => 'des colonnes de liste du personnel',
            'liste_transport' => 'des colonnes de liste du transport',
            default => $parametre,
        };
    }

    // ─── Énumération des cibles ─────────────────────────────────────────

    /**
     * Chaque cible : école concernée, jetons propres ({id}, {classe}…) et
     * libellés pour le dossier et le nom du fichier.
     *
     * @param  array<string, mixed>  $definition
     * @return iterable<array<string, mixed>>
     */
    private function cibles(array $definition): iterable
    {
        $types = $definition['types_ecole'] ?? null;

        foreach ($this->ecoles() as $ecole) {
            if ($types !== null && ! in_array($ecole->type, $types, true)) {
                continue;
            }

            yield from match ($definition['unite']) {
                'ecole' => $this->ciblesEcole($ecole, $definition['filtres'] ?? []),
                'sous_systeme' => $this->ciblesSousSysteme($ecole),
                'classe' => $this->ciblesClasse($ecole, (bool) ($definition['classes_examen'] ?? false)),
                'eleve' => $this->ciblesEleve($ecole),
                'personnel' => $this->ciblesPersonnel($ecole),
                'vehicule' => $this->ciblesSimples($ecole, BusVehicule::where('school_id', $ecole->id)->orderBy('immatriculation')->get(), 'Transport', fn ($v) => $v->immatriculation),
                'departement' => $this->ciblesSimples($ecole, Departement::where('school_id', $ecole->id)->orderBy('nom')->get(), 'Départements', fn ($d) => $d->nom),
                'budget' => $this->ciblesBudget($ecole),
                'conseil' => $this->ciblesConseil($ecole),
                'archive_classe' => $this->ciblesArchiveClasse($ecole),
                'archive_eleve' => $this->ciblesArchiveEleve($ecole),
                'versement' => $this->ciblesVersement($ecole),
                'versement_bus' => $this->ciblesVersementBus($ecole),
                'preinscription' => $this->ciblesPreinscription($ecole),
                'vente' => $this->ciblesVente($ecole),
                'bulletin_paie' => $this->ciblesBulletinPaie($ecole),
                default => [],
            };
        }
    }

    /** @param  array<string, string>  $filtres */
    private function ciblesEcole(School $ecole, array $filtres): iterable
    {
        $descend = $this->perimetre['sous_systeme_id'] ?? $this->perimetre['niveau_id'] ?? $this->perimetre['classe_id'] ?? null;

        // Un document d'école qui sait se restreindre à une classe : dès que
        // le périmètre descend sous l'école, un fichier par classe visée.
        if ($descend !== null && isset($filtres['classe_id'])) {
            foreach ($this->classesDe($ecole) as $classe) {
                yield $this->cible($ecole, ['classe_id' => $classe->id], $this->dossierClasse($classe), $classe->nom);
            }

            return;
        }

        if (! empty($this->perimetre['sous_systeme_id']) && isset($filtres['sous_systeme_id'])) {
            $ss = SousSysteme::where('school_id', $ecole->id)->find($this->perimetre['sous_systeme_id']);
            if ($ss) {
                yield $this->cible($ecole, ['sous_systeme_id' => $ss->id], [$ecole->name, $ss->nom], $ss->nom);
            }

            return;
        }

        yield $this->cible($ecole, [], [$ecole->name], null);
    }

    private function ciblesSousSysteme(School $ecole): iterable
    {
        $ids = $this->classesDe($ecole)->pluck('sous_systeme_id')->filter()->unique();

        foreach (SousSysteme::whereIn('id', $ids)->orderBy('nom')->get() as $ss) {
            yield $this->cible($ecole, ['id' => $ss->id, 'sous_systeme' => $ss->id], [$ecole->name, $ss->nom], $ss->nom);
        }
    }

    private function ciblesClasse(School $ecole, bool $examenSeulement): iterable
    {
        foreach ($this->classesDe($ecole) as $classe) {
            if ($examenSeulement && blank($classe->code_examen)) {
                continue;
            }

            yield $this->cible($ecole, ['id' => $classe->id, 'classe' => $classe->id], $this->dossierClasse($classe), $classe->nom);
        }
    }

    private function ciblesEleve(School $ecole): iterable
    {
        $classes = $this->classesDe($ecole)->keyBy('id');
        $requete = Eleve::where('school_id', $ecole->id)->whereIn('classe_id', $classes->keys())->where('statut', 'actif');

        if (! empty($this->perimetre['eleve_id'])) {
            $requete->whereKey($this->perimetre['eleve_id']);
        }

        foreach ($requete->orderBy('nom_complet')->get(['id', 'classe_id', 'nom_complet', 'matricule']) as $eleve) {
            $classe = $classes[$eleve->classe_id];
            yield $this->cible($ecole, ['id' => $eleve->id, 'classe' => $classe->id], $this->dossierClasse($classe), $eleve->nom_complet);
        }
    }

    private function ciblesPersonnel(School $ecole): iterable
    {
        $requete = Personnel::where('school_id', $ecole->id)->where('statut', 'actif');

        if (! empty($this->perimetre['personnel_id'])) {
            $requete->whereKey($this->perimetre['personnel_id']);
        }

        foreach ($requete->orderBy('nom_complet')->get(['id', 'nom_complet']) as $agent) {
            yield $this->cible($ecole, ['id' => $agent->id], [$ecole->name, 'Personnel'], $agent->nom_complet);
        }
    }

    /** @param  Collection<int, Model>  $modeles */
    private function ciblesSimples(School $ecole, Collection $modeles, string $dossier, callable $libelle): iterable
    {
        foreach ($modeles as $modele) {
            yield $this->cible($ecole, ['id' => $modele->getKey()], [$ecole->name, $dossier], $libelle($modele));
        }
    }

    private function ciblesBudget(School $ecole): iterable
    {
        $annee = $this->contexte($ecole)['annee_id'];
        $budgets = BudgetPersonnel::where('school_id', $ecole->id)->whereNull('annule_le')
            ->when($annee, fn ($q) => $q->where('annee_scolaire_id', $annee))
            ->orderBy('date_allocation')->get();

        return $this->ciblesSimples($ecole, $budgets, 'Budgets', fn ($b) => $b->libelle);
    }

    private function ciblesConseil(School $ecole): iterable
    {
        $classes = $this->classesDe($ecole)->keyBy('id');
        $conseils = ConseilClasse::where('school_id', $ecole->id)
            ->whereIn('classe_id', $classes->keys())
            ->where('annee_scolaire_id', $this->contexte($ecole)['annee_id'])
            ->get();

        foreach ($conseils as $conseil) {
            $classe = $classes[$conseil->classe_id];
            yield $this->cible($ecole, ['id' => $conseil->id, 'classe' => $classe->id], $this->dossierClasse($classe), $classe->nom);
        }
    }

    /** @return Collection<int, ArchiveClasseAnnee> */
    private function archives(School $ecole): Collection
    {
        $requete = ArchiveClasseAnnee::where('school_id', $ecole->id)
            ->where('annee_scolaire_id', $this->contexte($ecole)['annee_id']);

        if ($this->perimetreSousEcole()) {
            $requete->whereIn('classe_id', $this->classesDe($ecole)->pluck('id'));
        }

        return $requete->orderBy('classe_nom')->get();
    }

    private function ciblesArchiveClasse(School $ecole): iterable
    {
        foreach ($this->archives($ecole) as $archive) {
            yield $this->cible($ecole, ['id' => $archive->classe_id, 'classe' => $archive->classe_id], [$ecole->name, 'Archives', $archive->classe_nom], $archive->classe_nom);
        }
    }

    private function ciblesArchiveEleve(School $ecole): iterable
    {
        foreach ($this->archives($ecole) as $archive) {
            foreach ($archive->roster_json ?? [] as $eleve) {
                if (! empty($this->perimetre['eleve_id']) && (int) $eleve['eleve_id'] !== (int) $this->perimetre['eleve_id']) {
                    continue;
                }
                yield $this->cible($ecole, ['id' => $eleve['eleve_id'], 'classe' => $archive->classe_id], [$ecole->name, 'Archives', $archive->classe_nom], $eleve['nom_complet'] ?? (string) $eleve['eleve_id']);
            }
        }
    }

    /** Restreint une requête d'écritures financières aux élèves du périmètre, quand il descend sous l'école. */
    private function filtrerParEleve(Builder $requete, School $ecole, string $relation): Builder
    {
        if (! empty($this->perimetre['eleve_id'])) {
            return $requete->whereHas($relation, fn ($q) => $q->whereKey($this->perimetre['eleve_id']));
        }

        if ($this->perimetreSousEcole()) {
            $classes = $this->classesDe($ecole)->pluck('id');
            $requete->whereHas($relation, fn ($q) => $q->whereIn('classe_id', $classes));
        }

        return $requete;
    }

    private function ciblesVersement(School $ecole): iterable
    {
        $requete = Versement::where('school_id', $ecole->id)->whereNull('annule_le')
            ->whereBetween('date_versement', [$this->parametres['du'], $this->parametres['au']]);
        $this->filtrerParEleve($requete, $ecole, 'dossier.eleve');

        foreach ($requete->with('dossier.eleve:id,nom_complet')->orderBy('date_versement')->get() as $v) {
            yield $this->cible($ecole, ['id' => $v->id], [$ecole->name, 'Reçus scolarité'], $v->numero_recu.' '.($v->dossier?->eleve?->nom_complet ?? ''));
        }
    }

    private function ciblesVersementBus(School $ecole): iterable
    {
        $requete = BusVersement::where('school_id', $ecole->id)->whereNull('annule_le')
            ->whereBetween('date_versement', [$this->parametres['du'], $this->parametres['au']]);
        $this->filtrerParEleve($requete, $ecole, 'affectation.eleve');

        foreach ($requete->with('affectation.eleve:id,nom_complet')->orderBy('date_versement')->get() as $v) {
            yield $this->cible($ecole, ['id' => $v->id], [$ecole->name, 'Reçus transport'], $v->numero_recu.' '.($v->affectation?->eleve?->nom_complet ?? ''));
        }
    }

    private function ciblesPreinscription(School $ecole): iterable
    {
        $requete = Preinscription::where('school_id', $ecole->id)
            ->where(fn ($q) => $q->whereNotNull('versement_id')->orWhereNotNull('bus_versement_id'))
            ->whereBetween('created_at', [$this->parametres['du'].' 00:00:00', $this->parametres['au'].' 23:59:59']);

        if ($this->perimetreSousEcole()) {
            $requete->whereIn('classe_id', $this->classesDe($ecole)->pluck('id'));
        }

        foreach ($requete->orderBy('created_at')->get() as $p) {
            $nom = $p->donnees_eleve['nom_complet'] ?? $p->donnees_eleve['nom'] ?? "Préinscription {$p->id}";
            yield $this->cible($ecole, ['id' => $p->id], [$ecole->name, 'Reçus préinscription'], is_string($nom) ? $nom : "Préinscription {$p->id}");
        }
    }

    private function ciblesVente(School $ecole): iterable
    {
        $requete = VenteFourniture::where('school_id', $ecole->id)->whereNull('annule_le')
            ->whereBetween('date_vente', [$this->parametres['du'], $this->parametres['au']]);

        if ($this->perimetreSousEcole() || ! empty($this->perimetre['eleve_id'])) {
            $this->filtrerParEleve($requete, $ecole, 'eleve');
        }

        foreach ($requete->orderBy('date_vente')->get() as $v) {
            yield $this->cible($ecole, ['id' => $v->id], [$ecole->name, 'Factures'], (string) $v->numero_facture);
        }
    }

    private function ciblesBulletinPaie(School $ecole): iterable
    {
        $requete = BulletinPaie::where('school_id', $ecole->id)
            ->where('annee', (int) $this->parametres['annee_paie'])
            ->where('mois', (int) $this->parametres['mois'])
            ->with('personnel:id,nom_complet');

        if (! empty($this->perimetre['personnel_id'])) {
            $requete->where('personnel_id', $this->perimetre['personnel_id']);
        }

        foreach ($requete->get() as $b) {
            yield $this->cible($ecole, ['id' => $b->id], [$ecole->name, 'Paie'], $b->personnel?->nom_complet ?? (string) $b->numero);
        }
    }

    // ─── Périmètre ───────────────────────────────────────────────────────

    /** @return Collection<int, School> */
    private function ecoles(): Collection
    {
        $ids = ! empty($this->perimetre['school_id'])
            ? array_values(array_intersect([(int) $this->perimetre['school_id']], $this->schoolIdsAccessibles))
            : $this->schoolIdsAccessibles;

        return School::whereIn('id', $ids)->orderBy('name')->get();
    }

    private function perimetreSousEcole(): bool
    {
        return ! empty($this->perimetre['sous_systeme_id']) || ! empty($this->perimetre['niveau_id']) || ! empty($this->perimetre['classe_id']);
    }

    /** @return Collection<int, Classe> */
    private function classesDe(School $ecole): Collection
    {
        $this->classes ??= Classe::whereIn('school_id', $this->ecoles()->pluck('id'))
            ->when($this->perimetre['sous_systeme_id'] ?? null, fn ($q, $id) => $q->where('sous_systeme_id', $id))
            ->when($this->perimetre['niveau_id'] ?? null, fn ($q, $id) => $q->where('niveau_id', $id))
            ->when($this->perimetre['classe_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->with(['sousSysteme:id,nom', 'niveau:id,name_fr'])
            ->get()
            ->sortBy(fn (Classe $c) => sprintf('%s|%s|%s', $c->sousSysteme?->nom ?? '~', $c->niveau?->name_fr ?? '~', $c->nom), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->classes->where('school_id', $ecole->id)->values();
    }

    /** @return list<string> */
    private function dossierClasse(Classe $classe): array
    {
        return array_values(array_filter([
            $this->ecoles()->firstWhere('id', $classe->school_id)?->name,
            $classe->sousSysteme?->nom,
            $classe->niveau?->name_fr,
            $classe->nom,
        ]));
    }

    /**
     * Année et trimestre de CETTE école correspondant au choix de
     * l'utilisateur (libellé d'année, rang du trimestre).
     *
     * @return array{annee_id: ?int, trimestre_id: ?int}
     */
    private function contexte(School $ecole): array
    {
        if (isset($this->contextesEcole[$ecole->id])) {
            return $this->contextesEcole[$ecole->id];
        }

        $libelleAnnee = $this->parametres['annee'] ?? null;
        $annee = $libelleAnnee && $libelleAnnee !== 'active'
            ? AnneeScolaire::where('school_id', $ecole->id)->where('libelle', $libelleAnnee)->first()
            : AnneeScolaire::where('school_id', $ecole->id)->where('is_active', true)->first();

        $rang = $this->parametres['trimestre'] ?? 'actif';
        $trimestre = null;
        if ($annee) {
            $trimestre = $rang === 'actif' || $rang === null
                ? Trimestre::where('annee_scolaire_id', $annee->id)->where('is_active', true)->first()
                : Trimestre::where('annee_scolaire_id', $annee->id)->where('ordre', (int) $rang)->first();
        }

        return $this->contextesEcole[$ecole->id] = [
            'annee_id' => $annee?->id,
            'trimestre_id' => $trimestre?->id,
        ];
    }

    // ─── Construction d'un élément du plan ──────────────────────────────

    /**
     * @param  array<string, mixed>  $jetons
     * @param  list<string>  $dossier
     * @return array<string, mixed>
     */
    private function cible(School $ecole, array $jetons, array $dossier, ?string $libelle): array
    {
        return ['ecole' => $ecole, 'jetons' => $jetons, 'dossier' => $dossier, 'libelle' => $libelle];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $cible
     * @return array<string, mixed>
     */
    private function item(string $code, array $definition, string $format, string $chemin, array $cible): array
    {
        /** @var School $ecole */
        $ecole = $cible['ecole'];
        $contexte = $this->contexte($ecole);

        $jetons = [
            'school' => $ecole->id,
            'annee_id' => $contexte['annee_id'],
            'trimestre_id' => $contexte['trimestre_id'],
            'du' => $this->parametres['du'] ?? null,
            'au' => $this->parametres['au'] ?? null,
            'mois' => $this->parametres['mois'] ?? null,
            'annee' => $this->parametres['annee_paie'] ?? null,
            'date' => $this->parametres['date'] ?? null,
            'semaine' => $this->parametres['semaine'] ?? null,
            ...$this->jetonsListe($definition),
            ...$cible['jetons'],
        ];

        [$route, $requeteChemin] = array_pad(explode('?', $chemin, 2), 2, '');
        parse_str($requeteChemin, $requeteFixe);

        $requete = [];
        foreach ([...($definition['requete'] ?? []), ...$requeteFixe] as $cle => $modele) {
            $valeur = $this->remplacer((string) $modele, $jetons);
            if ($valeur !== '') {
                $requete[$cle] = $valeur;
            }
        }

        // Filtres d'un document d'école restreint à une classe / un sous-système.
        foreach (['classe_id', 'sous_systeme_id'] as $filtre) {
            if (isset($cible['jetons'][$filtre])) {
                $requete[$filtre] = $cible['jetons'][$filtre];
            }
        }

        $nom = $definition['libelle'].($cible['libelle'] ? ' - '.$cible['libelle'] : '');

        return [
            'code' => $code,
            'format' => $format,
            'school_id' => $ecole->id,
            'chemin' => $this->remplacer($route, $jetons),
            'requete' => $requete,
            'dossier' => implode('/', array_map(fn ($s) => self::nettoyer($s), $cible['dossier'])),
            'nom' => self::nettoyer($nom),
        ];
    }

    /** @param  array<string, mixed>  $definition */
    private function jetonsListe(array $definition): array
    {
        foreach (['liste_classe', 'liste_personnel', 'liste_transport'] as $parametre) {
            if (in_array($parametre, $definition['parametres'] ?? [], true)) {
                $liste = $this->parametres[$parametre] ?? [];
                $moyenneType = $liste['moyenne_type'] ?? null;

                return [
                    // Titres laissés vides dans le formulaire : le libellé du document.
                    'titre_fr' => ($liste['titre_fr'] ?? '') ?: $definition['libelle'],
                    'titre_en' => ($liste['titre_en'] ?? '') ?: (($liste['titre_fr'] ?? '') ?: $definition['libelle']),
                    'colonnes' => is_array($liste['colonnes'] ?? null) ? implode(',', $liste['colonnes']) : ($liste['colonnes'] ?? ''),
                    'moyenne_type' => $moyenneType,
                    // Moyenne d'un trimestre : celui choisi, résolu pour chaque école.
                    'moyenne_reference_id' => $moyenneType === 'trimestre' ? '{trimestre_id}' : null,
                ];
            }
        }

        return [];
    }

    /** @param  array<string, mixed>  $jetons */
    private function remplacer(string $modele, array $jetons): string
    {
        // Deux passes : un jeton de liste peut lui-même renvoyer vers {trimestre_id}.
        for ($i = 0; $i < 2; $i++) {
            $modele = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($jetons[$m[1]] ?? ''), $modele);
        }

        return $modele;
    }

    /** Nom sûr pour un fichier ou un dossier dans le ZIP, sur tout système. */
    public static function nettoyer(string $texte): string
    {
        $texte = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $texte);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $texte)), 120, '');
    }
}
