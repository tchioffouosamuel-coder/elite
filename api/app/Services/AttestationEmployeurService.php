<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\School;
use App\Support\Word\EnTeteWord;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Attestation de l'employeur, calquée sur le modèle de l'établissement
 * (« ATTESTATION DE L'EMPLOYEUR / EMPLOYER ATTESTATION »).
 *
 * Le document atteste l'emploi d'un agent et, le cas échéant, la période
 * d'absence autorisée. Il est bilingue ligne à ligne comme le certificat de
 * scolarité, et signé du chef d'établissement.
 *
 * L'ancienneté est calculée à la date d'émission plutôt que stockée : une
 * attestation délivrée six mois plus tard doit annoncer six mois de plus.
 */
class AttestationEmployeurService extends BaseService
{
    private const TYPE_DOCUMENT = 'attestation_employeur';

    private const POINTILLES = '……………………………………';

    public function __construct(private readonly DocumentReferenceService $references) {}

    /**
     * @param  array{debut?: ?string, fin?: ?string, prolongation?: ?string, motif?: ?string}  $conge
     */
    public function generer(Personnel $personnel, array $conge = [], ?int $generePar = null): string
    {
        $personnel->loadMissing(['school', 'fonctionReference']);
        $school = $personnel->school;
        $feminin = $personnel->sexe === 'F';

        $reference = $this->references->attribuer($school->id, self::TYPE_DOCUMENT, null, $personnel, $generePar);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Montserrat');
        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 900,
            'marginRight' => 900,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);

        $avecConge = ! empty($conge['debut']);
        $titre = ! $avecConge
            ? "ATTESTATION DE L'EMPLOYEUR N° "
            : "ATTESTATION DE L'EMPLOYEUR ET NOTIFICATION DE CONGE ANNUEL N° ";
        $titreEn = ! $avecConge
            ? 'EMPLOYER ATTESTATION N° '
            : 'EMPLOYER ATTESTATION AND ANNUAL LEAVE NOTIFICATION N° ';
        $section->addText($titre . $reference->numero, ['bold' => true, 'size' => 13], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText($titreEn . $reference->numero, ['bold' => true, 'italic' => true, 'size' => 10], ['alignment' => 'center', 'spaceAfter' => 160]);

        $ligne = $section->addTextRun(['spaceAfter' => 240]);
        $ligne->addText('Réf. N° ' . $reference->numeroFormate() . ' / ' . ($school->code ?: 'ATT'), ['size' => 10, 'italic' => true]);

        // Déclarant.
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 0]);
        $ligne->addText('Nous ', ['size' => 11]);
        $ligne->addText($school->name, ['size' => 11, 'bold' => true]);
        $ligne->addText(' attestons que l\'employé(e) de nommé(e) :', ['size' => 11]);

        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 160]);
        $ligne->addText('We, ', ['size' => 11, 'italic' => true]);
        $ligne->addText($school->name, ['size' => 11, 'italic' => true, 'bold' => true]);
        $ligne->addText(' hereby certify that the employee named:', ['size' => 11, 'italic' => true]);

        $this->champ($section, 'Nom et prénoms', 'Full name', trim(($personnel->civilite ?? '') . ' ' . mb_strtoupper($personnel->nom_complet)));
        $this->champ($section, 'Matricule interne N°', 'Internal staff N°', $personnel->matricule);
        $this->champ($section, 'N° CNPS', 'Social security N°', $personnel->numero_cnps);
        $this->champ($section, $feminin ? 'Née le' : 'Né le', 'Born on', $personnel->date_naissance?->format('d/m/Y'));
        $this->champ($section, 'Fonction occupée', 'Position held', $personnel->fonction);
        $this->champ($section, 'Responsable de', 'In charge of', $personnel->affectation);
        $this->champ($section, 'En poste depuis le', 'Employed since', $personnel->date_embauche?->format('d/m/Y'));
        $this->champ($section, 'Ancienneté', 'Longevity', $this->anciennete($personnel));

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);

        if (! empty($conge['debut'])) {
            $this->ajouterConge($section, $conge, $feminin);
        } else {
            $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 0]);
            $ligne->addText(
                'Est employé' . ($feminin ? 'e' : '') . ' dans notre établissement et y exerce régulièrement ses fonctions.',
                ['size' => 11],
            );
            $section->addText(
                'Is employed in our institution and duly carries out the said duties.',
                ['size' => 11, 'italic' => true],
                ['alignment' => 'both', 'spaceAfter' => 160],
            );
        }

        $section->addText(
            'En foi de quoi la présente attestation lui est délivrée pour servir et valoir ce que de droit.',
            ['size' => 11],
            ['alignment' => 'both', 'spaceAfter' => 0],
        );
        $section->addText(
            'In witness whereof this attestation is issued to serve where and when necessary.',
            ['size' => 11, 'italic' => true],
            ['alignment' => 'both', 'spaceAfter' => 240],
        );

        $this->signature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/attestation-employeur-' . uniqid() . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /** @param  array{debut?: ?string, fin?: ?string, prolongation?: ?string, motif?: ?string}  $conge */
    private function ajouterConge(Section $section, array $conge, bool $feminin): void
    {
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 0]);
        $ligne->addText(
            'Nous lui notifions que son congé annuel ' . ($conge['motif'] ? '(' . $conge['motif'] . ') ' : '')
                . 'est autorisé pour la période suivante :',
            ['size' => 11, 'color' => 'C00000', 'bold' => true],
        );

        $section->addText(
            'We hereby notify that the following period of annual leave is authorised:',
            ['size' => 11, 'italic' => true, 'color' => 'C00000', 'bold' => true],
            ['alignment' => 'both', 'spaceAfter' => 120],
        );

        $this->champ($section, 'Début', 'Start', $this->jour($conge['debut'] ?? null));
        $this->champ($section, 'Fin', 'End', $this->jour($conge['fin'] ?? null));

        if (! empty($conge['prolongation'])) {
            $this->champ($section, 'Prolongation autorisée', 'Authorised extension', $this->jour($conge['prolongation']));
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 160]);
        $ligne->addText(
            'Passé ce délai, l\'intéressé' . ($feminin ? 'e' : '') . ' est tenu' . ($feminin ? 'e' : '')
                . ' de reprendre son service.',
            ['size' => 11],
        );
    }

    private function champ(Section $section, string $fr, string $en, ?string $valeur): void
    {
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 100]);
        $ligne->addText($fr . ' / ', ['size' => 11]);
        $ligne->addText($en, ['size' => 11, 'italic' => true]);
        $ligne->addText(' : ', ['size' => 11]);
        $ligne->addText(
            $valeur !== null && trim($valeur) !== '' ? $valeur : self::POINTILLES,
            ['size' => 11, 'bold' => true],
        );
    }

    /** Ancienneté en années et mois, arrêtée au jour de l'émission. */
    private function anciennete(Personnel $personnel): ?string
    {
        if (! $personnel->date_embauche) {
            return null;
        }

        $ecart = $personnel->date_embauche->diff(Carbon::today());
        $annees = $ecart->y;
        $mois = $ecart->m;

        return trim(
            ($annees > 0 ? $annees . ' an' . ($annees > 1 ? 's' : '') . ' ' : '')
                . ($mois > 0 || $annees === 0 ? $mois . ' mois' : '')
        );
    }

    private function jour(?string $date): ?string
    {
        return $date ? implode('/', array_reverse(explode('-', substr($date, 0, 10)))) : null;
    }

    private function signature(Section $section, School $school): void
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        $ligne = $section->addTextRun(['spaceAfter' => 240]);
        $ligne->addText('Fait à ' . ($ville !== '' ? $ville : '…………') . ', le / ', ['size' => 11]);
        $ligne->addText('Done at ' . ($ville !== '' ? $ville : '…………') . ' on', ['size' => 11, 'italic' => true]);
        $ligne->addText(' : ' . now()->format('d/m/Y'), ['size' => 11]);

        $titre = $section->addTextRun(['alignment' => 'right', 'spaceAfter' => 0]);
        $titre->addText($school->type === 'secondaire' ? 'Le Principal' : 'Le Directeur', ['size' => 11, 'bold' => true]);
        $titre->addTextBreak();
        $titre->addText($school->type === 'secondaire' ? 'The Principal' : 'The Headmaster', ['size' => 10, 'italic' => true]);

        $section->addTextBreak(3);
        $section->addText('Signature et cachet', ['size' => 9], ['alignment' => 'right', 'spaceAfter' => 0]);
    }
}
