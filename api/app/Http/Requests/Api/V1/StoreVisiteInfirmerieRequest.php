<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Eleve;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Une visite se rattache à l'école de l'élève, pas au périmètre ambiant.
 *
 * Le trait ScopedRules borne les existences à `app('tenant.school_id')` :
 * l'école demandée par l'en-tête, ou à défaut celle du compte. En mode agrégé
 * — le super admin qui voit les trois écoles du complexe sans en choisir une —
 * cette valeur de repli est le rattachement du compte, qui n'a rien à voir
 * avec l'élève soigné. L'infirmière du complexe rattachée au collège se
 * voyait ainsi refuser tout élève de la maternelle, avec pour seul message
 * « Les données envoyées ne sont pas valides ».
 *
 * L'élève est donc cherché dans tout le complexe (comme le fait déjà le
 * contrôleur), et son école sert de référence aux malaises et à l'inventaire :
 * le cloisonnement tient toujours, il est simplement ancré au bon endroit.
 */
class StoreVisiteInfirmerieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ecoleEleve = $this->ecoleDeLEleve();

        return [
            'eleve_id' => ['required', Rule::exists('eleves', 'id')->whereIn('school_id', Tenant::schoolIds())],
            'date_visite' => ['required', 'date'],
            'raison' => ['required', 'string', 'max:2000'],
            'malaise_ids' => ['nullable', 'array'],
            'malaise_ids.*' => [$this->existeDansEcoleEleve('malaises_referentiel', $ecoleEleve)],
            'soins_prodiges' => ['required', 'string', 'max:2000'],
            'type_traitement' => ['required', 'in:interne,externe,mixte'],
            'structure_externe' => ['required_if:type_traitement,externe,mixte', 'nullable', 'string', 'max:255'],
            'cout_soins' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'materiels' => ['nullable', 'array'],
            'materiels.*.inventaire_article_id' => [
                'required',
                // Le stock partagé par les trois écoles est prélevable partout.
                $this->existeDansEcoleEleve('inventaire_articles', $ecoleEleve, partageable: true),
            ],
            'materiels.*.quantite' => ['required', 'integer', 'min:1'],
            'autre_materiel' => ['nullable', 'string', 'max:255'],
            'cout_autre_materiel' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'observations' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * École de l'élève soumis, cherchée dans tout le complexe.
     *
     * Null quand l'élève est absent, inconnu ou hors complexe : sa propre
     * règle le signalera, et les règles dépendantes se rabattent alors sur le
     * complexe entier plutôt que d'ajouter une seconde erreur qui masquerait
     * la vraie.
     */
    private function ecoleDeLEleve(): ?int
    {
        $eleveId = (int) $this->input('eleve_id');

        if ($eleveId <= 0) {
            return null;
        }

        return Eleve::whereKey($eleveId)
            ->whereIn('school_id', Tenant::schoolIds())
            ->value('school_id');
    }

    /**
     * @param  bool  $partageable  accepte aussi les lignes sans école, communes
     *                             à tout le complexe (cf. inventaire partagé)
     */
    private function existeDansEcoleEleve(string $table, ?int $ecoleEleve, bool $partageable = false): Exists
    {
        $ecoles = $ecoleEleve !== null ? [$ecoleEleve] : Tenant::schoolIds();

        return Rule::exists($table, 'id')->where(function ($query) use ($ecoles, $partageable) {
            $query->whereIn('school_id', $ecoles);

            if ($partageable) {
                $query->orWhereNull('school_id');
            }
        });
    }
}
