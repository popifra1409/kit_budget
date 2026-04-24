<?php

namespace App\Helpers;

class NombreEnLettres
{
    private static array $unites = [
        0  => '',
        1  => 'un',
        2  => 'deux',
        3  => 'trois',
        4  => 'quatre',
        5  => 'cinq',
        6  => 'six',
        7  => 'sept',
        8  => 'huit',
        9  => 'neuf',
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

    private static array $dizaines = [
        2 => 'vingt',
        3 => 'trente',
        4 => 'quarante',
        5 => 'cinquante',
        6 => 'soixante',
        7 => 'soixante',   // base 60 + 10..19
        8 => 'quatre-vingt',
        9 => 'quatre-vingt', // base 80 + 10..19
    ];

    /**
     * Convertir un nombre en lettres (français)
     */
    public static function convertir($nombre, bool $majuscule = false, string $devise = 'Fcfa'): string
    {
        if (!is_numeric($nombre)) {
            return '';
        }

        // ✅ Conversion sécurisée : éviter les problèmes de float pour grands nombres
        $nombre  = (int) round((float) $nombre);
        $entier  = abs($nombre);
        $negatif = $nombre < 0;

        if ($entier === 0) {
            $resultat = 'zéro';
        } else {
            $resultat = ($negatif ? 'moins ' : '') . self::convertirEntier($entier);
        }

        if (!empty($devise)) {
            $resultat .= ' ' . $devise;
        }

        return $majuscule ? ucfirst(trim($resultat)) : trim($resultat);
    }

    /**
     * Convertir un entier positif en lettres
     */
    private static function convertirEntier(int $nombre): string
    {
        if ($nombre === 0) return '';

        $resultat = '';

        // ── Milliards ─────────────────────────────────────────
        if ($nombre >= 1_000_000_000) {
            $n = intdiv($nombre, 1_000_000_000);
            $resultat .= self::convertirEntier($n) . ' milliard' . ($n > 1 ? 's' : '');
            $nombre   %= 1_000_000_000;
            if ($nombre > 0) $resultat .= ' ';
        }

        // ── Millions ──────────────────────────────────────────
        if ($nombre >= 1_000_000) {
            $n = intdiv($nombre, 1_000_000);
            $resultat .= self::convertirEntier($n) . ' million' . ($n > 1 ? 's' : '');
            $nombre   %= 1_000_000;
            if ($nombre > 0) $resultat .= ' ';
        }

        // ── Milliers ──────────────────────────────────────────
        if ($nombre >= 1_000) {
            $n = intdiv($nombre, 1_000);
            $resultat .= ($n === 1 ? 'mille' : self::convertirEntier($n) . ' mille');
            $nombre   %= 1_000;
            if ($nombre > 0) $resultat .= ' ';
        }

        // ── Centaines ─────────────────────────────────────────
        if ($nombre >= 100) {
            $n = intdiv($nombre, 100);
            if ($n === 1) {
                $resultat .= 'cent';
            } else {
                $resultat .= self::$unites[$n] . ' cent';
            }
            $nombre %= 100;
            if ($nombre === 0 && $n > 1) {
                $resultat .= 's'; // deux cents (pluriel si exact)
            } elseif ($nombre > 0) {
                $resultat .= ' ';
            }
        }

        // ── Dizaines et unités (0-99) ─────────────────────────
        if ($nombre >= 20) {
            $dizaine = intdiv($nombre, 10);
            $unite   = $nombre % 10;

            if ($dizaine === 7) {
                // 70-79 : soixante + dix..dix-neuf
                $resultat .= 'soixante-' . self::convertirUnite(10 + $unite);
            } elseif ($dizaine === 8) {
                // 80 : quatre-vingts / 81-89 : quatre-vingt-X
                $resultat .= 'quatre-vingt';
                if ($unite > 0) {
                    $resultat .= '-' . self::$unites[$unite];
                } else {
                    $resultat .= 's'; // quatre-vingts (pluriel si exact)
                }
            } elseif ($dizaine === 9) {
                // 90-99 : quatre-vingt + dix..dix-neuf
                $resultat .= 'quatre-vingt-' . self::convertirUnite(10 + $unite);
            } else {
                // 20-69 classique
                $resultat .= self::$dizaines[$dizaine];
                if ($unite === 1 && $dizaine !== 8) {
                    $resultat .= ' et un';
                } elseif ($unite > 0) {
                    $resultat .= '-' . self::$unites[$unite];
                }
            }
        } elseif ($nombre > 0) {
            // 1-19
            $resultat .= self::convertirUnite($nombre);
        }

        return $resultat;
    }

    /**
     * Convertir une unité (1-19)
     */
    private static function convertirUnite(int $n): string
    {
        return self::$unites[$n] ?? '';
    }

    /**
     * Convertir un montant avec devise CFA
     */
    public static function montantCFA($montant): string
    {
        return self::convertir($montant, true, 'Francs CFA');
    }

    /**
     * Convertir un montant sans devise
     */
    public static function nombreSeul($nombre): string
    {
        return self::convertir($nombre, false, '');
    }
}
