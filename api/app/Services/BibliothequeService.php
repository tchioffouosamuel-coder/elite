<?php

namespace App\Services;

use App\Models\BibliothequeDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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

    public function supprimer(BibliothequeDocument $document): void
    {
        Storage::disk('public')->delete($document->fichier_path);
        $document->delete();
    }
}
