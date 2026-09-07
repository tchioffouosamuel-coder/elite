<?php

namespace App\Services;

use App\Models\BibliothequeDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;

class BibliothequeService extends BaseService
{
    /** @param int|array<int> $schoolId */
    public function lister(int|array $schoolId): Collection
    {
        return BibliothequeDocument::visiblePour($schoolId)
            ->with(['ecoles', 'uploadePar'])
            ->latest()
            ->get();
    }

    /** @param array{titre: string, description?: ?string, school_ids: array<int>} $donnees */
    public function uploader(array $donnees, UploadedFile $fichier, ?int $uploadePar): BibliothequeDocument
    {
        return $this->transaction(function () use ($donnees, $fichier, $uploadePar) {
            $document = BibliothequeDocument::create([
                'titre' => $donnees['titre'],
                'description' => $donnees['description'] ?? null,
                'fichier_path' => $fichier->store('bibliotheque', 'public'),
                'fichier_nom_original' => $fichier->getClientOriginalName(),
                'taille' => $fichier->getSize(),
                'type_mime' => $fichier->getClientMimeType(),
                'uploaded_par' => $uploadePar,
            ]);

            $document->ecoles()->sync($donnees['school_ids']);

            return $document->fresh(['ecoles', 'uploadePar']);
        });
    }

    /**
     * Import massif : un document par fichier, même ciblage d'écoles et même
     * description pour tous — le titre de chacun se déduit de son nom de
     * fichier (sans l'extension), pour ne pas demander une saisie manuelle
     * répétée à chaque fichier d'un lot.
     *
     * @param  array<int, UploadedFile>  $fichiers
     * @param  array<int>  $schoolIds
     * @return SupportCollection<int, BibliothequeDocument>
     */
    public function importer(array $fichiers, array $schoolIds, ?string $description, ?int $uploadePar): SupportCollection
    {
        return $this->transaction(function () use ($fichiers, $schoolIds, $description, $uploadePar) {
            return collect($fichiers)->map(fn (UploadedFile $fichier) => $this->uploader([
                'titre' => pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $description,
                'school_ids' => $schoolIds,
            ], $fichier, $uploadePar))->values();
        });
    }

    public function supprimer(BibliothequeDocument $document): void
    {
        Storage::disk('public')->delete($document->fichier_path);
        $document->delete();
    }
}
