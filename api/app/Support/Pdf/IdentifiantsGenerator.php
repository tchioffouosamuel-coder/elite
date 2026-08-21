<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Models\User;
use App\Support\Pdf\Concerns\RenduDocument;
use Mpdf\Output\Destination;

/**
 * Identifiants de connexion des comptes de l'établissement — le document que
 * l'administration distribue à l'ouverture des accès (cf. `CompteAgentService`).
 *
 * Le mot de passe n'est imprimé que pour les comptes qui portent encore celui,
 * commun, distribué à l'ouverture (`doit_changer_mot_de_passe`) : une fois
 * personnalisé, il est haché et personne ne peut plus le connaître, pas même
 * l'administrateur qui génère ce document.
 */
class IdentifiantsGenerator
{
    use RenduDocument;

    public function build(array $donnees, School $school): string
    {
        return $this->buildMany([compact('donnees', 'school')]);
    }

    public function buildMany(array $documents): string
    {
        $premier = $documents[0];
        $mpdf = MpdfFactory::make([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 10,
            'margin_bottom' => 12,
        ], $premier['school']);
        $mpdf->SetTitle('Identifiants de connexion');

        foreach ($documents as $index => $document) {
            if ($index > 0) {
                $mpdf->AddPage();
            }

            $donnees = $document['donnees'];
            $school = $document['school'];
            $mpdf->WriteHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                    . '<style>' . $this->stylesBase() . $this->stylesPropres() . '</style></head><body>'
                    . $this->enTeteEcole($school)
                    . '<hr>'
                    . $this->titre($donnees)
                    . $this->avertissement()
                    . $this->tableau($donnees)
                    . '</body></html>'
            );
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function stylesPropres(): string
    {
        return '.bandeau{background:' . self::ARDOISE . ';color:#fff;padding:2mm;text-align:center;'
            . 'font-size:3mm;font-weight:bold;margin:3mm 0}'
            . '.avertissement{background:#fdf1ea;border:0.4px solid ' . self::ACCENT . ';border-radius:2mm;'
            . 'padding:2mm 3mm;font-size:2.6mm;color:' . self::ARDOISE . ';margin-bottom:3mm}'
            . '.identifiants th{font-size:2.6mm}'
            . '.identifiants td{font-size:2.7mm;padding:1.4mm 1mm}'
            . '.identifiants tbody tr:nth-child(even) td{background:#f7f7f5}'
            . '.nom{font-weight:bold;text-transform:uppercase}'
            . '.mdp{font-weight:bold;color:' . self::ARDOISE . ';letter-spacing:0.2mm}'
            . '.personnalise{color:#888;font-style:italic}';
    }

    private function titre(array $donnees): string
    {
        return '<div style="text-align:center;line-height:1.4;">'
            . '<span class="titre">Identifiants de connexion</span><br>'
            . '<span class="titre-en">Login credentials</span>'
            . '</div>'
            . '<div class="bandeau">'
            . 'Comptes actifs <i>/ Active accounts</i> : ' . $donnees['total']
            . '</div>';
    }

    private function avertissement(): string
    {
        return '<div class="avertissement">'
            . '<b>Document confidentiel</b> — à remettre en main propre à chaque titulaire, '
            . 'jamais affiché ni transmis en clair. Chaque mot de passe listé est provisoire '
            . 'et doit être remplacé dès la première connexion.<br>'
            . '<i>Confidential document — hand to each holder in person, never posted or sent unencrypted. '
            . 'Every listed password is temporary and must be changed on first login.</i>'
            . '</div>';
    }

    private function tableau(array $donnees): string
    {
        $lignes = '';
        $rang = 1;

        foreach ($donnees['comptes'] as $compte) {
            /** @var User $compte */
            $lignes .= '<tr>'
                . '<td>' . $rang . '</td>'
                . '<td class="left nom">' . $this->e($compte->name) . '</td>'
                . '<td class="left">' . $this->e($compte->libelleRole() ?? '—') . '</td>'
                // Un compte parent n'a pas d'e-mail (cf. CompteParentService) :
                // son identifiant de connexion est alors le téléphone.
                . '<td class="left">' . $this->e($compte->email ?: ($compte->phone ?: '—')) . '</td>'
                . '<td>' . $this->motDePasse($compte, $donnees['mot_de_passe_defaut']) . '</td>'
                . '</tr>';
            $rang++;
        }

        if ($lignes === '') {
            $lignes = '<tr><td colspan="5" style="padding:6mm;">Aucun compte actif.</td></tr>';
        }

        return '<table class="identifiants"><thead><tr>'
            . '<th style="width:5%;">N°</th>'
            . '<th style="width:27%;">Nom et prénoms<br><i>Full name</i></th>'
            . '<th style="width:18%;">Fonction<br><i>Role</i></th>'
            . '<th style="width:28%;">Identifiant<br><i>Login</i></th>'
            . '<th style="width:22%;">Mot de passe<br><i>Password</i></th>'
            . '</tr></thead><tbody>' . $lignes . '</tbody></table>';
    }

    private function motDePasse(User $compte, string $motDePasseDefaut): string
    {
        return $compte->doit_changer_mot_de_passe
            ? '<span class="mdp">' . $this->e($motDePasseDefaut) . '</span>'
            : '<span class="personnalise">Personnalisé<br><i>Custom</i></span>';
    }
}
