<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Presence;
use App\Models\Seance;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeanceController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $service) {}

    public function index(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $seances = Seance::where('classe_id', $classe->id)
            ->when($request->date('date_debut'), fn ($q, $d) => $q->whereDate('date_seance', '>=', $d))
            ->when($request->date('date_fin'), fn ($q, $d) => $q->whereDate('date_seance', '<=', $d))
            ->when($request->integer('trimestre_id'), fn ($q, $id) => $q->where('trimestre_id', $id))
            ->with(['classeMatiere.matiere', 'classeMatiere.enseignant'])
            ->withCount(['presences as absents_count' => fn ($q) => $q->whereIn('statut', ['absent', 'renvoye'])])
            ->orderByDesc('date_seance')->orderBy('heure_debut')
            ->get();

        return ApiResponse::success($seances->map($this->presenter(...)));
    }

    public function store(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $data = $request->validate([
            'classe_matiere_id' => ['required', 'integer'],
            'trimestre_id' => ['nullable', 'integer'],
            'date_seance' => ['required', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:50'],
            'contenu' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless(
            ClasseMatiere::where('classe_id', $classe->id)->whereKey($data['classe_matiere_id'])->exists(),
            422,
            "Cette matière n'est pas affectée à la classe."
        );

        $seance = Seance::create([...$data, 'classe_id' => $classe->id, 'school_id' => $classe->school_id]);

        return ApiResponse::created(
            $this->presenter($seance->load('classeMatiere.matiere', 'classeMatiere.enseignant')),
            'Séance créée.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $seance = $this->seance($id);

        $data = $request->validate([
            'contenu' => ['nullable', 'string', 'max:2000'],
            'salle' => ['nullable', 'string', 'max:50'],
            'statut' => ['nullable', 'in:prevue,effectuee,annulee'],
        ]);

        $seance->update($data);

        return ApiResponse::success(
            $this->presenter($seance->load('classeMatiere.matiere', 'classeMatiere.enseignant')),
            'Séance mise à jour.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->seance($id)->delete();

        return ApiResponse::success(message: 'Séance supprimée.');
    }

    /** Feuille d'appel de la séance. */
    public function appel(int $id): JsonResponse
    {
        $seance = $this->seance($id);

        return ApiResponse::success([
            'seance' => $this->presenter($seance->load('classeMatiere.matiere', 'classeMatiere.enseignant')),
            'lignes' => $this->service->feuilleAppel($seance)->map(fn ($ligne) => [
                'eleve_id' => $ligne['eleve']->id,
                'nom_complet' => $ligne['eleve']->nom_complet,
                'matricule' => $ligne['eleve']->matricule,
                'statut' => $ligne['statut'],
                'motif' => $ligne['motif'],
                'justifie' => $ligne['justifie'],
                'remarque' => $ligne['remarque'],
                'pointe' => $ligne['pointe'],
            ]),
        ]);
    }

    public function enregistrerAppel(Request $request, int $id): JsonResponse
    {
        $seance = $this->seance($id);

        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.eleve_id' => ['required', 'integer'],
            'lignes.*.statut' => ['required', 'in:present,absent,retard,renvoye'],
            // Une absence sans motif ne se traite pas : le surveillant général
            // ne saurait pas s'il faut relancer la famille ou classer l'affaire.
            'lignes.*.motif' => ['nullable', 'required_if:lignes.*.statut,absent', Rule::in(Presence::MOTIFS)],
            'lignes.*.justifie' => ['nullable', 'boolean'],
            'lignes.*.remarque' => ['nullable', 'string', 'max:255'],
        ]);

        $total = $this->service->enregistrerAppel($seance, $data['lignes']);

        return ApiResponse::success(['enregistres' => $total], "Appel enregistré ({$total} élève(s)).");
    }

    private function classe(int $id): Classe
    {
        return Classe::forSchool(app('tenant.school_id'))->findOrFail($id);
    }

    private function seance(int $id): Seance
    {
        return Seance::forSchool(app('tenant.school_id'))->findOrFail($id);
    }

    private function presenter(Seance $seance): array
    {
        return [
            'id' => $seance->id,
            'classe_id' => $seance->classe_id,
            'classe_matiere_id' => $seance->classe_matiere_id,
            'matiere' => $seance->classeMatiere?->matiere?->nom,
            'enseignant' => $seance->classeMatiere?->enseignant?->nom_complet,
            'date_seance' => $seance->date_seance?->toDateString(),
            'heure_debut' => substr((string) $seance->heure_debut, 0, 5),
            'heure_fin' => substr((string) $seance->heure_fin, 0, 5),
            'salle' => $seance->salle,
            'contenu' => $seance->contenu,
            'statut' => $seance->statut,
            'absents' => $seance->absents_count,
        ];
    }
}
