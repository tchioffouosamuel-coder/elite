<?php

namespace App\Support\Pdf;

use App\Models\School;
use App\Models\Setting;
use App\Support\Pdf\Concerns\RenduDocument;
use Carbon\CarbonInterface;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Carbon;
use Mpdf\Output\Destination;

/**
 * Mise en page commune des reçus (scolarité, transport, préinscription),
 * reprise du ticket papier en usage dans l'établissement : rouleau de 80 mm
 * composé à l'italienne (80 × 200 mm, le pilote de l'imprimante thermique
 * fait pivoter la page), en trois zones :
 *
 * - à gauche, imprimés de côté, les historiques (versements, accessoires) ;
 * - un trait épais ;
 * - au centre, l'en-tête (nom FR, sigle, nom EN, autorisation, adresse), puis
 *   les mentions de l'élève, les montants, le code QR et le numéro ;
 * - à droite, la bande réservée à la signature et au cachet du chef
 *   d'établissement.
 *
 * Les générateurs de reçus ne font que réunir les données (cf. `rendre()`).
 */
class GabaritRecuTicket
{
    use RenduDocument;

    /**
     * Rouleau rallongé de 200 à 240 mm : les 40 mm gagnés forment la bande de
     * droite où le chef d'établissement signe et appose son cachet — demande
     * du 19/10/2026, le ticket précédent ne laissait de place qu'au cachet.
     */
    private const FORMAT = [80, 240];

    /**
     * Préfixe d'empreinte du cache de rendus : à incrémenter dès qu'une
     * modification du gabarit change le PDF sans changer le HTML (marges,
     * format, filigrane), pour que les tickets déjà en cache soient recomposés.
     */
    private const EMPREINTE_VERSION = 'ticket-v2';

    /** Largeur du filigrane : tient dans les 80 mm de haut du ticket couché. */
    private const FILIGRANE_LARGEUR = 55;

    /** Abscisse du trait épais séparant les historiques du corps du reçu. */
    private const SEPARATEUR = 40;

    /** Lignes d'historique au-delà desquelles les plus anciennes sont résumées. */
    private const MAX_VERSEMENTS = 5;

    private const MAX_ACCESSOIRES = 4;

    /**
     * @param  array{
     *     titre: string,
     *     mentions: list<array{0: string, 1: string}>,
     *     montants: list<array{0: string, 1: string}>,
     *     versements: list<array{0: string, 1: int}>,
     *     accessoires?: list<array{0: string, 1: int}>|null,
     *     numero: string,
     *     encaisseur: ?string,
     *     enregistre_le?: Carbon|CarbonInterface|null,
     *     qr?: ?string,
     *     annule?: bool,
     *     annule_par?: ?string,
     *     annule_le?: Carbon|CarbonInterface|null,
     * }  $recu
     */
    public function rendre(School $school, array $recu): string
    {
        $html = $this->html($school, $recu);

        if (! config('recu.cache', true)) {
            return $this->composer($school, $recu['numero'], $html);
        }

        // Le HTML détermine entièrement le ticket : à empreinte égale, le même
        // PDF. Réimprimer un reçu revient donc à relire un fichier plutôt qu'à
        // refaire composer la page à mPDF — l'essentiel des quelque 300 ms que
        // coûte un ticket part dans l'ajustement automatique de ses blocs
        // `position:absolute`, qu'on ne peut pas accélérer sans en changer la
        // mise en page, validée telle quelle avec l'établissement.
        return CacheRendus::memoriser(
            self::EMPREINTE_VERSION.'|'.$this->signatureFiligrane($school).'|'.$html,
            fn () => $this->composer($school, $recu['numero'], $html),
        );
    }

    /** Composition effective par mPDF — l'étape coûteuse que le cache évite de refaire. */
    private function composer(School $school, string $numero, string $html): string
    {
        $mpdf = MpdfFactory::make([
            'format' => self::FORMAT,
            'orientation' => 'L',
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ], $school);
        MpdfFactory::appliquerFiligrane($mpdf, $school, self::FILIGRANE_LARGEUR);
        $mpdf->SetTitle('Reçu '.$numero);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** @param  array<string, mixed>  $recu */
    private function html(School $school, array $recu): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.$this->styles().'</style></head><body>'
            // Le trait et le corps sont posés en absolu ; seul le tableau des
            // historiques reste dans le flux : mPDF ne sait faire pivoter qu'un
            // tableau de premier niveau, qu'il place alors en haut à gauche.
            .'<div style="position:absolute;left:'.self::SEPARATEUR.'mm;top:4mm;height:72mm;width:1mm;border-left:0.9mm solid #000"></div>'
            .$this->enTete($school)
            .$this->bloc(42, 29, 27, $this->mentions($recu['mentions']))
            // Titre au-dessus des montants : tant qu'il descendait jusqu'au
            // premier d'entre eux, il recouvrait le nom de l'élève (retour du
            // 19/10/2026) — d'où un titre plus large, plus haut et plus petit.
            .$this->bloc(72, 126, 21, '<div class="titre">'.$this->e($recu['titre']).'</div>')
            .$this->bloc(72, 104, 32, $this->corps($recu))
            .$this->bloc(178, 20, 40, $this->zoneCachet($recu))
            .$this->bloc(200, 36, 14, $this->zoneSignature($school))
            .$this->historiques($recu)
            .'</body></html>';
    }

    /**
     * Le filigrane est le seul élément du ticket qui n'apparaisse pas dans le
     * HTML : il entre donc dans l'empreinte par son chemin et sa date, sans
     * quoi un logo remplacé continuerait d'être servi depuis le cache.
     */
    private function signatureFiligrane(School $school): string
    {
        $logo = $school->logo_path ? storage_path('app/public/'.ltrim($school->logo_path, '/')) : null;

        return $logo !== null && is_file($logo) ? $logo.':'.filemtime($logo) : '';
    }

    private function styles(): string
    {
        return 'body{font-family:dejavuserifcondensed,serif;font-size:2.7mm;color:#000;margin:0}'
            .'table{border-collapse:collapse}'
            .'.ecole{font-weight:bold;font-size:2.7mm;line-height:1.15;text-align:center}'
            .'.sigle{font-weight:bold;font-size:4.5mm;text-align:center}'
            .'.mention-ecole{font-size:2.2mm;font-style:italic;text-align:center;line-height:1.3}'
            .'.titre{font-weight:bold;font-style:italic;font-size:3.4mm;line-height:1.2;text-decoration:underline;text-align:center}'
            .'.cle{font-weight:bold;font-size:2.7mm;text-decoration:underline;text-align:center}'
            .'.valeur{font-size:2.5mm;text-align:center;padding-bottom:1.8mm}'
            .'.montants{width:100%}'
            .'.lib{font-weight:bold;font-size:2.8mm;text-decoration:underline;padding:0.7mm 2mm 0.7mm 0;vertical-align:top;white-space:nowrap}'
            .'.val{font-weight:bold;font-size:3mm;width:40%;padding:0.7mm 0;vertical-align:top}'
            .'.hist td{font-size:2.7mm;padding:0.4mm 1mm;border-bottom:0.2mm solid #000}'
            .'.hist .t{font-size:2.9mm;border-bottom:none;padding-top:1.2mm}'
            .'.pied{font-size:2.3mm;text-align:center}'
            .'.signataire{font-weight:bold;font-size:2.6mm;text-align:center;line-height:1.2}'
            .'.cachet-mention{font-size:2.2mm;font-style:italic;text-align:center;line-height:1.25;padding-bottom:0.8mm}'
            .'.cachet{border:0.2mm dashed #000;height:44mm}'
            .'.annule{color:#ac3527;font-weight:bold;font-size:3.2mm;text-align:center;margin-top:1mm}'
            .'.annule-par{color:#ac3527;font-size:2.2mm;text-align:center;margin-top:0.5mm}'
            .'.enregistre{font-size:2.2mm;font-weight:normal;text-align:center}';
    }

    /** Bloc positionné en absolu : mPDF ignore marges et centrage d'un bloc placé dans une cellule. */
    private function bloc(float $x, float $largeur, float $y, string $contenu): string
    {
        return '<div style="position:absolute;left:'.$x.'mm;top:'.$y.'mm;width:'.$largeur.'mm">'.$contenu.'</div>';
    }

    /**
     * Nom FR à gauche, sigle au centre, nom EN à droite, puis l'autorisation
     * d'ouverture et l'adresse sur toute la largeur. Le nom est la dernière
     * ligne de l'en-tête officiel (`header_fr`/`header_en`) : le cartouche
     * ministériel complet ne tiendrait pas sur 80 mm de haut.
     */
    private function enTete(School $school): string
    {
        $autorisation = Setting::get($school->id, 'numero_autorisation_ouverture');
        // Téléphone affiché sur le reçu : celui du chef d'établissement par
        // défaut (`recu_telephone`), distinct du `phone` générique de l'école
        // qui sert ailleurs (bulletins, portail) — cf. point 5 des retours
        // d'Elites-tech, qui veut le numéro du chef sur le ticket de caisse.
        $telephone = Setting::get($school->id, 'recu_telephone', $school->phone);
        $contacts = array_filter([
            $school->address,
            $telephone ? 'TEL / PHONE : '.$telephone : null,
            $school->email ? 'e-mail : '.$school->email : null,
        ]);

        $mentions = ($autorisation ? '<div>Autorisation d\'ouverture / Opening order N° : '.$this->e($autorisation).'</div>' : '')
            .($contacts !== [] ? '<div>'.$this->e(implode(', ', $contacts)).'</div>' : '');

        return $this->bloc(43, 55, 4, '<div class="ecole">'.$this->e($this->nomEtablissement($school->header_fr, $school->name)).'</div>')
            .$this->bloc(100, 40, 4.5, $this->embleme($school))
            .$this->bloc(145, 55, 4, '<div class="ecole">'.$this->e($this->nomEtablissement($school->header_en, $school->name)).'</div>')
            .$this->bloc(43, 157, 14.5, '<div class="mention-ecole">'.$mentions.'</div>');
    }

    /**
     * Emblème central du ticket : le logo de l'établissement, qui remplace le
     * sigle imprimé en toutes lettres jusqu'ici — demande du 19/10/2026, un
     * reçu se reconnaît mieux à son logo qu'à « ELITES-BTA » écrit en gras.
     *
     * Le sigle reste le repli pour un établissement qui n'a pas encore chargé
     * son logo : nom court configurable (`recu_nom_etablissement`), sinon le
     * code de l'établissement, sinon son nom complet — cf. point 4 des
     * retours, « Elites-BTA » n'est pas écrit en dur, c'est la donnée
     * `schools.code` de cette école précise.
     */
    private function embleme(School $school): string
    {
        $logo = $this->cheminImage($school->logo_path);

        if ($logo === null) {
            $sigle = Setting::get($school->id, 'recu_nom_etablissement') ?: ($school->code ?: $school->name);

            return '<div class="sigle">'.$this->e(mb_strtoupper($sigle)).'</div>';
        }

        $taille = $this->tailleLogo($logo);

        return '<div style="text-align:center"><img src="'.$this->e($logo)
            .'" style="width:'.$taille['largeur'].'mm;height:'.$taille['hauteur'].'mm"></div>';
    }

    /**
     * Boîte du logo sur le ticket : la bande centrale fait 42 mm de large, et
     * les mentions de l'établissement commencent à 15,5 mm — au-delà de 10 mm
     * de haut, le logo passerait par-dessus.
     *
     * @return array{largeur: float, hauteur: float}
     */
    protected function logoBoiteMax(): array
    {
        return ['largeur' => 40.0, 'hauteur' => 10.0];
    }

    /** Dernière ligne non vide d'un en-tête riche — par convention, le nom de l'établissement. */
    private function nomEtablissement(?string $html, string $repli): string
    {
        $texte = strip_tags(preg_replace('#<br\s*/?>|</p>|</div>#i', "\n", (string) $html));
        $lignes = array_values(array_filter(array_map(
            fn ($l) => trim(html_entity_decode($l, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            explode("\n", $texte),
        ), fn ($l) => $l !== '' && ! preg_match('/^\*+$/', $l)));

        return mb_strtoupper($lignes === [] ? $repli : end($lignes));
    }

    /** @param  list<array{0: string, 1: string}>  $mentions */
    private function mentions(array $mentions): string
    {
        $html = '';
        foreach ($mentions as [$cle, $valeur]) {
            $html .= '<div class="cle">'.$this->e($cle).'</div><div class="valeur">'.$this->e($valeur).'</div>';
        }

        return $html;
    }

    /** @param  array<string, mixed>  $recu */
    private function corps(array $recu): string
    {
        $html = '<table class="montants">';
        foreach ($recu['montants'] as [$cle, $valeur]) {
            $html .= '<tr><td class="lib">'.$this->e($cle).'</td><td class="val">'.$this->e($valeur).'</td></tr>';
        }
        $html .= '</table>';

        if ($recu['annule'] ?? false) {
            $html .= '<div class="annule">*** REÇU ANNULÉ / RECEIPT CANCELLED ***</div>';

            // Qui a annulé et quand : seule « modification » possible d'un
            // versement (point 12) — un reçu ne se corrige jamais sur place.
            $annulePar = $recu['annule_par'] ?? null;
            $annuleLe = $recu['annule_le'] ?? null;
            if ($annulePar || $annuleLe) {
                $html .= '<div class="annule-par">'
                    .'Annulé'.($annulePar ? ' par '.$this->e($annulePar) : '')
                    .($annuleLe ? ' le '.$this->e($annuleLe->format('d/m/Y')).' à '.$this->e($annuleLe->format('H:i')) : '')
                    .' / Cancelled'.($annulePar ? ' by '.$this->e($annulePar) : '')
                    .($annuleLe ? ' on '.$this->e($annuleLe->format('d/m/Y')).' at '.$this->e($annuleLe->format('H:i')) : '')
                    .'</div>';
            }
        }

        return $html;
    }

    /**
     * Coin inférieur droit : code QR de vérification d'authenticité, numéro
     * du reçu et caissier. Le reste de la droite du ticket est laissé libre
     * pour le cachet et la signature, apposés à la main au comptoir.
     *
     * @param  array<string, mixed>  $recu
     */
    private function zoneCachet(array $recu): string
    {
        $qr = ! empty($recu['qr'])
            ? '<img src="'.$recu['qr'].'" style="width:13mm;height:13mm">'
            : '';

        // Date et heure d'enregistrement (point 12), sous le nom du caissier
        // déjà affiché — c'est la trace de « qui, quand » demandée en plus.
        $enregistreLe = $recu['enregistre_le'] ?? null;
        $enregistre = $enregistreLe
            ? '<div class="enregistre">Enregistré le '.$this->e($enregistreLe->format('d/m/Y')).' à '.$this->e($enregistreLe->format('H:i')).'<br>'
                .'Recorded on '.$this->e($enregistreLe->format('d/m/Y')).' at '.$this->e($enregistreLe->format('H:i')).'</div>'
            : '';

        return '<div class="pied">'.$qr.'<br>'
            .'N° '.$this->e($recu['numero']).'<br>'
            .'<b>'.$this->e(mb_strtoupper((string) ($recu['encaisseur'] ?? ''))).'</b>'
            .$enregistre
            .'</div>';
    }

    /**
     * Bande de droite, laissée vierge sous le nom du signataire pour sa
     * signature et son cachet — la raison d'être des 40 mm ajoutés au rouleau.
     *
     * Le signataire est configurable (`recu_signataire`) : toutes les écoles
     * du complexe ne sont pas dirigées par une directrice.
     */
    private function zoneSignature(School $school): string
    {
        $signataire = Setting::get($school->id, 'recu_signataire') ?: 'La Directrice / The Principal';

        return '<div class="signataire">'.$this->e($signataire).'</div>'
            .'<div class="cachet-mention">Signature et cachet<br>Signature and stamp</div>'
            .'<div class="cachet"></div>';
    }

    /**
     * Historiques, imprimés de côté le long du bord gauche du ticket : un
     * tableau pivoté d'un quart de tour, dont la largeur devient la hauteur
     * du ticket.
     *
     * @param  array<string, mixed>  $recu
     */
    private function historiques(array $recu): string
    {
        $lignes = $this->section(
            'Historique des versements / Payment history',
            ['Date de paiement / Date', 'Montant / Amount'],
            $this->resumer($recu['versements'], self::MAX_VERSEMENTS, 'versements', 'payments'),
        );

        if (($recu['accessoires'] ?? null) !== null) {
            $lignes .= $this->section(
                'Historique des accessoires / Other fees history',
                ['Accessoires / Other fees', 'Montant / Amount'],
                $this->resumer($recu['accessoires'], self::MAX_ACCESSOIRES, 'accessoires', 'other fees'),
            );
        }

        return '<table class="hist" rotate="90" style="width:72mm">'.$lignes.'</table>';
    }

    /**
     * @param  list<string>  $colonnes
     * @param  list<array{0: string, 1: string}>  $lignes
     */
    private function section(string $titre, array $colonnes, array $lignes): string
    {
        $html = '<tr><td class="t" colspan="2" style="text-align:center"><b>'.$this->e($titre).'</b></td></tr>'
            .'<tr><td style="width:60%">'.$this->e($colonnes[0]).'</td><td style="text-align:right">'.$this->e($colonnes[1]).'</td></tr>';

        foreach ($lignes as [$libelle, $montant]) {
            $html .= '<tr><td>'.$this->e($libelle).'</td><td style="text-align:right">'.$this->e($montant).'</td></tr>';
        }

        return $html;
    }

    /**
     * Les plus récentes d'abord ; au-delà du maximum, les plus anciennes
     * sont regroupées sur une ligne pour que le ticket garde sa taille.
     *
     * @param  list<array{0: string, 1: int}>  $lignes
     * @return list<array{0: string, 1: string}>
     */
    private function resumer(array $lignes, int $max, string $nomFr, string $nomEn): array
    {
        $affichees = array_slice($lignes, 0, $max - 1);
        $reste = array_slice($lignes, $max - 1);

        if (count($reste) === 1) {
            $affichees[] = $reste[0];
        } elseif ($reste !== []) {
            $affichees[] = [
                '+ '.count($reste).' '.$nomFr.' antérieurs / + '.count($reste).' previous '.$nomEn,
                array_sum(array_column($reste, 1)),
            ];
        }

        return array_map(fn ($l) => [$l[0], self::montant($l[1])], $affichees);
    }

    /**
     * Code QR du ticket, en image encodée dans l'URL — vers la page publique
     * de vérification d'authenticité du reçu.
     *
     * Mémorisé : le lien d'un reçu ne change jamais, et l'encodage coûte à lui
     * seul une vingtaine de millisecondes, payées jusqu'ici à chaque
     * réimpression. Un échec d'encodage n'empêche pas d'imprimer le reçu : le
     * QR est un complément de vérification, pas une mention obligatoire.
     */
    public static function qr(string $lien): ?string
    {
        try {
            return CacheRendus::memoriser(
                'qr-v1|'.$lien,
                fn () => (new Builder)->build(data: $lien, size: 200, margin: 4)->getDataUri(),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function montant(int $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }

    public static function francs(int $valeur): string
    {
        return self::montant($valeur).' cfa';
    }

    public static function libelleMode(string $mode): string
    {
        return match ($mode) {
            'mobile_money' => 'MOBILE MONEY',
            'virement' => 'BANK TRANSFER',
            'cheque' => 'CHEQUE',
            'depot_bancaire' => 'BANK DEPOSIT',
            default => 'CASH',
        };
    }
}
