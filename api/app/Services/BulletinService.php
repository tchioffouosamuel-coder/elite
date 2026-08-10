<?php

namespace App\Services;

use App\Models\AbsenceTrimestre;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Sanction;
use App\Models\Trimestre;

class BulletinService extends BaseService
{
    public function __construct(private readonly MoyenneService $moyennes)
    {
    }

    public function donnees(Eleve $eleve, Trimestre $trimestre): array
    {
        $classe = $eleve->classe;
        $sequences = $trimestre->sequences;

        $affectations = $classe->classeMatieres()->where('statut', 'actif')
            ->with(['matiere', 'enseignant'])->orderBy('groupe')->orderBy('id')->get();

        $lignes = $affectations->map(function (ClasseMatiere $cm) use ($eleve, $trimestre, $sequences) {
            $notesParSequence = Note::where('eleve_id', $eleve->id)
                ->where('classe_matiere_id', $cm->id)
                ->whereIn('sequence_id', $sequences->pluck('id'))
                ->get()->keyBy('sequence_id');

            $moyenne = $this->moyennes->moyenneMatiereEleve($eleve, $cm, $trimestre);
            $classement = $this->moyennes->classementMatiere($cm, $trimestre);
            $rangEleve = $classement->firstWhere('eleve_id', $eleve->id);
            $valeursClasse = $classement->pluck('moyenne')->filter(fn ($v) => $v !== null);

            return [
                'groupe' => $cm->groupe,
                'matiere' => $cm->matiere->nom,
                'enseignant' => $cm->enseignant?->nomComplet() ?? '—',
                'notes' => $sequences->map(fn ($s) => $notesParSequence->get($s->id)?->valeur !== null
                    ? (float) $notesParSequence->get($s->id)->valeur
                    : null)->values(),
                'moyenne' => $moyenne,
                'coefficient' => (float) $cm->coefficient,
                'total' => $moyenne !== null ? round($moyenne * (float) $cm->coefficient, 2) : null,
                'cote' => $this->moyennes->lettreCote($moyenne),
                'rang' => $rangEleve['rang'] ?? null,
                'min' => $valeursClasse->isNotEmpty() ? (float) $valeursClasse->min() : null,
                'max' => $valeursClasse->isNotEmpty() ? (float) $valeursClasse->max() : null,
            ];
        })->groupBy('groupe');

        $general = $this->moyennes->moyenneGeneraleEleve($eleve, $trimestre);
        $classementGeneral = $this->moyennes->classementGeneral($classe, $trimestre);
        $rangGeneral = $classementGeneral->first(fn ($row) => $row['eleve']->id === $eleve->id);

        $absence = AbsenceTrimestre::where('eleve_id', $eleve->id)->where('trimestre_id', $trimestre->id)->first();
        $sanctions = Sanction::where('eleve_id', $eleve->id)->where('trimestre_id', $trimestre->id)->get();
        $heuresNonJustifiees = (float) ($absence?->heures_non_justifiees ?? 0);

        $effectif = $classe->eleves()->where('statut', 'actif');
        $totalEffectif = (clone $effectif)->count();
        $garcons = (clone $effectif)->where('sexe', 'M')->count();
        $filles = (clone $effectif)->where('sexe', 'F')->count();

        return [
            'eleve' => $eleve,
            'classe' => $classe,
            'trimestre' => $trimestre,
            'anneeScolaire' => $trimestre->anneeScolaire,
            'effectif' => ['total' => $totalEffectif, 'garcons' => $garcons, 'filles' => $filles],
            'lignes' => $lignes,
            'moyenne_generale' => $general['moyenne'],
            'total_points' => $general['total_points'],
            'total_coef' => $general['total_coef'],
            'rang_general' => $rangGeneral['rang'] ?? null,
            'cote_generale' => $this->moyennes->lettreCote($general['moyenne']),
            'appreciation' => $this->moyennes->appreciation($general['moyenne']),
            'mention_travail' => $this->moyennes->mentionTravail($eleve->school_id, $general['moyenne']),
            'mention_conduite' => $this->moyennes->mentionConduite($eleve->school_id, $heuresNonJustifiees),
            'heures_justifiees' => (float) ($absence?->heures_justifiees ?? 0),
            'heures_non_justifiees' => $heuresNonJustifiees,
            'sanctions' => $sanctions,
        ];
    }
}
