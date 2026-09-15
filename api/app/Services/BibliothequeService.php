<?php

namespace App\Services;

use App\Models\BibliothequeDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;

class BibliothequeService extends BaseService
{
    /**
     * `$perPage` null renvoie le catalogue complet — c'est ce qu'utilisent
     * les espaces personnel et parent, qui affichent tout sans pagination.
     * L'écran d'administration, lui, en accumule chaque dépôt d'année en
     * année et demande une page à la fois.
     *
     * @param  int|array<int>  $schoolId
     * @param  array{search?: ?string}  $filtres
     */
    public function lister(int|array $schoolId, array $filtres = [], ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $detail = BibliothequeDocument::visiblePour($schoolId)
            ->with(['ecoles', 'classes', 'uploadePar'])
            ->when($filtres['search'] ?? null, fn ($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('titre', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhereHas('ecoles', fn ($e) => $e->where('name', 'like', "%{$s}%"));
            }))
            ->latest();

        return $perPage !== null ? $detail->paginate($perPage) : $detail->get();
    }

    /**
     * Documents visibles par un agent : ceux de son école, non réservés à un
     * autre public, et soit ouverts à toute l'école, soit restreints à l'une
     * des classes où il intervient (titulariat ou affectation matière).
     *
     * @param  array<int>  $classeIds
     * @return Collection<int, BibliothequeDocument>
     */
    public function listerPourPersonnel(int $schoolId, array $classeIds): Collection
    {
        return BibliothequeDocument::visiblePour($schoolId)
            ->ciblant('personnel')
            ->pourClasses($classeIds)
            ->latest()
            ->get();
    }

    /**
     * Documents visibles par un parent : ceux des écoles de ses enfants, non
     * réservés à un autre public, et soit ouverts à toute l'école, soit
     * restreints à l'une des classes où est inscrit l'un de ses enfants.
     *
     * @param  int|array<int>  $schoolId
     * @param  array<int>  $classeIds
     * @return Collection<int, BibliothequeDocument>
     */
    public function listerPourParent(int|array $schoolId, array $classeIds): Collection
    {
        return BibliothequeDocument::visiblePour($schoolId)
            ->ciblant('parents')
            ->pourClasses($classeIds)
            ->latest()
            ->get();
    }

    /** @param array{titre: string, description?: ?string, school_ids: array<int>, classe_ids?: array<int>, cibles?: ?array<string>} $donnees */
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
                'cibles' => empty($donnees['cibles']) ? null : $donnees['cibles'],
                'uploaded_par' => $uploadePar,
            ]);

            $document->ecoles()->sync($donnees['school_ids']);
            $document->classes()->sync($donnees['classe_ids'] ?? []);

            return $document->fresh(['ecoles', 'classes', 'uploadePar']);
        });
    }

    /**
     * Import massif : un document par fichier, même ciblage (écoles, classes,
     * destinataires) et même description pour tous — le titre de chacun se
     * déduit de son nom de fichier (sans l'extension), pour ne pas demander
     * une saisie manuelle répétée à chaque fichier d'un lot.
     *
     * @param  array<int, UploadedFile>  $fichiers
     * @param  array<int>  $schoolIds
     * @param  array<int>  $classeIds
     * @param  ?array<string>  $cibles
     * @return SupportCollection<int, BibliothequeDocument>
     */
    public function importer(array $fichiers, array $schoolIds, ?string $description, ?int $uploadePar, array $classeIds = [], ?array $cibles = null): SupportCollection
    {
        return $this->transaction(function () use ($fichiers, $schoolIds, $description, $uploadePar, $classeIds, $cibles) {
            return collect($fichiers)->map(fn (UploadedFile $fichier) => $this->uploader([
                'titre' => pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $description,
                'school_ids' => $schoolIds,
                'classe_ids' => $classeIds,
                'cibles' => $cibles,
            ], $fichier, $uploadePar))->values();
        });
    }

    /**
     * Modifie le ciblage d'un document existant (école(s), classe(s),
     * destinataires) sans repasser par un nouveau dépôt de fichier.
     *
     * @param  array{titre?: string, description?: ?string, school_ids?: array<int>, classe_ids?: array<int>, cibles?: ?array<string>}  $donnees
     */
    public function modifier(BibliothequeDocument $document, array $donnees): BibliothequeDocument
    {
        return $this->transaction(function () use ($document, $donnees) {
            $champs = [];
            if (array_key_exists('titre', $donnees)) {
                $champs['titre'] = $donnees['titre'];
            }
            if (array_key_exists('description', $donnees)) {
                $champs['description'] = $donnees['description'];
            }
            if (array_key_exists('cibles', $donnees)) {
                $champs['cibles'] = empty($donnees['cibles']) ? null : $donnees['cibles'];
            }
            if ($champs !== []) {
                $document->update($champs);
            }

            if (array_key_exists('school_ids', $donnees)) {
                $document->ecoles()->sync($donnees['school_ids']);
            }

            if (array_key_exists('classe_ids', $donnees)) {
                $document->classes()->sync($donnees['classe_ids']);
            }

            return $document->fresh(['ecoles', 'classes', 'uploadePar']);
        });
    }

    public function supprimer(BibliothequeDocument $document): void
    {
        Storage::disk('public')->delete($document->fichier_path);
        $document->delete();
    }
}
