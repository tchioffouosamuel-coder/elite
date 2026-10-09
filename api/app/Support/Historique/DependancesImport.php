<?php

namespace App\Support\Historique;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DependancesImport
{
    private ?array $etrangeres = null;

    public function etrangeres(): array
    {
        if ($this->etrangeres === null) {
            $this->etrangeres = [];
            foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
                $this->etrangeres[$table['name']] = Schema::getForeignKeys($table['name']);
            }
        }

        return $this->etrangeres;
    }

    public function empreintes(array $ligne): array
    {
        $tableParent = (new $ligne['modele'])->getTable();
        $empreintes = [];
        foreach ($this->etrangeres() as $table => $cles) {
            foreach ($cles as $cle) {
                if ($cle['foreign_table'] !== $tableParent || $cle['foreign_columns'] !== ['id']) {
                    continue;
                }
                $empreintes[$table.':'.implode(',', $cle['columns'])] = $this->empreinte(DB::table($table)->where($cle['columns'][0], $ligne['id']));
            }
        }

        if ($ligne['modele'] === User::class) {
            $morph = (new User)->getMorphClass();
            foreach (['model_has_roles' => ['model_id', 'model_type'], 'model_has_permissions' => ['model_id', 'model_type'],
                'personal_access_tokens' => ['tokenable_id', 'tokenable_type'], 'notifications' => ['notifiable_id', 'notifiable_type'],
                'sessions' => ['user_id', null]] as $table => [$champ, $type]) {
                if (isset($this->etrangeres()[$table])) {
                    $requete = DB::table($table)->where($champ, $ligne['id']);
                    if ($type) {
                        $requete->where($type, $morph);
                    }
                    $empreintes[$table.':compte'] = $this->empreinte($requete);
                }
            }
        }

        ksort($empreintes);

        return $empreintes;
    }

    private function empreinte(Builder $requete): string
    {
        $lignes = $requete->lockForUpdate()->get()->map(function ($valeurs) {
            $valeurs = array_diff_key((array) $valeurs, array_flip(['created_at', 'updated_at']));
            ksort($valeurs);

            return json_encode($valeurs, JSON_THROW_ON_ERROR);
        })->sort()->values()->all();

        return hash('sha256', json_encode($lignes, JSON_THROW_ON_ERROR));
    }

    public function capturer(array $lignes): array
    {
        foreach ($lignes as &$ligne) {
            $ligne['dependances'] = $this->empreintes($ligne);
        }

        return $lignes;
    }

    public function ordonner(array $lignes): array
    {
        $ordre = [];
        $restantes = array_keys($lignes);
        $parents = [];
        foreach ($lignes as $index => $ligne) {
            $parents[(new $ligne['modele'])->getTable()][$ligne['id']] = $index;
        }
        while ($restantes !== []) {
            $avance = false;
            foreach ($restantes as $position => $index) {
                $ligne = $lignes[$index];
                $valeurs = $ligne['apres'] ?? $ligne['avant'];
                $depend = false;
                foreach ($this->etrangeres()[(new $ligne['modele'])->getTable()] ?? [] as $cle) {
                    if ($cle['foreign_columns'] !== ['id']) {
                        continue;
                    }
                    $parent = $parents[$cle['foreign_table']][$valeurs[$cle['columns'][0]] ?? ''] ?? null;
                    if ($parent !== null && $parent !== $index && in_array($parent, $restantes, true)) {
                        $depend = true;
                        break;
                    }
                }
                if (! $depend) {
                    $ordre[] = $index;
                    unset($restantes[$position]);
                    $avance = true;
                }
            }
            abort_unless($avance, 409, 'Cet import contient des relations cycliques non annulables.');
        }

        return $ordre;
    }

    public function remapper(array $lignes, string $table, int $ancien, int $nouveau): array
    {
        foreach ($lignes as &$ligne) {
            $tableLigne = (new $ligne['modele'])->getTable();
            if ($tableLigne === $table && (int) $ligne['id'] === $ancien) {
                $ligne['id'] = $nouveau;
                if (array_key_exists('id', $ligne['cle'])) {
                    $ligne['cle']['id'] = $nouveau;
                }
            }
            foreach ($this->etrangeres()[$tableLigne] ?? [] as $cle) {
                if ($cle['foreign_table'] !== $table || $cle['foreign_columns'] !== ['id']) {
                    continue;
                }
                $champ = $cle['columns'][0];
                foreach (['avant', 'apres', 'cle'] as $version) {
                    if (isset($ligne[$version][$champ]) && (int) $ligne[$version][$champ] === $ancien) {
                        $ligne[$version][$champ] = $nouveau;
                    }
                }
            }
        }

        return $lignes;
    }
}
