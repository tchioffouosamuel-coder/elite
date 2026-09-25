<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Un document de la bibliothèque numérique, visible par les écoles rattachées via {@see ecoles()}. */
class BibliothequeDocument extends Model
{
    protected $table = 'bibliotheque_documents';

    /** Destinataires possibles d'un document — `cibles` vide/null = tout le monde. */
    public const CIBLES = ['personnel', 'parents'];

    protected $fillable = [
        'titre', 'description', 'fichier_path', 'fichier_nom_original', 'taille', 'type_mime', 'cibles', 'uploaded_par',
    ];

    protected function casts(): array
    {
        return ['taille' => 'integer', 'cibles' => 'array'];
    }

    public function ecoles(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'bibliotheque_document_school');
    }

    /** Classes auxquelles ce document est restreint — vide = toute l'école. */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'bibliotheque_document_classe');
    }

    public function uploadePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_par');
    }

    public function getFichierUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->fichier_path);
    }

    /** Documents Word : convertis en PDF pour la visionneuse de l'app mobile. */
    public const EXTENSIONS_CONVERTIBLES = ['doc', 'docx'];

    public function extension(): string
    {
        return strtolower(pathinfo($this->fichier_nom_original ?: $this->fichier_path, PATHINFO_EXTENSION));
    }

    /**
     * Aperçu PDF d'un document Word — la visionneuse protégée de l'app
     * n'affiche que du PDF. Lien signé et temporaire : il n'est remis qu'à
     * ceux qui voient déjà le document dans leur liste, sans refaire côté
     * route tout le ciblage (écoles, classes, destinataires). Null pour un
     * format déjà lisible tel quel (PDF, image) ou non convertible.
     */
    public function getApercuUrlAttribute(): ?string
    {
        if (! in_array($this->extension(), self::EXTENSIONS_CONVERTIBLES, true)) {
            return null;
        }

        return URL::temporarySignedRoute('api.v1.bibliotheque.apercu', now()->addDay(), ['id' => $this->id]);
    }

    /** @param int|array<int> $schoolId */
    public function scopeVisiblePour(Builder $query, int|array $schoolId): Builder
    {
        return $query->whereHas('ecoles', fn (Builder $q) => $q->whereIn('schools.id', (array) $schoolId));
    }

    /** Un document ciblant explicitement `$cible` (« personnel » ou « parents »), ou aucun public particulier. */
    public function scopeCiblant(Builder $query, string $cible): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('cibles')->orWhereJsonContains('cibles', $cible));
    }

    /**
     * Un document sans classe rattachée (visible par toute l'école) ou
     * restreint à l'une des classes de `$classeIds` — c'est ce qui distingue
     * un document déposé pour tout le monde de celui réservé à une classe.
     *
     * @param  array<int>  $classeIds
     */
    public function scopePourClasses(Builder $query, array $classeIds): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereDoesntHave('classes')
            ->orWhereHas('classes', fn (Builder $c) => $c->whereIn('classes.id', $classeIds)));
    }
}
