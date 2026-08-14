<?php

namespace App\Services;

use App\Models\Eleve;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class AttestationService extends BaseService
{
    /**
     * Génère une attestation de scolarité .docx et retourne le chemin du
     * fichier temporaire (à supprimer après envoi par le contrôleur).
     */
    public function genererScolarite(Eleve $eleve): string
    {
        $classe = $eleve->classe;
        $school = $eleve->school;
        $annee = $classe?->anneeScolaire;

        $phpWord = new PhpWord;
        $section = $phpWord->addSection(['marginTop' => 1200, 'marginBottom' => 1200]);

        $section->addText(mb_strtoupper($school->name), ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        if ($school->address) {
            $section->addText($school->address, ['size' => 10], ['alignment' => 'center']);
        }
        $section->addTextBreak(2);
        $section->addText(
            'ATTESTATION DE SCOLARITÉ',
            ['bold' => true, 'size' => 16, 'underline' => 'single'],
            ['alignment' => 'center']
        );
        $section->addTextBreak(2);

        $texte = sprintf(
            "Le Chef d'Établissement de %s certifie que l'élève %s, %s le %s%s, matricule n° %s, est régulièrement inscrit(e) en classe de %s au titre de l'année scolaire %s.",
            $school->name,
            mb_strtoupper($eleve->nom_complet),
            $eleve->sexe === 'F' ? 'née' : 'né',
            $eleve->date_naissance?->format('d/m/Y') ?? '—',
            $eleve->lieu_naissance ? " à {$eleve->lieu_naissance}" : '',
            $eleve->matricule ?? '—',
            $classe?->nom ?? '—',
            $annee?->libelle ?? '—'
        );
        $section->addText($texte, ['size' => 12], ['alignment' => 'both', 'spaceAfter' => 200]);

        $section->addTextBreak(1);
        $section->addText(
            "Cette attestation est délivrée à l'intéressé(e) pour servir et valoir ce que de droit.",
            ['size' => 11]
        );

        $lieu = $school->address ? trim(explode(',', $school->address)[0]) : '____________';
        $section->addTextBreak(3);
        $section->addText("Fait à {$lieu}, le ".now()->format('d/m/Y'), ['size' => 11], ['alignment' => 'right']);
        $section->addTextBreak(2);
        $section->addText("Le Chef d'Établissement", ['size' => 11, 'bold' => true], ['alignment' => 'right']);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/attestation-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }
}
