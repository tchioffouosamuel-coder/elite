<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Tuteur;
use App\Support\Word\EnTeteWord;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Certificat de scolarité, calqué sur le modèle en usage dans l'établissement
 * (« CERTIFICAT DE SCOLARITE / SCHOOL ATTENDANCE CERTIFICATE »).
 *
 * Le document précédent était un paragraphe français d'une seule traite. Le
 * modèle officiel est bilingue ligne à ligne, mentionne le chef
 * d'établissement signataire, l'acte de naissance et la filiation, et se
 * présente sur le papier à en-tête de l'école : un certificat rédigé
 * autrement n'était pas accepté par les administrations qui le réclament.
 */
class AttestationService extends BaseService
{
    /** Fonctions qui signent le certificat, du plus spécifique au plus général. */
    private const SIGNATAIRES = ['Principal', 'Directeur', 'Directrice', "Chef d'Établissement"];

    /** Type sous lequel ce document est numéroté dans le registre (cf. DocumentReferenceService). */
    private const TYPE_DOCUMENT = 'certificat_scolarite';

    public function __construct(private readonly DocumentReferenceService $references) {}

    /**
     * Génère le certificat de scolarité en .docx et retourne le chemin du
     * fichier temporaire (à supprimer après envoi par le contrôleur).
     */
    public function genererScolarite(Eleve $eleve): string
    {
        $classe = $eleve->classe;
        $school = $eleve->school;
        $annee = AnneeScolaire::where('school_id', $eleve->school_id)->where('is_active', true)->first();
        $feminin = $eleve->sexe === 'F';

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Montserrat');
        $section = $phpWord->addSection([
            'marginTop' => 720, 'marginBottom' => 720, 'marginLeft' => 900, 'marginRight' => 900,
        ]);

        EnTeteWord::filigrane($section, $school);
        EnTeteWord::ajouter($section, $school);

        $section->addText('CERTIFICAT DE SCOLARITE', ['bold' => true, 'size' => 15], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText('SCHOOL ATTENDANCE CERTIFICATE', ['bold' => true, 'italic' => true, 'size' => 12], ['alignment' => 'center', 'spaceAfter' => 200]);

        $reference = $this->references->attribuer(
            $school->id,
            self::TYPE_DOCUMENT,
            $annee?->id,
            $eleve,
            auth()->id(),
        );
        $this->ajouterReference($section, $school, $eleve, $annee, $reference->numeroFormate());

        // Signataire : « Je soussigné(e) … Le principal du collège … »
        $signataire = $this->signataire($school);
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 120]);
        $ligne->addText('Je soussigné'.($this->signataireFeminin($signataire) ? 'e' : '').' / ', ['size' => 11]);
        $ligne->addText('I the undersigned', ['size' => 11, 'italic' => true]);
        $ligne->addText(' : ', ['size' => 11]);
        $ligne->addText($signataire?->nom_complet ?? self::POINTILLES, ['size' => 11, 'bold' => true]);

        $etablissement = $this->designation($school);
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 0]);
        $ligne->addText($this->titreFonction($school).' '.$etablissement['fr'].' ', ['size' => 11]);
        $ligne->addText('« '.$school->name.' »', ['size' => 11, 'bold' => true]);
        $ligne->addText(", déclare que l'élève :", ['size' => 11]);

        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 160]);
        $ligne->addText($etablissement['en'].' ', ['size' => 11, 'italic' => true]);
        $ligne->addText('« '.$school->name.' »', ['size' => 11, 'italic' => true, 'bold' => true]);
        $ligne->addText(' declares that the student by name:', ['size' => 11, 'italic' => true]);

        $this->ajouterChamp($section, 'Nom et prénoms', 'Name', mb_strtoupper($eleve->nom_complet), true);
        $this->ajouterChamp($section, 'Classe', 'Class', $classe?->nom);
        $this->ajouterChamp(
            $section,
            $feminin ? 'Née le' : 'Né le',
            'Born on',
            $this->dateEtLieu($eleve),
        );
        $this->ajouterChamp($section, 'Acte de naissance N°', 'Birth certificate N°', $eleve->numero_acte_naissance);
        $this->ajouterChamp(
            $section,
            $feminin ? 'Fille de' : 'Fils de',
            $feminin ? 'Daughter of' : 'Son of',
            $this->parent($eleve, 'pere'),
        );
        $this->ajouterChamp($section, 'Et de', 'And of', $this->parent($eleve, 'mere'));

        $section->addTextBreak(1, null, ['spaceAfter' => 0]);

        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 0]);
        $ligne->addText(
            'Est inscrit'.($feminin ? 'e' : '').' et fréquente régulièrement '.$etablissement['ce']
                .' durant l\'année scolaire ',
            ['size' => 11],
        );
        $ligne->addText($annee?->libelle ?? self::POINTILLES, ['size' => 11, 'bold' => true]);
        $ligne->addText('.', ['size' => 11]);

        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 160]);
        $ligne->addText(
            'Is registered and regularly present in school within the school year '
                .($annee?->libelle ?? self::POINTILLES).'.',
            ['size' => 11, 'italic' => true],
        );

        $section->addText(
            'En foi de quoi ce certificat lui a été délivré pour servir et valoir ce que de droit.',
            ['size' => 11],
            ['alignment' => 'both', 'spaceAfter' => 0],
        );
        $section->addText(
            'In testimony whereof this certificate is issued to serve where and when necessary.',
            ['size' => 11, 'italic' => true],
            ['alignment' => 'both', 'spaceAfter' => 240],
        );

        $this->ajouterSignature($section, $school, $signataire);

        $directory = storage_path('app/tmp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/certificat-scolarite-'.uniqid().'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /** Pointillés du modèle, là où l'information manque et doit être complétée à la main. */
    private const POINTILLES = '……………………………………';

    /**
     * Ligne de référence. Le numéro d'ordre vient du registre des documents
     * (cf. DocumentReferenceService), plutôt que d'être laissé en pointillés
     * pour un registre papier.
     */
    private function ajouterReference(Section $section, School $school, Eleve $eleve, ?AnneeScolaire $annee, string $numeroFormate): void
    {
        $ligne = $section->addTextRun(['spaceAfter' => 240]);
        $ligne->addText('Réf. N° '.$numeroFormate.' / '.($school->code ?: 'CDC').' / '.($annee?->libelle ?? '202…-202…'), ['size' => 10, 'italic' => true]);
        $ligne->addText("\t\t", ['size' => 10]);
        $ligne->addText('Matricule : ', ['size' => 10, 'italic' => true]);
        $ligne->addText($eleve->matricule ?: self::POINTILLES, ['size' => 10, 'bold' => true]);
    }

    /** Une ligne « Libellé / Label : valeur », le libellé anglais en italique. */
    private function ajouterChamp(Section $section, string $fr, string $en, ?string $valeur, bool $majuscules = false): void
    {
        $ligne = $section->addTextRun(['alignment' => 'both', 'spaceAfter' => 100]);
        $ligne->addText($fr.' / ', ['size' => 11]);
        $ligne->addText($en, ['size' => 11, 'italic' => true]);
        $ligne->addText(' : ', ['size' => 11]);
        $ligne->addText(
            $valeur !== null && trim($valeur) !== '' ? $valeur : self::POINTILLES,
            ['size' => 11, 'bold' => true, 'allCaps' => $majuscules],
        );
    }

    private function dateEtLieu(Eleve $eleve): ?string
    {
        $date = $eleve->date_naissance?->format('d/m/Y');
        $lieu = trim((string) $eleve->lieu_naissance);

        if ($date === null && $lieu === '') {
            return null;
        }

        return trim(($date ?? self::POINTILLES).($lieu !== '' ? ' à / at '.$lieu : ''));
    }

    /**
     * Père ou mère, repérés par le lien de parenté du rattachement. L'import
     * du fichier de situation nomme ces liens « Père » et « Mère » ; une fiche
     * saisie à la main peut employer une autre casse ou l'anglais.
     */
    private function parent(Eleve $eleve, string $role): ?string
    {
        $prefixes = $role === 'pere' ? ['pere', 'père', 'father'] : ['mere', 'mère', 'mother'];

        $tuteur = $eleve->tuteurs->first(function (Tuteur $tuteur) use ($prefixes) {
            $lien = mb_strtolower(trim((string) $tuteur->pivot->lien_parente));

            return $lien !== '' && collect($prefixes)->contains(fn (string $p) => str_starts_with($lien, $p));
        });

        // Un nom d'attente (« Père de X ») vaut absence de nom : l'import crée
        // ce libellé quand le fichier ne donne qu'un numéro de téléphone.
        $nom = $tuteur?->nom_complet;

        return $nom !== null && preg_match('/^(Père|Mère|Tuteur|Contact) de /iu', $nom) !== 1 ? $nom : null;
    }

    /** « collège » au secondaire, « école » au primaire et en maternelle. */
    private function designation(School $school): array
    {
        return $school->type === 'secondaire'
            ? ['fr' => 'du collège', 'en' => 'Principal of the college', 'ce' => 'ce collège']
            : ['fr' => "de l'école", 'en' => 'Head of the school', 'ce' => 'cette école'];
    }

    private function titreFonction(School $school): string
    {
        return $school->type === 'secondaire' ? 'Le Principal' : 'Le Directeur';
    }

    /** Chef d'établissement en poste, s'il figure au fichier du personnel. */
    private function signataire(School $school): ?Personnel
    {
        $fonctions = FonctionReferentiel::forSchool($school->id)
            ->whereIn('label_fr', self::SIGNATAIRES)
            ->pluck('id');

        if ($fonctions->isEmpty()) {
            return null;
        }

        return Personnel::forSchool($school->id)
            ->whereIn('fonction_id', $fonctions)
            ->where('statut', 'actif')
            ->first();
    }

    private function signataireFeminin(?Personnel $signataire): bool
    {
        return $signataire?->sexe === 'F';
    }

    private function ajouterSignature(Section $section, School $school, ?Personnel $signataire): void
    {
        $ville = trim(explode(',', (string) $school->address)[0] ?? '');

        $ligne = $section->addTextRun(['spaceAfter' => 240]);
        $ligne->addText('Fait à '.($ville !== '' ? $ville : '…………').', le / ', ['size' => 11]);
        $ligne->addText('Done at '.($ville !== '' ? $ville : '…………').' on', ['size' => 11, 'italic' => true]);
        $ligne->addText(' : '.now()->format('d/m/Y'), ['size' => 11]);

        $titre = $section->addTextRun(['alignment' => 'right', 'spaceAfter' => 0]);
        $titre->addText($this->titreFonction($school), ['size' => 11, 'bold' => true]);
        $titre->addTextBreak();
        $titre->addText($school->type === 'secondaire' ? 'The Principal' : 'The Headmaster', ['size' => 10, 'italic' => true]);

        $this->ajouterVisa($section, $school);

        if ($signataire !== null) {
            $section->addText($signataire->nom_complet, ['size' => 11, 'bold' => true], ['alignment' => 'right', 'spaceAfter' => 0]);
        }
    }

    /** Cachet et signature composés en une image (la signature traverse le cachet), s'ils ont été chargés. */
    private function ajouterVisa(Section $section, School $school): void
    {
        $visa = (new VisaComposeService)->chemin($school);

        if ($visa === null) {
            // Sans visa scanné, il faut laisser la place de signer à la main.
            $section->addTextBreak(3);

            return;
        }

        $section->addImage($visa, ['height' => 50, 'alignment' => 'right']);
    }
}
