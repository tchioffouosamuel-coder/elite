<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Recueille, pendant une requête journalisée, chaque création, modification
 * et suppression Eloquent — avec les valeurs avant/après — pour les joindre
 * à la ligne d'audit de cette requête.
 *
 * Une pile plutôt qu'un simple tableau : le rejeu d'un lot de
 * synchronisation (`SyncController::rejouer()`) traite chaque opération
 * comme une sous-requête complète, journalisée séparément, à l'intérieur de
 * la requête `/sync` englobante. Chaque écriture doit revenir à la
 * sous-requête qui l'a produite, pas au lot.
 *
 * Limite connue : les écritures en masse du query builder
 * (`Model::where(...)->update()`) ne déclenchent aucun événement Eloquent et
 * n'apparaissent donc pas ici — la requête elle-même reste journalisée.
 */
class CollecteurChangements
{
    /** @var list<list<array<string, mixed>>> */
    private array $pile = [];

    public function ouvrir(): void
    {
        $this->pile[] = [];
    }

    /** @return list<array<string, mixed>> */
    public function fermer(): array
    {
        return array_pop($this->pile) ?? [];
    }

    public function actif(): bool
    {
        return $this->pile !== [];
    }

    public function enregistrer(string $evenement, Model $modele): void
    {
        if (! $this->actif() || ! $this->doitSuivre($modele)) {
            return;
        }

        $niveau = array_key_last($this->pile);

        if (count($this->pile[$niveau]) >= (int) config('audit.max_changements', 200)) {
            return;
        }

        $entree = [
            'operation' => $evenement,
            'modele' => class_basename($modele),
            'id' => $modele->getKey(),
        ];

        $masques = $modele->getHidden();

        if ($evenement === 'updated') {
            $avant = [];
            $apres = [];

            foreach ($modele->getChanges() as $champ => $valeur) {
                if ($champ === $modele->getUpdatedAtColumn()) {
                    continue;
                }
                $avant[$champ] = $this->valeur($champ, $modele->getRawOriginal($champ), $masques);
                $apres[$champ] = $this->valeur($champ, $valeur, $masques);
            }

            if ($apres === []) {
                return;
            }

            $entree['avant'] = $avant;
            $entree['apres'] = $apres;
        } elseif ($evenement === 'created') {
            $entree['apres'] = $this->attributs($modele, $masques);
        } else {
            $entree['avant'] = $this->attributs($modele, $masques);
        }

        $this->pile[$niveau][] = $entree;
    }

    private function doitSuivre(Model $modele): bool
    {
        $classe = $modele::class;

        return str_starts_with($classe, 'App\\Models\\')
            && ! in_array($classe, config('audit.modeles_exclus', []), true);
    }

    /**
     * @param  list<string>  $masques
     * @return array<string, mixed>
     */
    private function attributs(Model $modele, array $masques): array
    {
        $resultat = [];

        foreach ($modele->getAttributes() as $champ => $valeur) {
            $resultat[$champ] = $this->valeur($champ, $valeur, $masques);
        }

        return $resultat;
    }

    /** @param  list<string>  $masques */
    private function valeur(string $champ, mixed $valeur, array $masques): mixed
    {
        if (in_array($champ, $masques, true) || Assainisseur::estSensible($champ)) {
            return $valeur === null ? null : '***';
        }

        return Assainisseur::scalaire($valeur);
    }
}
