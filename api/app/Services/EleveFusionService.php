<?php

namespace App\Services;

use App\Models\Eleve;
use Illuminate\Support\Facades\DB;

/**
 * Fusion de deux fiches élève reconnues comme doublons (même nom, même date
 * de naissance) — typiquement issues d'une ré-importation sous un nouveau
 * schéma de matricule qui a laissé l'ancienne fiche en place à côté de la
 * nouvelle.
 *
 * Contrairement à {@see ClasseFusionService}, l'argent est en jeu : un
 * paiement peut avoir été ressaisi sur les deux fiches lors de l'incident qui
 * a produit le doublon, et `dossiers_scolarite`/`versements` sont en cascade
 * de suppression sous `eleves` — supprimer la fiche source perdrait tout ce
 * qui y reste rattaché. La fusion refuse donc purement et simplement de
 * toucher aux deux fiches dès que leurs dossiers de scolarité couvrent une
 * même année : mieux vaut laisser un doublon visible qu'effacer ou dédoubler
 * silencieusement un paiement. Le reste (notes, présences, sanctions...) suit
 * la même convention que ClasseFusionService : une ligne en conflit avec une
 * contrainte unique est abandonnée au profit de celle déjà présente sur la
 * fiche conservée, plutôt que fusionnée champ à champ.
 */
class EleveFusionService extends BaseService
{
    /**
     * Tables à contrainte unique impliquant `eleve_id` : la ou les colonnes
     * listées ici sont celles qui, avec `eleve_id`, forment cette contrainte
     * — une ligne source dont cette combinaison existe déjà côté cible est
     * un doublon de la ligne cible, pas une donnée à fusionner.
     *
     * @var array<string, list<string>>
     */
    private const TABLES_CONTRAINTE_UNIQUE = [
        'eleve_tuteur' => ['tuteur_id'],
        'notes' => ['classe_matiere_id', 'sequence_id'],
        'absence_trimestres' => ['trimestre_id'],
        'presences' => ['seance_id'],
        'conseil_classe_decisions' => ['conseil_classe_id'],
        'historiques_scolarite_eleves' => ['annee_scolaire_id'],
    ];

    /** Tables portant `eleve_id` sans contrainte d'unicité : réassignation en masse, sans dédoublonnage préalable. */
    private const TABLES_SANS_CONTRAINTE = [
        'sanctions', 'visites_infirmerie', 'revendications', 'moratoires',
        'justifications_absences', 'observations', 'preinscriptions',
        'modifications_eleves', 'ventes_fournitures', 'bus_affectations',
        'remises', 'dettes_anterieures',
    ];

    public function __construct(private readonly EleveService $eleveService) {}

    /**
     * @return array{fusionne: bool, raison?: string}
     */
    public function fusionner(Eleve $conservee, Eleve $supprimee): array
    {
        if ($this->anneesFinancieresEnConflit($conservee->id, $supprimee->id)) {
            return ['fusionne' => false, 'raison' => 'chevauchement_annee_financiere'];
        }

        $this->transaction(function () use ($conservee, $supprimee) {
            foreach (self::TABLES_CONTRAINTE_UNIQUE as $table => $colonnesConflit) {
                $this->dedupliquer($table, $supprimee->id, $conservee->id, $colonnesConflit);
            }

            foreach ([...array_keys(self::TABLES_CONTRAINTE_UNIQUE), ...self::TABLES_SANS_CONTRAINTE, 'dossiers_scolarite'] as $table) {
                if (DB::getSchemaBuilder()->hasColumn($table, 'eleve_id')) {
                    DB::table($table)->where('eleve_id', $supprimee->id)->update(['eleve_id' => $conservee->id]);
                }
            }

            // Réutilise EleveService::delete() : détache les tuteurs restants
            // (déjà réassignés ou dédupliqués, donc no-op le plus souvent) et
            // supprime la photo stockée, comme toute suppression de fiche.
            $this->eleveService->delete($supprimee);
        });

        return ['fusionne' => true];
    }

    /** Vrai si les deux élèves ont chacun un dossier de scolarité sur une même année. */
    private function anneesFinancieresEnConflit(int $idA, int $idB): bool
    {
        $anneesA = DB::table('dossiers_scolarite')->where('eleve_id', $idA)->pluck('annee_scolaire_id');
        $anneesB = DB::table('dossiers_scolarite')->where('eleve_id', $idB)->pluck('annee_scolaire_id');

        return $anneesA->intersect($anneesB)->isNotEmpty();
    }

    /**
     * Supprime, côté source, les lignes dont la combinaison `eleve_id` +
     * `$colonnesConflit` existe déjà côté cible : la réassignation en masse
     * qui suit violerait sinon la contrainte unique de la table.
     *
     * @param  list<string>  $colonnesConflit
     */
    private function dedupliquer(string $table, int $sourceId, int $targetId, array $colonnesConflit): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $ciblesExistantes = DB::table($table)->where('eleve_id', $targetId)->get($colonnesConflit);

        DB::table($table)->where('eleve_id', $sourceId)
            ->get(array_merge(['id'], $colonnesConflit))
            ->each(function (object $ligne) use ($table, $colonnesConflit, $ciblesExistantes) {
                $enConflit = $ciblesExistantes->contains(
                    fn (object $cible) => collect($colonnesConflit)->every(fn (string $col) => $cible->$col === $ligne->$col)
                );
                if ($enConflit) {
                    DB::table($table)->where('id', $ligne->id)->delete();
                }
            });
    }
}
