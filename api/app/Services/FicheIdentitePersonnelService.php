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
 * Version .docx de la fiche d'identification du personnel (cf.
 * FicheIdentitePersonnelGenerator pour la version PDF) : même contenu, pour
 * l'établissement qui veut la corriger à la main avant archivage plutôt que
 * ressaisir le dossier.
 */
class FicheIdentitePersonnelService extends BaseService
{
    private const ACCENT = '39B54A';

    private const POINTILLES = '……………………………………';

    public function generer(Personnel $personnel): string
    {
        $school = $personnel->school;
        $feminin = $personnel->sexe === 'F';

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Montserrat');
        $section = $phpWord->addSection([
            'marginTop' => 720, 'marginBottom' => 720, 'marginLeft' => 900, 'marginRight' => 900,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);

        $section->addText("FICHE D'IDENTIFICATION DU PERSONNEL", ['bold' => true, 'size' => 14, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText('STAFF IDENTIFICATION SHEET', ['italic' => true, 'size' => 11, 'color' => self::ACCENT], ['alignment' => 'center', 'spaceAfter' => 200]);

        if ($personnel->photo_path) {
            $chemin = storage_path('app/public/'.ltrim($personnel->photo_path, '/'));
            if (is_file($chemin)) {
                $section->addImage($chemin, ['width' => 90, 'height' => 110, 'alignment' => 'right']);
            }
        }

        $this->rubrique($section, 'Identité', 'Identity');
        $this->champ($section, 'Matricule', 'Staff ID', $personnel->matricule);
        $this->champ($section, 'Nom et prénoms', 'Full name', mb_strtoupper($personnel->nom_complet), true);
        $this->champ($section, 'Sexe', 'Sex', $personnel->sexe ? ($feminin ? 'Féminin' : 'Masculin') : null);
        $this->champ($section, $feminin ? 'Née le' : 'Né le', 'Born on', $personnel->date_naissance?->format('d/m/Y'));
        $this->champ($section, 'Situation matrimoniale', 'Marital status', $this->situationMatrimoniale($personnel->situation_matrimoniale));
        $this->champ($section, 'N° CNI', 'ID card N°', $personnel->numero_cni);
        $this->champ($section, 'N° CNPS', 'Social security N°', $personnel->numero_cnps);
        $this->champ($section, "Département d'origine", 'Home department', $personnel->departement_origine);
        $this->champ($section, 'Résidence', 'Residence', $personnel->residence);
        $this->champ($section, 'Téléphone', 'Phone', $personnel->telephone);
        $this->champ($section, 'Téléphone secondaire', 'Secondary phone', $personnel->telephone_2);
        $this->champ($section, 'E-mail', 'Email', $personnel->email);

        $this->rubrique($section, 'Emploi', 'Employment');
        $this->champ($section, 'Fonction', 'Position', $personnel->fonction);
        $this->champ($section, 'Département / Service', 'Department', $personnel->departement?->nom);
        $this->champ($section, 'Affectation', 'Duty post', $personnel->affectation);
        $this->champ($section, 'En poste depuis le', 'Employed since', $personnel->date_embauche?->format('d/m/Y'));
        $this->champ($section, 'Ancienneté', 'Longevity', $this->anciennete($personnel));
        $this->champ($section, 'N° permis de conduire', 'Driving licence N°', $personnel->numero_permis);
        $this->champ($section, 'Statut', 'Status', $personnel->statut === 'actif' ? 'Actif' : 'Ex-employé');

        $this->rubrique($section, 'Diplômes & coordonnées bancaires', 'Qualifications & bank details');
        $this->champ($section, 'Diplôme académique', 'Academic qualification', $personnel->diplome_academique);
        $this->champ($section, 'Diplôme professionnel', 'Professional qualification', $personnel->diplome_professionnel);
        $this->champ($section, 'Banque', 'Bank', $personnel->banque);
        $this->champ($section, 'N° de compte', 'Account N°', $personnel->numero_compte);

        $this->rubrique($section, 'Filiation', 'Parentage');
        $this->champ($section, 'Père', 'Father', $personnel->pere_nom_complet);
        $this->champ($section, 'Téléphone du père', "Father's phone", $personnel->pere_telephone);
        $this->champ($section, 'Mère', 'Mother', $personnel->mere_nom_complet);
        $this->champ($section, 'Téléphone de la mère', "Mother's phone", $personnel->mere_telephone);
        $this->champ($section, "Nombre d'enfants", 'Number of children', $personnel->nombre_enfants !== null ? (string) $personnel->nombre_enfants : null);

        $this->enfants($section, $personnel);

        $this->signature($section, $school);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/fiche-identification-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    private function rubrique(Section $section, string $fr, string $en): void
    {
        $ligne = $section->addTextRun(['spaceBefore' => 200, 'spaceAfter' => 120, 'borderBottomSize' => 6, 'borderBottomColor' => self::ACCENT]);
        $ligne->addText(mb_strtoupper($fr).' ', ['bold' => true, 'size' => 11, 'color' => '292F36']);
        $ligne->addText('/ '.$en, ['bold' => true, 'italic' => true, 'size' => 9, 'color' => '777777']);
    }

    private function champ(Section $section, string $fr, string $en, ?string $valeur, bool $majuscules = false): void
    {
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 90]);
        $ligne->addText($fr.' / ', ['size' => 10]);
        $ligne->addText($en, ['size' => 10, 'italic' => true]);
        $ligne->addText(' : ', ['size' => 10]);
        $ligne->addText(
            $valeur !== null && trim($valeur) !== '' ? $valeur : self::POINTILLES,
            ['size' => 10, 'bold' => true, 'allCaps' => $majuscules],
        );
    }

    private function enfants(Section $section, Personnel $personnel): void
    {
        $enfants = $personnel->enfants ?? [];

        if ($enfants === []) {
            return;
        }

        $this->rubrique($section, 'Enfants', 'Children');

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);

        $table->addRow();
        $table->addCell(6000)->addText('Nom et prénoms', ['bold' => true, 'size' => 9]);
        $table->addCell(1500)->addText('Sexe', ['bold' => true, 'size' => 9]);
        $table->addCell(2500)->addText('Date de naissance', ['bold' => true, 'size' => 9]);

        foreach ($enfants as $enfant) {
            $table->addRow();
            $table->addCell(6000)->addText($enfant['nom_complet'] ?? '—', ['size' => 9]);
            $table->addCell(1500)->addText(($enfant['sexe'] ?? null) === 'F' ? 'F' : 'M', ['size' => 9]);
            $table->addCell(2500)->addText($this->dateFormatee($enfant['date_naissance'] ?? null) ?? '—', ['size' => 9]);
        }
    }

    private function signature(Section $section, School $school): void
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        $ligne = $section->addTextRun(['spaceBefore' => 300, 'spaceAfter' => 240]);
        $ligne->addText('Fait à '.($ville !== '' ? $ville : '…………').', le / ', ['size' => 11]);
        $ligne->addText('Done at '.($ville !== '' ? $ville : '…………').' on', ['size' => 11, 'italic' => true]);
        $ligne->addText(' : '.now()->format('d/m/Y'), ['size' => 11]);

        $titre = $section->addTextRun(['alignment' => 'right', 'spaceAfter' => 0]);
        $titre->addText("Le Chef d'Établissement", ['size' => 11, 'bold' => true]);
        $titre->addTextBreak();
        $titre->addText('The Principal', ['size' => 10, 'italic' => true]);

        $visa = (new VisaComposeService)->chemin($school);
        if ($visa !== null) {
            $section->addImage($visa, ['height' => 50, 'alignment' => 'right']);
        } else {
            $section->addTextBreak(3);
        }
    }

    private function situationMatrimoniale(?string $valeur): ?string
    {
        return match ($valeur) {
            'celibataire' => 'Célibataire',
            'marie' => 'Marié(e)',
            'divorce' => 'Divorcé(e)',
            'veuf' => 'Veuf/Veuve',
            default => null,
        };
    }

    private function dateFormatee(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->format('d/m/Y');
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
            ($annees > 0 ? $annees.' an'.($annees > 1 ? 's' : '').' ' : '')
                .($mois > 0 || $annees === 0 ? $mois.' mois' : '')
        );
    }
}
