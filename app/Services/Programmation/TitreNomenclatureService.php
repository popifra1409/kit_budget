<?php
// app/Services/Programmation/TitreNomenclatureService.php

namespace App\Services\Programmation;

use App\Models\CbmtLigne;

/**
 * Titre CBMT d'une ligne de nomenclature : celui saisi sur la ligne s'il existe,
 * sinon celui proposé par les règles de config/cbmt.php (préfixe de code le plus long).
 */
class TitreNomenclatureService
{
    /** Titre proposé d'après le code et le type (depense / recette), ou null si aucune règle. */
    public function titrePropose(?string $code, ?string $type): ?int
    {
        $regles = config("cbmt.regles.{$type}", []);
        $code = trim((string) $code);

        if ($code === '' || empty($regles)) {
            return null;
        }

        for ($longueur = min(strlen($code), 6); $longueur >= 1; $longueur--) {
            $prefixe = substr($code, 0, $longueur);

            if (array_key_exists($prefixe, $regles)) {
                return (int) $regles[$prefixe];
            }
        }

        return null;
    }

    /** Titre retenu pour une nomenclature : saisi, sinon proposé. */
    public function titreDe(?object $nomenclature): ?int
    {
        if (!$nomenclature) {
            return null;
        }

        return $nomenclature->titre !== null
            ? (int) $nomenclature->titre
            : $this->titrePropose($nomenclature->code ?? null, $nomenclature->type ?? null);
    }

    /** Options du sélecteur de titre selon le type de la nomenclature. */
    public static function options(?string $type): array
    {
        $titres = $type === 'recette' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        return collect($titres)->mapWithKeys(fn($libelle, $numero) => [$numero => "Titre {$numero} — {$libelle}"])->all();
    }

    public static function libelle(?string $type, ?int $titre): ?string
    {
        if ($titre === null) {
            return null;
        }

        $titres = $type === 'recette' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        return isset($titres[$titre]) ? "Titre {$titre} — {$titres[$titre]}" : "Titre {$titre}";
    }
}
