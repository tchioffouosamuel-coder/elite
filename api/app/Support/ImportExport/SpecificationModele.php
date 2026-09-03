<?php

namespace App\Support\ImportExport;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contrat que déclare un modèle « maître » (peu de champs scalaires, au plus
 * quelques FK résolues par libellé) pour obtenir import, export et
 * téléchargement de modèle sans écrire de classe `Imports\Xxx`/`Exports\Xxx`
 * dédiée — cf. {@see \App\Imports\ImportGenerique}, {@see \App\Exports\ExportGenerique},
 * {@see \App\Exports\ModeleGenerique}, et le trait
 * {@see \App\Http\Controllers\Concerns\GereImportExport} qui les active sur
 * un contrôleur.
 *
 * Un modèle à règles métier réelles (Élèves, Personnel, Notes…) garde sa
 * propre classe Import/Export : ce contrat ne vise que les listes de
 * référence simples.
 */
interface SpecificationModele
{
    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modele(): string;

    /**
     * En-tête source normalisé (slug, comme le fait maatwebsite par défaut)
     * => clé canonique. Plusieurs en-têtes peuvent viser la même clé — même
     * principe de tolérance aux synonymes que {@see \App\Imports\EleveImport::COLONNES}.
     *
     * @return array<string, string>
     */
    public function colonnes(): array;

    /**
     * Clé canonique => libellé humain de la colonne Excel. Source unique
     * pour les en-têtes du modèle téléchargeable ET de l'export — les deux
     * ne peuvent donc jamais diverger.
     *
     * @return array<string, string>
     */
    public function libellesTemplate(): array;

    /** Règles de validation Laravel, indexées sur les clés canoniques. */
    public function regles(): array;

    /**
     * Clé d'unicité pour l'`updateOrCreate` — un réimport met à jour plutôt
     * que dupliquer.
     *
     * @param  array<string, mixed>  $ligne  Ligne déjà normalisée (clés canoniques).
     * @return array<string, mixed>
     */
    public function cleUnique(array $ligne, int $schoolId): array;

    /**
     * Ligne normalisée -> attributs du modèle (résolution des FK par
     * libellé incluse).
     *
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    public function transformer(array $ligne, int $schoolId): array;

    /** Requête de base pour l'export — filtrée par école, prête à `get()`. */
    public function pourExport(int|array $schoolId): Builder;

    /** Valeur à écrire dans la colonne `$cle` de l'export, pour cet enregistrement. */
    public function valeurExport(mixed $enregistrement, string $cle): mixed;
}
