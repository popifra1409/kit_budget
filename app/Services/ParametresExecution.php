<?php

namespace App\Services;

use App\Models\ParametreExecution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lecture et enregistrement des paramètres d'exécution budgétaire.
 *
 *   ParametresExecution::get('delai_paiement_jours')                 → valeur en vigueur aujourd'hui
 *   ParametresExecution::get('delai_paiement_jours', $dateLiquidation) → valeur en vigueur à cette date
 *
 * Valeur applicable à une date = la plus récente dont la date d'effet est ≤ cette date ;
 * à défaut, la valeur par défaut de config/execution.php. Jamais d'exception à la lecture :
 * si la table n'existe pas encore (migration non lancée), la valeur par défaut est renvoyée.
 */
class ParametresExecution
{
    protected static array $cache = [];
    protected static ?bool $tableExiste = null;

    public static function definitions(): array
    {
        return config('execution.parametres', []);
    }

    public static function get(string $cle, $date = null): mixed
    {
        $def = static::definitions()[$cle] ?? null;

        if ($def === null) {
            throw new \InvalidArgumentException("Paramètre d'exécution inconnu : {$cle}");
        }

        $jour = Carbon::parse($date ?? now())->toDateString();
        $cleCache = "{$cle}|{$jour}";

        if (array_key_exists($cleCache, static::$cache)) {
            return static::$cache[$cleCache];
        }

        $valeur = static::tableExiste()
            ? ParametreExecution::where('cle', $cle)
            ->whereDate('date_effet', '<=', $jour)
            ->orderByDesc('date_effet')->orderByDesc('id')
            ->value('valeur')
            : null;

        return static::$cache[$cleCache] = static::convertir($valeur ?? $def['defaut'], $def['type']);
    }

    /** Toutes les valeurs en vigueur à une date (par défaut aujourd'hui). */
    public static function valeurs($date = null): array
    {
        return collect(static::definitions())
            ->mapWithKeys(fn($def, $cle) => [$cle => static::get($cle, $date)])
            ->all();
    }

    /**
     * Enregistre les valeurs qui CHANGENT par rapport à celles en vigueur à la date d'effet.
     * Renvoie la liste des paramètres modifiés [cle => [avant, apres]].
     */
    public static function enregistrer(array $valeurs, $dateEffet, ?string $motif = null): array
    {
        $date = Carbon::parse($dateEffet)->toDateString();
        $modifies = [];

        DB::transaction(function () use ($valeurs, $date, $motif, &$modifies) {
            foreach (static::definitions() as $cle => $def) {
                if (!array_key_exists($cle, $valeurs)) {
                    continue;
                }

                $avant = static::get($cle, $date);
                $apres = static::convertir($valeurs[$cle], $def['type']);

                if ($avant === $apres) {
                    continue;
                }

                ParametreExecution::create([
                    'cle'        => $cle,
                    'valeur'     => is_bool($apres) ? ($apres ? '1' : '0') : (string) $apres,
                    'date_effet' => $date,
                    'motif'      => $motif,
                ]);

                $modifies[$cle] = [$avant, $apres];
            }
        });

        static::$cache = [];

        return $modifies;
    }

    /** Libellé lisible d'une valeur (pour l'historique et les messages). */
    public static function libelleValeur(string $cle, mixed $valeur): string
    {
        $def = static::definitions()[$cle] ?? [];

        return match ($def['type'] ?? null) {
            'booleen' => static::convertir($valeur, 'booleen') ? 'Oui' : 'Non',
            'choix'   => $def['options'][$valeur] ?? (string) $valeur,
            default   => trim($valeur . ' ' . ($def['unite'] ?? '')),
        };
    }

    public static function convertir(mixed $valeur, string $type): mixed
    {
        return match ($type) {
            'entier'  => (int) $valeur,
            'decimal' => round((float) $valeur, 4),
            'booleen' => filter_var($valeur, FILTER_VALIDATE_BOOLEAN),
            default   => (string) $valeur,
        };
    }

    protected static function tableExiste(): bool
    {
        return static::$tableExiste ??= Schema::hasTable('parametres_execution');
    }
}
