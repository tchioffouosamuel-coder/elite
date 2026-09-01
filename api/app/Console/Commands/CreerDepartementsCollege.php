<?php

namespace App\Console\Commands;

use App\Models\Departement;
use App\Models\School;
use Illuminate\Console\Command;

/**
 * Crée les 8 fiches Departement du collège technique Elites-tech, une par
 * spécialité de l'organigramme 2026-2027. Chaque fiche reste sans
 * `head_personnel_id` tant qu'aucun coordinateur nommé n'a été désigné —
 * cf. Attributions::CHEF_DEPARTEMENT, qui se règle ensuite depuis l'écran
 * des responsables de département, une fois le personnel identifié.
 *
 * Idempotente : la contrainte unique (school_id, nom) fait qu'un second
 * passage ne duplique rien.
 */
class CreerDepartementsCollege extends Command
{
    /**
     * Libellés tels qu'affichés sur l'organigramme du collège Elites-tech.
     */
    private const DEPARTEMENTS = [
        'Électrique',
        'Auto Mécanique',
        'Bâtiment',
        'Habillement',
        'Comptabilité',
        'Administration et Communication',
        'Économie Familiale',
        'Marketing',
    ];

    protected $signature = 'departements:seed-college {--school= : ID ou code de l\'établissement secondaire visé, si l\'auto-détection est ambiguë}';

    protected $description = "Crée les 8 fiches Departement du collège technique (Électrique, Auto Mécanique, Bâtiment, Habillement, Comptabilité, Admin & Comm, Économie Familiale, Marketing).";

    public function handle(): int
    {
        $school = $this->resoudreEcole();

        if (! $school) {
            return self::FAILURE;
        }

        $crees = 0;
        $existants = 0;

        foreach (self::DEPARTEMENTS as $nom) {
            $departement = Departement::firstOrCreate([
                'school_id' => $school->id,
                'nom' => $nom,
            ]);

            if ($departement->wasRecentlyCreated) {
                $this->line("Créé : {$nom}");
                $crees++;
            } else {
                $this->line("Déjà présent : {$nom}");
                $existants++;
            }
        }

        $this->info("{$crees} département(s) créé(s), {$existants} déjà en place, sur « {$school->name} ».");
        $this->comment("Aucun chef de département n'est assigné : renseignez head_personnel_id (attribution chef_departement) depuis l'écran des responsables une fois le personnel identifié.");

        return self::SUCCESS;
    }

    private function resoudreEcole(): ?School
    {
        if ($identifiant = $this->option('school')) {
            $school = is_numeric($identifiant)
                ? School::find((int) $identifiant)
                : School::where('code', $identifiant)->first();

            if (! $school) {
                $this->error("Aucun établissement ne correspond à « {$identifiant} ».");

                return null;
            }

            if (! $school->estSecondaire()) {
                $this->error("« {$school->name} » n'est pas un établissement secondaire (type={$school->type}).");

                return null;
            }

            return $school;
        }

        $ecoles = School::where('type', 'secondaire')->get();

        if ($ecoles->isEmpty()) {
            $this->error("Aucun établissement de type secondaire trouvé. Créez-le d'abord, ou passez --school=<id|code>.");

            return null;
        }

        if ($ecoles->count() > 1) {
            $this->error('Plusieurs établissements secondaires trouvés, précisez avec --school=<id|code> :');
            $ecoles->each(fn (School $s) => $this->line("  #{$s->id}  {$s->code}  {$s->name}"));

            return null;
        }

        return $ecoles->first();
    }
}
