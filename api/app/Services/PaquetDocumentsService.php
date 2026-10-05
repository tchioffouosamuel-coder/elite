<?php

namespace App\Services;

use App\Models\User;
use App\Support\Documents\ExecuteurDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Paquet de documents (ZIP) produit par lots successifs.
 *
 * Un paquet « tous les bulletins de toutes les écoles » représente des
 * centaines de PDF : en une seule requête, il dépasserait le délai
 * d'exécution du serveur. Comme l'import découpé des préinscriptions, le
 * client appelle donc `traiter()` en boucle ; chaque appel produit autant
 * de fichiers que le permet un budget de temps, puis rend la main.
 */
class PaquetDocumentsService
{
    /**
     * Au-delà, le lot s'arrête et rend la main au client. Assez bas pour
     * qu'un dernier document lourd (~10 s) tienne encore sous la limite de
     * 30 s d'un hébergement qui interdit `set_time_limit()`.
     */
    private const BUDGET_SECONDES = 15;

    /** Paquets abandonnés (jamais téléchargés) supprimés au bout de ce délai. */
    private const DUREE_VIE_HEURES = 24;

    public function __construct(private readonly ExecuteurDocument $executeur) {}

    /** @param  list<array<string, mixed>>  $items */
    public function creer(User $user, array $items): string
    {
        $this->purgerAnciens();

        $token = (string) Str::uuid();
        File::ensureDirectoryExists($this->dossier($token));

        $this->ecrireEtat($token, [
            'user_id' => $user->id,
            'cree_le' => now()->toIso8601String(),
            'items' => $items,
            'position' => 0,
            'fichiers' => 0,
            'erreurs' => [],
            'noms' => [],
        ]);

        return $token;
    }

    /** @return array{total: int, traites: int, fichiers: int, erreurs: int, termine: bool, derniere_erreur: ?string} */
    public function traiter(string $token, User $user): array
    {
        $etat = $this->etat($token, $user);
        // Allonger une limite trop courte, jamais en imposer une : en CLI ou
        // dans un worker longue durée (limite 0 = illimitée), elle vaudrait
        // pour tout le processus, pas pour ce seul lot.
        $limite = (int) ini_get('max_execution_time');
        if ($limite > 0 && $limite < self::BUDGET_SECONDES * 4) {
            @set_time_limit(self::BUDGET_SECONDES * 4);
        }

        $zip = new ZipArchive;
        if ($zip->open($this->cheminZip($token), ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive du paquet.");
        }

        $debut = microtime(true);
        $total = count($etat['items']);

        while ($etat['position'] < $total && (microtime(true) - $debut) < self::BUDGET_SECONDES) {
            $item = $etat['items'][$etat['position']];

            try {
                $fichier = $this->executeur->executer($item, $user);
                $chemin = $this->cheminUnique($etat['noms'], $item['dossier'], $item['nom'], $fichier['extension']);
                $zip->addFromString($chemin, $fichier['contenu']);
                $etat['fichiers']++;
            } catch (\Throwable $e) {
                $etat['erreurs'][] = "{$item['dossier']}/{$item['nom']} ({$item['format']}) : ".Str::limit($e->getMessage(), 300);
            }

            $etat['position']++;
        }

        $termine = $etat['position'] >= $total;

        // Le relevé des échecs voyage avec le paquet : rien ne disparaît en silence.
        if ($termine && $etat['erreurs'] !== []) {
            $zip->addFromString('_documents-non-generes.txt', implode(PHP_EOL, $etat['erreurs']).PHP_EOL);
        }

        // Sans aucune entrée, `close()` ne crée pas le fichier : `telechargement()` le constatera.
        $zip->close();

        $this->ecrireEtat($token, $etat);

        return [
            'total' => $total,
            'traites' => $etat['position'],
            'fichiers' => $etat['fichiers'],
            'erreurs' => count($etat['erreurs']),
            'termine' => $termine,
            'derniere_erreur' => $etat['erreurs'] === [] ? null : end($etat['erreurs']),
        ];
    }

    /**
     * Un paquet d'un seul fichier est rendu tel quel, sans archive autour :
     * c'est le cas de la génération unitaire.
     *
     * @return array{chemin: ?string, contenu: ?string, nom: string}
     */
    public function telechargement(string $token, User $user): array
    {
        $etat = $this->etat($token, $user);
        $zip = $this->cheminZip($token);

        if (! is_file($zip)) {
            throw new RuntimeException($etat['erreurs'] === []
                ? "Aucun fichier n'a été produit."
                : 'Aucun fichier n\'a pu être produit : '.$etat['erreurs'][0]);
        }

        if ($etat['fichiers'] === 1 && $etat['erreurs'] === []) {
            $archive = new ZipArchive;
            $archive->open($zip);
            $nom = basename((string) $archive->getNameIndex(0));
            $contenu = (string) $archive->getFromIndex(0);
            $archive->close();

            return ['chemin' => null, 'contenu' => $contenu, 'nom' => $nom];
        }

        return ['chemin' => $zip, 'contenu' => null, 'nom' => 'documents-'.now()->format('Y-m-d_His').'.zip'];
    }

    public function supprimer(string $token, User $user): void
    {
        $this->etat($token, $user);
        File::deleteDirectory($this->dossier($token));
    }

    /** @param  array<string, true>  $noms */
    private function cheminUnique(array &$noms, string $dossier, string $nom, string $extension): string
    {
        $base = ($dossier !== '' ? $dossier.'/' : '').$nom;
        $chemin = "{$base}.{$extension}";

        for ($i = 2; isset($noms[$chemin]); $i++) {
            $chemin = "{$base} ({$i}).{$extension}";
        }

        $noms[$chemin] = true;

        return $chemin;
    }

    /** @return array<string, mixed> */
    private function etat(string $token, User $user): array
    {
        $fichier = $this->dossier($token).'/etat.json';
        abort_unless(Str::isUuid($token) && is_file($fichier), 404, 'Ce paquet est introuvable ou a expiré.');

        $etat = json_decode((string) file_get_contents($fichier), true);
        abort_unless((int) $etat['user_id'] === $user->id, 403, 'Ce paquet a été préparé par un autre compte.');

        return $etat;
    }

    /** @param  array<string, mixed>  $etat */
    private function ecrireEtat(string $token, array $etat): void
    {
        file_put_contents($this->dossier($token).'/etat.json', json_encode($etat, JSON_UNESCAPED_UNICODE));
    }

    public function cheminZip(string $token): string
    {
        return $this->dossier($token).'/paquet.zip';
    }

    private function dossier(string $token): string
    {
        // Le jeton est un UUID généré ici, vérifié avant tout accès disque.
        return storage_path('app/private/paquets-documents/'.$token);
    }

    private function purgerAnciens(): void
    {
        $racine = storage_path('app/private/paquets-documents');
        if (! is_dir($racine)) {
            return;
        }

        foreach (File::directories($racine) as $dossier) {
            if (filemtime($dossier) < now()->subHours(self::DUREE_VIE_HEURES)->getTimestamp()) {
                File::deleteDirectory($dossier);
            }
        }
    }
}
