<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\ProgressionItem;
use App\Services\CalendrierScolaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendrierScolaireController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $annee = $this->annee($request);
        $regles = CalendrierScolaire::where('annee_scolaire_id', $annee->id)
            ->with(['classe:id,nom', 'sousSysteme:id,nom', 'niveau:id,code,name_fr'])
            ->orderBy('date')->get()
            ->map(fn(CalendrierScolaire $r) => $this->presenter($r));

        return ApiResponse::success([
            'annee' => ['id' => $annee->id, 'libelle' => $annee->libelle, 'date_debut' => Carbon::parse((string) $annee->date_debut)->toDateString(), 'date_fin' => Carbon::parse((string) $annee->date_fin)->toDateString()],
            'regles' => $regles,
        ]);
    }

    public function store(Request $request, CalendrierScolaireService $service): JsonResponse
    {
        $annee = $this->annee($request);
        $data = $request->validate([
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'est_ouvert' => ['required', 'boolean'],
            'motif' => ['nullable', 'string', 'max:255'],
            'sous_systeme_id' => ['nullable', 'integer'],
            'niveau_id' => ['nullable', 'integer'],
            'classe_id' => ['nullable', 'integer'],
        ]);

        abort_if($data['date'] < Carbon::parse((string) $annee->date_debut)->toDateString() || $data['date'] > Carbon::parse((string) $annee->date_fin)->toDateString(), 422, "La date doit appartenir à l'année scolaire.");
        $this->verifierCible($data);

        $cles = ['annee_scolaire_id' => $annee->id, 'date' => $data['date'], 'classe_id' => $data['classe_id'] ?? null, 'niveau_id' => $data['niveau_id'] ?? null, 'sous_systeme_id' => $data['sous_systeme_id'] ?? null];
        $regle = CalendrierScolaire::updateOrCreate($cles, ['est_ouvert' => $data['est_ouvert'], 'motif' => $data['motif'] ?? null]);

        $classes = Classe::where('school_id', $annee->school_id)->get();
        $service->recalculerDates($annee, $classes);

        return ApiResponse::success($this->presenter($regle->load(['classe:id,nom', 'sousSysteme:id,nom', 'niveau:id,code,name_fr'])), 'Calendrier mis à jour et leçons réajustées.');
    }

    /** Détail d'un jour : créneaux d'emploi du temps et leçons prévues, avec leurs enseignants. */
    public function jour(Request $request, string $date, CalendrierScolaireService $service): JsonResponse
    {
        $annee = $this->annee($request);
        abort_unless(Carbon::hasFormat($date, 'Y-m-d'), 422, 'Date invalide.');
        $data = ['classe_id' => $request->integer('classe_id') ?: null, 'niveau_id' => $request->integer('niveau_id') ?: null, 'sous_systeme_id' => $request->integer('sous_systeme_id') ?: null];
        $this->verifierCible($data);

        $classes = Classe::where('school_id', $annee->school_id)
            ->when($data['classe_id'], fn($q) => $q->whereKey($data['classe_id']))
            ->when($data['niveau_id'], fn($q) => $q->where('niveau_id', $data['niveau_id']))
            ->when($data['sous_systeme_id'], fn($q) => $q->where('sous_systeme_id', $data['sous_systeme_id']))
            ->get();
        $classeIds = $classes->pluck('id');

        $jourSemaine = Carbon::parse($date)->dayOfWeekIso;
        $cours = EmploiDuTemps::whereIn('classe_id', $classeIds)->where('jour', $jourSemaine)
            ->whereNotNull('classe_matiere_id')
            ->with(['classe:id,nom', 'classeMatiere.matiere:id,nom', 'classeMatiere.enseignant:id,nom_complet'])
            ->orderBy('heure_debut')->get()
            ->map(fn(EmploiDuTemps $c) => [
                'id' => $c->id,
                'heure_debut' => substr((string) $c->heure_debut, 0, 5),
                'heure_fin' => substr((string) $c->heure_fin, 0, 5),
                'classe_id' => $c->classe_id,
                'classe' => $c->classe?->nom,
                'matiere' => $c->classeMatiere?->matiere?->nom,
                'enseignant' => $c->classeMatiere?->enseignant?->nom_complet,
                'salle' => $c->salle,
            ]);

        $lecons = ProgressionItem::lecons()->whereDate('date_prevue', $date)
            ->whereHas('classeMatiere.classe', fn($q) => $q->whereIn('id', $classeIds))
            ->with(['classeMatiere.classe:id,nom', 'classeMatiere.matiere:id,nom', 'classeMatiere.enseignant:id,nom_complet'])
            ->orderBy('ordre')->get()
            ->map(fn(ProgressionItem $l) => [
                'id' => $l->id,
                'titre' => $l->titre,
                'classe_id' => $l->classeMatiere?->classe_id,
                'classe' => $l->classeMatiere?->classe?->nom,
                'matiere' => $l->classeMatiere?->matiere?->nom,
                'enseignant' => $l->classeMatiere?->enseignant?->nom_complet,
                'date_realisee' => $l->date_realisee?->toDateString(),
            ]);

        $classesFermees = $classes->reject(fn(Classe $classe) => $service->estOuvert($classe, Carbon::parse($date), $annee->id))->pluck('nom')->values();

        return ApiResponse::success([
            'date' => $date,
            'jour' => $jourSemaine,
            'classes_fermees' => $classesFermees,
            'cours' => $cours->values(),
            'lecons' => $lecons->values(),
        ]);
    }

    /** Recalcule les dates prévues de toutes les classes de l'école à partir de l'emploi du temps et du calendrier. */
    public function recalculer(Request $request, CalendrierScolaireService $service): JsonResponse
    {
        $annee = $this->annee($request);
        $modifiees = $service->recalculerDates($annee, Classe::where('school_id', $annee->school_id)->get());

        return ApiResponse::success(['modifiees' => $modifiees], $modifiees > 0 ? "{$modifiees} leçon(s) replanifiée(s) selon l'emploi du temps." : 'Les dates prévues sont déjà à jour.');
    }

    public function destroy(Request $request, int $id, CalendrierScolaireService $service): JsonResponse
    {
        $annee = $this->annee($request);
        $regle = CalendrierScolaire::where('annee_scolaire_id', $annee->id)->findOrFail($id);
        $regle->delete();
        $service->recalculerDates($annee, Classe::where('school_id', $annee->school_id)->get());

        return ApiResponse::success(null, 'Jour rouvert et leçons réajustées.');
    }

    private function annee(Request $request): AnneeScolaire
    {
        $id = $request->integer('annee_id');

        return AnneeScolaire::where('school_id', app('tenant.school_id'))
            ->when($id, fn($q) => $q->whereKey($id))
            ->when(! $id, fn($q) => $q->where('is_active', true))
            ->firstOrFail();
    }

    private function verifierCible(array $data): void
    {
        $compte = collect(['classe_id', 'niveau_id', 'sous_systeme_id'])->filter(fn($cle) => ! empty($data[$cle]))->count();
        abort_if($compte > 1, 422, 'Choisissez une seule portée : classe, niveau ou sous-système.');
    }

    private function presenter(CalendrierScolaire $r): array
    {
        return [
            'id' => $r->id,
            'date' => Carbon::parse((string) $r->date)->toDateString(),
            'est_ouvert' => $r->est_ouvert,
            'motif' => $r->motif,
            'classe_id' => $r->classe_id,
            'classe' => $r->classe?->nom,
            'niveau_id' => $r->niveau_id,
            'niveau' => $r->niveau?->name_fr ?? $r->niveau?->code,
            'sous_systeme_id' => $r->sous_systeme_id,
            'sous_systeme' => $r->sousSysteme?->nom,
        ];
    }
}
