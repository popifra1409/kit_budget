<?php

namespace App\Helpers;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

/**
 * Géométrie des documents DomPDF A4 : estimation de la hauteur réelle des blocs
 * (métriques de police DomPDF) pour découper les tableaux afin que le bloc de
 * signatures reste sur une page qui a la place de l'afficher en entier.
 *
 * Ne contient aucune règle métier : uniquement des points et des lignes de texte.
 */
class MiseEnPagePdf
{
    public const PAGE_H = 841.89;
    public const PAGE_L = 595.28;

    public const CM = 28.3465;
    public const MM = 2.83465;
    public const PX = 0.75;

    /**
     * Marges effectivement appliquées : le @page du layout master est déclaré
     * après celui du template et l'emporte donc (8mm / 1.5cm / 1cm).
     */
    public const MARGE_HAUT = 8 * self::MM;
    public const MARGE_BAS = 1 * self::CM;
    public const MARGE_COTE = 1.5 * self::CM;

    public const LARGEUR_CONTENU = self::PAGE_L - 2 * self::MARGE_COTE;

    /**
     * Bas de la zone où le flux doit rester visible. Les mentions d'impression
     * sont du flux normal en fin de document : plus rien ne peint par-dessus le
     * texte, la marge basse de 1cm sert seule de limite.
     */
    public const FLUX_BAS = self::PAGE_H - self::MARGE_BAS - 4;

    /**
     * Format paysage A4 (états de régie) : les marges sont celles du @page du
     * template, ce document ne passe pas par le layout master.
     */
    public const MARGE_BAS_PAYSAGE   = 1.4 * self::CM;
    public const MARGE_COTE_PAYSAGE  = 1.2 * self::CM;
    public const LARGEUR_CONTENU_PAYSAGE = self::PAGE_H - 2 * self::MARGE_COTE_PAYSAGE;
    public const FLUX_BAS_PAYSAGE = self::PAGE_L - self::MARGE_BAS_PAYSAGE - 4;

    /** Interligne DomPDF pour DejaVu Sans. */
    public const INTERLIGNE = 1.44;

    private static ?FontMetrics $metrics = null;

    /** @var array<string,int> */
    private static array $lignes = [];

    public static function metrics(): FontMetrics
    {
        if (self::$metrics === null) {
            $options = app()->bound('dompdf.options') ? app('dompdf.options') : new Options();
            self::$metrics = (new Dompdf($options))->getFontMetrics();
        }

        return self::$metrics;
    }

    /** Largeur d'un texte en points. */
    public static function largeurTexte(string $texte, float $taille, bool $gras = false): float
    {
        $police = self::metrics()->get_font('DejaVu Sans', $gras ? 'bold' : 'normal');

        return self::metrics()->get_text_width($texte, $police, $taille);
    }

    /**
     * Nombre de lignes qu'un texte occupe dans une colonne de $largeur points.
     */
    public static function nbLignes(string $texte, float $largeur, float $taille, bool $gras = false): int
    {
        $texte = trim(preg_replace('/\s+/u', ' ', $texte) ?? '');
        if ($texte === '' || $largeur <= 0) {
            return 1;
        }

        $cle = md5($texte . '|' . round($largeur, 2) . '|' . $taille . '|' . ($gras ? 'b' : 'n'));
        if (isset(self::$lignes[$cle])) {
            return self::$lignes[$cle];
        }

        $total = 0;
        $courant = '';
        foreach (explode(' ', $texte) as $mot) {
            $essai = $courant === '' ? $mot : $courant . ' ' . $mot;
            if (self::largeurTexte($essai, $taille, $gras) <= $largeur) {
                $courant = $essai;
                continue;
            }

            if ($courant !== '') {
                $total++;
                $courant = '';
            }

            // Mot seul plus large que la colonne : DomPDF le casse caractère par caractère.
            $largeurMot = self::largeurTexte($mot, $taille, $gras);
            if ($largeurMot > $largeur) {
                $total += (int) ceil($largeurMot / $largeur);
            } else {
                $courant = $mot;
            }
        }

        return self::$lignes[$cle] = max(1, $total + ($courant === '' ? 0 : 1));
    }

    /**
     * Hauteur d'une ligne de tableau : padding haut + bas, bord, et une
     * interligne par ligne de texte.
     */
    public static function hauteurLigne(int $nbLignes, float $taillePolice, float $paddingPx): float
    {
        return 2 * $paddingPx * self::PX + 1.5 + $nbLignes * $taillePolice * self::INTERLIGNE;
    }

    /** Hauteur d'un bloc de texte de $nbLignes lignes. */
    public static function hauteurBloc(int $nbLignes, float $taillePolice): float
    {
        return $nbLignes * $taillePolice * self::INTERLIGNE;
    }

    /** Place encore visible sur une page une fois $dejaPris points consommés. */
    public static function place(float $dejaPris): float
    {
        return max(0.0, self::FLUX_BAS - self::MARGE_HAUT - $dejaPris);
    }

    /** Même calcul en paysage A4. */
    public static function placePaysage(float $dejaPris): float
    {
        return max(0.0, self::FLUX_BAS_PAYSAGE - self::MARGE_HAUT - $dejaPris);
    }

    /**
     * Répartition des lignes d'un tableau sur les pages.
     *
     * Le bloc qui suit le tableau (récapitulatif, montant en lettres,
     * signatures) est ajouté comme une ligne virtuelle en dernière position :
     * il ne peut donc jamais être séparé de la page qui doit l'accueillir, et
     * les lignes de tableau en trop sont poussées sur la page suivante.
     *
     * @param float[] $hauteurs hauteur de chaque ligne du tableau, en points
     * @return int[] nombre de lignes du tableau par page
     */
    public static function lignesParPage(array $hauteurs, float $capacitePremiere, float $capaciteAutres, float $reserve): array
    {
        $lignes = array_values($hauteurs);
        if ($reserve > 0) {
            $lignes[] = $reserve;
        }

        $repartition = [];
        $courante = 0;
        $utilise = 0.0;
        $capacite = max($capacitePremiere, 0.0);

        foreach ($lignes as $hauteur) {
            if ($courante > 0 && $utilise + $hauteur > $capacite) {
                $repartition[] = $courante;
                $courante = 0;
                $utilise = 0.0;
                $capacite = max($capaciteAutres, 0.0);
            }
            $courante++;
            $utilise += $hauteur;
        }
        $repartition[] = $courante;

        if ($reserve > 0) {
            $repartition[count($repartition) - 1]--;
        }

        // Une page sans ligne de tableau n'existe que pour le bloc de fin.
        return $repartition;
    }
}
