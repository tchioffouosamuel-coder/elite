<?php

namespace App\Support\ImportExport;

use Illuminate\Support\Str;

/**
 * Résolution d'une clé étrangère par libellé (ex. « Nom du sous-système » ->
 * `sous_system_id`) pour les {@see SpecificationModele} — même normalisation
 * (insensible à la casse, aux accents, aux espaces) que
 * {@see \App\Imports\EleveImport::cle()}.
 */
class Resolveur
{
    /** @var array<string, array<string, int>> classe => (clé normalisée => id), mémorisé le temps de la requête */
    private static array $cache = [];

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modele
     * @param  list<string>  $colonnes  Colonnes candidates, dans l'ordre de préférence.
     */
    public static function id(string $modele, int|array $schoolId, ?string $libelle, array $colonnes = ['nom']): ?int
    {
        if ($libelle === null || trim($libelle) === '') {
            return null;
        }

        $table = self::table($modele, $schoolId, $colonnes);

        return $table[self::cle($libelle)] ?? null;
    }

    /** @return array<string, int> */
    private static function table(string $modele, int|array $schoolId, array $colonnes): array
    {
        $cleCache = $modele.'|'.(is_array($schoolId) ? implode(',', $schoolId) : $schoolId).'|'.implode(',', $colonnes);

        if (isset(self::$cache[$cleCache])) {
            return self::$cache[$cleCache];
        }

        $query = $modele::query();

        if (is_array($schoolId)) {
            $query->whereIn('school_id', $schoolId);
        } else {
            $query->where('school_id', $schoolId);
        }

        $table = [];

        foreach ($query->get(['id', ...$colonnes]) as $ligne) {
            foreach ($colonnes as $colonne) {
                $cle = self::cle($ligne->{$colonne});

                if ($cle !== '' && ! isset($table[$cle])) {
                    $table[$cle] = $ligne->id;
                }
            }
        }

        return self::$cache[$cleCache] = $table;
    }

    public static function cle(?string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii((string) $libelle))) ?? '';
    }
}
