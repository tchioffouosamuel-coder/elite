<?php

namespace App\Support\ImportExport\Specs;

use App\Models\AnneeScolaire;
use App\Models\FraisAnnexe;
use App\Support\ImportExport\SpecificationModele;
use Illuminate\Database\Eloquent\Builder;

/** Frais annexes (cantine, transport, examens…) de l'année scolaire active de l'école. */
class FraisAnnexeSpec implements SpecificationModele
{
    public function modele(): string
    {
        return FraisAnnexe::class;
    }

    public function colonnes(): array
    {
        return [
            'libelle' => 'libelle', 'nom' => 'libelle',
            'montant' => 'montant',
            'obligatoire' => 'obligatoire',
        ];
    }

    public function libellesTemplate(): array
    {
        return ['libelle' => 'Libellé', 'montant' => 'Montant (FCFA)', 'obligatoire' => 'Obligatoire (Oui/Non)'];
    }

    public function regles(): array
    {
        return ['libelle' => ['required', 'string'], 'montant' => ['required', 'numeric', 'min:0']];
    }

    public function cleUnique(array $ligne, int $schoolId): array
    {
        return [
            'school_id' => $schoolId,
            'annee_scolaire_id' => AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->value('id'),
            'libelle' => $ligne['libelle'],
        ];
    }

    public function transformer(array $ligne, int $schoolId): array
    {
        return array_filter([
            'montant' => isset($ligne['montant']) ? (int) round((float) $ligne['montant']) : null,
            'obligatoire' => isset($ligne['obligatoire']) ? $this->booleen($ligne['obligatoire']) : null,
        ], fn ($v) => $v !== null);
    }

    public function pourExport(int|array $schoolId): Builder
    {
        return FraisAnnexe::when(
            is_array($schoolId),
            fn (Builder $q) => $q->whereIn('school_id', $schoolId),
            fn (Builder $q) => $q->where('school_id', $schoolId),
        )->orderBy('libelle');
    }

    public function valeurExport(mixed $enregistrement, string $cle): mixed
    {
        return $cle === 'obligatoire' ? ($enregistrement->obligatoire ? 'Oui' : 'Non') : $enregistrement->{$cle};
    }

    private function booleen(mixed $valeur): bool
    {
        return in_array(mb_strtoupper(trim((string) $valeur)), ['OUI', 'O', 'YES', 'Y', '1', 'TRUE', 'VRAI'], true);
    }
}
