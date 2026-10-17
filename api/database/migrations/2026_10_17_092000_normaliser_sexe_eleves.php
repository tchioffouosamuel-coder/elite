<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * BUG3 de la punch-list Elites-tech : la colonne `eleves.sexe` est un
 * `enum('M','F')` nullable depuis sa création (cf.
 * `2026_08_09_203537_create_eleves_table`) et la validation des requêtes
 * (`StoreEleveRequest`/`UpdateEleveRequest`) exige déjà `in:M,F` — aucune
 * saisie passant par l'API ne peut donc écrire autre chose que 'M', 'F' ou
 * NULL.
 *
 * Reste le risque d'une valeur posée hors de ce chemin — import historique
 * (ancien `_smapp`), synchronisation desktop, correction manuelle en base, ou
 * une ligne écrite quand `sql_mode` ne forçait pas STRICT_TRANS_TABLES (MySQL
 * tronque alors silencieusement une valeur hors enum en chaîne vide '',
 * plutôt que de rejeter l'insertion). Cette migration ne change pas le
 * schéma (déjà correct) : elle ne fait que nettoyer la donnée, en mappant les
 * variantes françaises/anglaises courantes et en mettant à NULL ce qui ne
 * peut pas être deviné avec confiance — jamais une valeur au hasard.
 */
return new class extends Migration
{
    /** Variantes reconnues, comparées une fois minuscules/sans accents/sans espaces. */
    private const VERS_M = ['m', 'masculin', 'male', 'man', 'boy', 'garcon', 'garçon', 'h', 'homme'];

    private const VERS_F = ['f', 'feminin', 'féminin', 'female', 'woman', 'girl', 'fille', 'femme'];

    public function up(): void
    {
        if (! Schema::hasTable('eleves') || ! Schema::hasColumn('eleves', 'sexe')) {
            return;
        }

        $lignes = DB::table('eleves')
            ->whereNotNull('sexe')
            ->whereNotIn('sexe', ['M', 'F'])
            ->get(['id', 'sexe']);

        if ($lignes->isEmpty()) {
            return;
        }

        $corrigees = 0;
        $nullifiees = 0;

        foreach ($lignes as $ligne) {
            $normalise = self::normaliser((string) $ligne->sexe);

            DB::table('eleves')->where('id', $ligne->id)->update(['sexe' => $normalise]);

            $normalise === null ? $nullifiees++ : $corrigees++;
        }

        $message = "Normalisation de eleves.sexe : {$corrigees} ligne(s) corrigée(s), {$nullifiees} ligne(s) mise(s) à NULL (valeur non reconnue) sur ".$lignes->count().' anomalie(s) trouvée(s).';

        // Visible dans la sortie de `php artisan migrate` et conservé dans les
        // logs applicatifs — un correctif de données silencieux ne permettrait
        // pas de signaler le nombre de dossiers à vérifier à l'école.
        echo "    {$message}\n";
        Log::info($message);
    }

    public function down(): void
    {
        // Purement correctif : aucune valeur fiable à restaurer (une donnée
        // déjà invalide ne se « dé-nettoie » pas), donc rien à défaire.
    }

    private static function normaliser(string $valeur): ?string
    {
        $cle = preg_replace('/[^a-z]+/', '', mb_strtolower(Str::ascii(trim($valeur)))) ?? '';

        if ($cle === '') {
            return null;
        }

        if (in_array($cle, self::VERS_M, true)) {
            return 'M';
        }

        if (in_array($cle, self::VERS_F, true)) {
            return 'F';
        }

        // Valeur non reconnue : on ne devine pas, direction NULL plutôt
        // qu'un sexe faux dans le dossier de l'élève.
        return null;
    }
};
