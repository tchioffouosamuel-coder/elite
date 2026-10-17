<?php

namespace App\Support\Progression;

use App\Models\ClasseMatiere;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Détermine, pour une affectation classe↔matière donnée, si sa fiche de
 * progression (colonnes et items) se lit/s'écrit dans les lignes
 * historiques — propres à CETTE affectation — ou dans les lignes
 * partagées — communes à toutes les classes où le même enseignant donne la
 * même matière au même niveau (BUG1 de la punch-list Elites-tech : avant ce
 * correctif, un enseignant qui donne Maths en 6ème A et 6ème B devait
 * remplir deux fiches identiques).
 *
 * Le niveau se lit comme ailleurs dans l'app (cf. `ConseilClasseService`,
 * `ArchivageService::archiverEcole()`) : `niveau_scolaire_id` au
 * primaire/maternelle (SIL, CP… — plus fin que le simple cycle porté par
 * `Classe::niveau_id`), `niveau_classe` (le libellé libre, ex. « 6ème »)
 * sinon. Une classe qui ne porte ni l'un ni l'autre n'a pas de niveau
 * identifiable : impossible d'y grouper quoi que ce soit, on reste alors en
 * mode historique.
 *
 * Règles de résolution, dans cet ordre :
 *  1. des lignes historiques existent déjà pour CETTE affectation
 *     (`classe_matiere_id`) → on continue à les servir telles quelles, sans
 *     fusion automatique avec une éventuelle fiche partagée ;
 *  2. sinon, des lignes partagées existent déjà pour la clé (matière,
 *     niveau, enseignant) → on les sert ;
 *  3. sinon → nouveau mode par défaut : les lignes créées sont partagées
 *     (sauf si la classe n'a pas de niveau identifiable, cf. ci-dessus, où
 *     l'on reste en mode historique).
 */
class ResolveurFicheProgression
{
    /** @return array{matiere_id:int, niveau_scolaire_id:?int, niveau_classe:?string, personnel_id:int}|null */
    public static function cle(ClasseMatiere $classeMatiere): ?array
    {
        $classe = $classeMatiere->loadMissing('classe')->classe;

        // Sans enseignant assigné, « le même enseignant » ne veut rien dire :
        // pas de partage possible, on reste sur l'affectation précise.
        if ($classeMatiere->personnel_id === null) {
            return null;
        }

        $niveauScolaireId = $classe->niveau_scolaire_id;
        $niveauClasse = $niveauScolaireId ? null : $classe->niveau_classe;

        if ($niveauScolaireId === null && blank($niveauClasse)) {
            return null;
        }

        return [
            'matiere_id' => $classeMatiere->matiere_id,
            'niveau_scolaire_id' => $niveauScolaireId,
            'niveau_classe' => $niveauClasse,
            'personnel_id' => $classeMatiere->personnel_id,
        ];
    }

    /**
     * Vrai si la fiche de cette affectation est servie depuis les lignes
     * partagées (matière + niveau + enseignant) plutôt que depuis des lignes
     * historiques propres à `classe_matiere_id` — à l'usage de l'écran, qui
     * peut vouloir l'indiquer (ex. « cette fiche est commune à vos classes
     * de 6ème »).
     */
    public static function estPartagee(ClasseMatiere $classeMatiere, string $modele = ProgressionItem::class): bool
    {
        if (self::legacyExiste($classeMatiere, $modele)) {
            return false;
        }

        return self::cle($classeMatiere) !== null;
    }

    /** @param  class-string<ProgressionColonne>|class-string<ProgressionItem>  $modele */
    public static function itemsScope(ClasseMatiere $classeMatiere): Builder
    {
        return self::scope($classeMatiere, ProgressionItem::class);
    }

    public static function colonnesScope(ClasseMatiere $classeMatiere): Builder
    {
        return self::scope($classeMatiere, ProgressionColonne::class);
    }

    /**
     * Attributs à fusionner lors d'une création — `classe_matiere_id` seul
     * en mode historique, la clé de partage (avec `classe_matiere_id` nul)
     * en mode partagé.
     *
     * @return array<string, mixed>
     */
    public static function attributsCreation(ClasseMatiere $classeMatiere, string $modele): array
    {
        if (self::legacyExiste($classeMatiere, $modele)) {
            return self::attributsLegacy($classeMatiere);
        }

        $cle = self::cle($classeMatiere);

        return $cle !== null
            ? [...$cle, 'classe_matiere_id' => null]
            : self::attributsLegacy($classeMatiere);
    }

    /** @param  class-string<ProgressionColonne>|class-string<ProgressionItem>  $modele */
    private static function scope(ClasseMatiere $classeMatiere, string $modele): Builder
    {
        $query = $modele::query();

        if (self::legacyExiste($classeMatiere, $modele)) {
            return $query->where('classe_matiere_id', $classeMatiere->id);
        }

        $cle = self::cle($classeMatiere);

        if ($cle === null) {
            return $query->where('classe_matiere_id', $classeMatiere->id);
        }

        $query->whereNull('classe_matiere_id')
            ->where('matiere_id', $cle['matiere_id'])
            ->where('personnel_id', $cle['personnel_id']);

        $cle['niveau_scolaire_id'] !== null
            ? $query->where('niveau_scolaire_id', $cle['niveau_scolaire_id'])
            : $query->whereNull('niveau_scolaire_id');

        $cle['niveau_classe'] !== null
            ? $query->where('niveau_classe', $cle['niveau_classe'])
            : $query->whereNull('niveau_classe');

        return $query;
    }

    /** @param  class-string<ProgressionColonne>|class-string<ProgressionItem>  $modele */
    private static function legacyExiste(ClasseMatiere $classeMatiere, string $modele): bool
    {
        return $modele::where('classe_matiere_id', $classeMatiere->id)->exists();
    }

    private static function attributsLegacy(ClasseMatiere $classeMatiere): array
    {
        return [
            'classe_matiere_id' => $classeMatiere->id,
            'matiere_id' => null, 'niveau_scolaire_id' => null, 'niveau_classe' => null, 'personnel_id' => null,
        ];
    }
}
