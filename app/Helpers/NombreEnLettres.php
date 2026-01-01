<?php

namespace App\Helpers;

class NombreEnLettres
{
    private static $unites = [
        0 => '',
        1 => 'un',
        2 => 'deux',
        3 => 'trois',
        4 => 'quatre',
        5 => 'cinq',
        6 => 'six',
        7 => 'sept',
        8 => 'huit',
        9 => 'neuf',
        10 => 'dix',
        11 => 'onze',
        12 => 'douze',
        13 => 'treize',
        14 => 'quatorze',
        15 => 'quinze',
        16 => 'seize',
        17 => 'dix-sept',
        18 => 'dix-huit',
        19 => 'dix-neuf',
    ];

    private static $dizaines = [
        2 => 'vingt',
        3 => 'trente',
        4 => 'quarante',
        5 => 'cinquante',
        6 => 'soixante',
        7 => 'soixante-dix',
        8 => 'quatre-vingt',
        9 => 'quatre-vingt-dix',
    ];

    /**
     * Convertir un nombre en lettres (français)
     */
    public static function convertir($nombre, bool $majuscule = false, string $devise = 'F cfa'): string
    {
        if (!is_numeric($nombre)) {
            return '';
        }

        $nombre = floatval($nombre);
        $entier = floor($nombre);
        $decimal = round(($nombre - $entier) * 100);

        $resultat = self::convertirEntier($entier);

        if ($decimal > 0) {
            $resultat .= ' virgule ' . self::convertirEntier($decimal);
        }

        if (!empty($devise)) {
            $resultat .= ' ' . $devise;
        }

        if ($majuscule) {
            $resultat = ucfirst($resultat);
        }

        return trim($resultat);
    }

    /**
     * Convertir un nombre entier en lettres
     */
    private static function convertirEntier(int $nombre): string
    {
        if ($nombre == 0) {
            return 'zéro';
        }

        if ($nombre < 0) {
            return 'moins ' . self::convertirEntier(-$nombre);
        }

        $resultat = '';

        // Milliards
        if ($nombre >= 1000000000) {
            $milliards = floor($nombre / 1000000000);
            $resultat .= self::convertirEntier($milliards) . ' milliard';
            if ($milliards > 1) {
                $resultat .= 's';
            }
            $nombre %= 1000000000;
            if ($nombre > 0) {
                $resultat .= ' ';
            }
        }

        // Millions
        if ($nombre >= 1000000) {
            $millions = floor($nombre / 1000000);
            $resultat .= self::convertirEntier($millions) . ' million';
            if ($millions > 1) {
                $resultat .= 's';
            }
            $nombre %= 1000000;
            if ($nombre > 0) {
                $resultat .= ' ';
            }
        }

        // Milliers
        if ($nombre >= 1000) {
            $milliers = floor($nombre / 1000);
            if ($milliers == 1) {
                $resultat .= 'mille';
            } else {
                $resultat .= self::convertirEntier($milliers) . ' mille';
            }
            $nombre %= 1000;
            if ($nombre > 0) {
                $resultat .= ' ';
            }
        }

        // Centaines
        if ($nombre >= 100) {
            $centaines = floor($nombre / 100);
            if ($centaines == 1) {
                $resultat .= 'cent';
            } else {
                $resultat .= self::$unites[$centaines] . ' cent';
            }
            $nombre %= 100;
            if ($nombre > 0) {
                $resultat .= ' ';
            } elseif ($centaines > 1) {
                $resultat .= 's';
            }
        }

        // Dizaines et unités
        if ($nombre >= 20) {
            $dizaine = floor($nombre / 10);
            $unite = $nombre % 10;

            $resultat .= self::$dizaines[$dizaine];

            if ($unite == 1 && $dizaine != 8) {
                $resultat .= ' et un';
            } elseif ($unite > 1) {
                $resultat .= '-' . self::$unites[$unite];
            } elseif ($dizaine == 8 && $unite == 0) {
                $resultat .= 's';
            }
        } elseif ($nombre > 0) {
            $resultat .= self::$unites[$nombre];
        }

        return $resultat;
    }

    /**
     * Convertir un montant avec devise CFA
     */
    public static function montantCFA($montant): string
    {
        return self::convertir($montant, true, 'F cfa');
    }

    /**
     * Convertir un montant sans devise
     */
    public static function nombreSeul($nombre): string
    {
        return self::convertir($nombre, false, '');
    }
}
