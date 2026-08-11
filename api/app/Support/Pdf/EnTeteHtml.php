<?php

namespace App\Support\Pdf;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Les champs header_fr/header_en sont saisis via un éditeur WYSIWYG (HTML :
 * gras, italique, alignement) mais restent affichés dans des PDF générés
 * côté serveur — jamais exécutés dans un navigateur. Le risque n'est donc pas
 * l'exécution de script, mais un balisage cassé ou des attributs superflus
 * qui abîmeraient la mise en page ; on filtre par prudence via une liste
 * blanche plutôt que de faire confiance telle quelle à la saisie admin.
 */
class EnTeteHtml
{
    private const BALISES_AUTORISEES = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'span', 'div', 'ul', 'ol', 'li'];

    /**
     * Rendu prêt à l'emploi pour un document : HTML nettoyé pour les valeurs
     * saisies via l'éditeur, ou repli sur l'ancien format texte brut (avec de
     * vrais retours à la ligne) pour les établissements pas encore resaisis.
     */
    public static function render(?string $valeur): string
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return '';
        }

        if (! str_contains($valeur, '<')) {
            return nl2br(htmlspecialchars($valeur, ENT_QUOTES, 'UTF-8'));
        }

        return self::nettoyer($valeur);
    }

    /** Filtre à la sauvegarde : ne garde que la mise en forme, jette le reste. */
    public static function nettoyer(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $racine = $doc->getElementsByTagName('div')->item(0);
        if (! $racine) {
            return null;
        }

        self::filtrerEnfants($racine);

        $sortie = '';
        foreach (iterator_to_array($racine->childNodes) as $enfant) {
            $sortie .= $doc->saveHTML($enfant);
        }

        return $sortie !== '' ? $sortie : null;
    }

    private static function filtrerEnfants(DOMNode $noeud): void
    {
        foreach (iterator_to_array($noeud->childNodes) as $enfant) {
            if (! $enfant instanceof DOMElement) {
                continue;
            }

            if (! in_array($enfant->nodeName, self::BALISES_AUTORISEES, true)) {
                // Balise interdite (script, style, table…) : on garde le texte
                // qu'elle contient, on jette juste l'enveloppe.
                while ($enfant->firstChild) {
                    $noeud->insertBefore($enfant->firstChild, $enfant);
                }
                $noeud->removeChild($enfant);

                continue;
            }

            foreach (iterator_to_array($enfant->attributes ?? []) as $attribut) {
                $garder = $attribut->name === 'style'
                    && preg_match('/^text-align:\s*(left|center|right|justify);?$/', trim($attribut->value));

                if (! $garder) {
                    $enfant->removeAttribute($attribut->name);
                }
            }

            self::filtrerEnfants($enfant);
        }
    }
}
