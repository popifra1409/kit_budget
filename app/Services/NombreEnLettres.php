<?php

namespace App\Services;

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
        19 => 'dix-neuf'
    ];

    private static $dizaines = [
        2 => 'vingt',
        3 => 'trente',
        4 => 'quarante',
        5 => 'cinquante',
        6 => 'soixante',
        7 => 'soixante',
        8 => 'quatre-vingt',
        9 => 'quatre-vingt'
    ];

    /**
     * Convertir un nombre en lettres (français)
     * 
     * @param float $nombre Le nombre à convertir
     * @param string $devise La devise (FCFA par défaut)
     * @return string Le nombre en lettres
     */
    public static function convertir(float $nombre, string $devise = 'FRANCS CFA'): string
    {
        // Séparer partie entière et décimale
        $partieEntiere = floor($nombre);

        if ($partieEntiere == 0) {
            return 'ZERO ' . $devise;
        }

        $lettres = self::convertirNombre($partieEntiere);

        // Mettre en majuscules et ajouter la devise
        return strtoupper($lettres) . ' ' . $devise;
    }

    /**
     * Convertir un nombre entier en lettres
     */
    private static function convertirNombre(int $nombre): string
    {
        if ($nombre == 0) {
            return '';
        }

        if ($nombre < 20) {
            return self::$unites[$nombre];
        }

        if ($nombre < 100) {
            return self::convertirDizaines($nombre);
        }

        if ($nombre < 1000) {
            return self::convertirCentaines($nombre);
        }

        if ($nombre < 1000000) {
            return self::convertirMilliers($nombre);
        }

        if ($nombre < 1000000000) {
            return self::convertirMillions($nombre);
        }

        return self::convertirMilliards($nombre);
    }

    /**
     * Convertir les dizaines (20-99)
     */
    private static function convertirDizaines(int $nombre): string
    {
        $dizaine = floor($nombre / 10);
        $unite = $nombre % 10;

        $resultat = self::$dizaines[$dizaine];

        if ($dizaine == 7 || $dizaine == 9) {
            // Soixante-dix, quatre-vingt-dix
            $resultat .= '-' . self::$unites[10 + $unite];
        } elseif ($unite == 1 && $dizaine != 8) {
            // Vingt et un, trente et un, etc. (mais quatre-vingt-un)
            $resultat .= ' et un';
        } elseif ($unite > 0) {
            $resultat .= '-' . self::$unites[$unite];
        } elseif ($dizaine == 8) {
            // Quatre-vingts (avec s)
            $resultat .= 's';
        }

        return $resultat;
    }

    /**
     * Convertir les centaines (100-999)
     */
    private static function convertirCentaines(int $nombre): string
    {
        $centaine = floor($nombre / 100);
        $reste = $nombre % 100;

        $resultat = '';

        if ($centaine == 1) {
            $resultat = 'cent';
        } else {
            $resultat = self::$unites[$centaine] . ' cent';
        }

        // Ajouter un 's' si centaines multiples et pas de reste
        if ($centaine > 1 && $reste == 0) {
            $resultat .= 's';
        }

        if ($reste > 0) {
            $resultat .= ' ' . self::convertirNombre($reste);
        }

        return $resultat;
    }

    /**
     * Convertir les milliers (1000-999999)
     */
    private static function convertirMilliers(int $nombre): string
    {
        $milliers = floor($nombre / 1000);
        $reste = $nombre % 1000;

        $resultat = '';

        if ($milliers == 1) {
            $resultat = 'mille';
        } else {
            $resultat = self::convertirNombre($milliers) . ' mille';
        }

        if ($reste > 0) {
            $resultat .= ' ' . self::convertirNombre($reste);
        }

        return $resultat;
    }

    /**
     * Convertir les millions (1000000-999999999)
     */
    private static function convertirMillions(int $nombre): string
    {
        $millions = floor($nombre / 1000000);
        $reste = $nombre % 1000000;

        $resultat = self::convertirNombre($millions) . ' million';

        // Ajouter un 's' si plusieurs millions
        if ($millions > 1) {
            $resultat .= 's';
        }

        if ($reste > 0) {
            $resultat .= ' ' . self::convertirNombre($reste);
        }

        return $resultat;
    }

    /**
     * Convertir les milliards
     */
    private static function convertirMilliards(int $nombre): string
    {
        $milliards = floor($nombre / 1000000000);
        $reste = $nombre % 1000000000;

        $resultat = self::convertirNombre($milliards) . ' milliard';

        if ($milliards > 1) {
            $resultat .= 's';
        }

        if ($reste > 0) {
            $resultat .= ' ' . self::convertirNombre($reste);
        }

        return $resultat;
    }

    /**
     * Convertir un montant avec centimes
     */
    public static function convertirMontant(float $montant): string
    {
        $partieEntiere = floor($montant);
        $centimes = round(($montant - $partieEntiere) * 100);

        $lettres = self::convertir($partieEntiere, 'FRANCS CFA');

        if ($centimes > 0) {
            $lettres .= ' ET ' . self::convertirNombre($centimes) . ' CENTIMES';
        }

        return $lettres;
    }
}
