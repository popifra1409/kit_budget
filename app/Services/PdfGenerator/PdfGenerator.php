<?php

namespace App\Services\PdfGenerator;

use App\Models\EtatConfig;
use App\Helpers\NombreEnLettres;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

class PdfGenerator
{
    // =========================================================
    // RÉSOLUTION DE LA CONFIG
    // =========================================================

    /**
     * Résoudre la config depuis un code ou un type_document.
     *
     * - Si $codeOuType correspond à un `code` exact → utiliser cette variante
     * - Sinon chercher dans `type_document` → utiliser la variante par défaut
     *
     * @param string      $codeOuType  Code exact OU type_document
     * @param string|null $codeVariante  Code spécifique d'une variante (optionnel)
     */
    protected function resoudreConfig(string $codeOuType, ?string $codeVariante = null): EtatConfig
    {
        // ✅ 1. Variante explicitement demandée
        if ($codeVariante) {
            return EtatConfig::where('code', $codeVariante)
                ->where('actif', true)
                ->firstOrFail();
        }

        // ✅ 2. Code exact existant
        $parCode = EtatConfig::where('code', $codeOuType)->where('actif', true)->first();
        if ($parCode) {
            return $parCode;
        }

        // ✅ 3. type_document → variante par défaut
        $parType = EtatConfig::where('type_document', $codeOuType)
            ->where('actif', true)
            ->where('est_defaut', true)
            ->first();

        if ($parType) {
            return $parType;
        }

        // ✅ 4. type_document → première variante disponible
        $premiere = EtatConfig::where('type_document', $codeOuType)
            ->where('actif', true)
            ->orderBy('ordre')
            ->first();

        if ($premiere) {
            return $premiere;
        }

        abort(404, "Aucun état trouvé pour : {$codeOuType}");
    }

    // =========================================================
    // GÉNÉRATION
    // =========================================================

    /**
     * Générer un PDF depuis un code ou type_document.
     *
     * @param string      $codeOuType   Code exact OU type_document
     * @param mixed       $donnees      Données source
     * @param array       $options      Options PDF + variante
     *                                  ['variante' => 'op_detaillee', 'pdf_options' => [...]]
     */
    public function generer(string $codeOuType, $donnees, array $options = [])
    {
        $config = $this->resoudreConfig(
            $codeOuType,
            $options['variante'] ?? null
        );

        $donneesPrepares = $this->preparerDonnees($donnees, $config);

        $donneesVue = [
            'config' => $config,
            'donnees' => $donneesPrepares,
            'options' => $options,
        ];

        $html = View::make($config->template, $donneesVue)->render();

        $pdf = Pdf::loadHTML($html);

        $orientation = $options['pdf_options']['orientation']
            ?? $config->options_pdf['orientation']
            ?? 'portrait';

        $pdf->setPaper('a4', strtolower($orientation));

        return $pdf;
    }

    /**
     * Retourner toutes les variantes disponibles pour un type_document.
     * Utile pour peupler un Select Filament.
     */
    public function variantesPour(string $typeDocument): array
    {
        return EtatConfig::where('type_document', $typeDocument)
            ->where('actif', true)
            ->orderBy('est_defaut', 'desc')
            ->orderBy('ordre')
            ->get()
            ->mapWithKeys(fn($e) => [
                $e->code => $e->nom . ($e->est_defaut ? ' ⭐' : '')
            ])
            ->toArray();
    }

    // =========================================================
    // PRÉPARATION DES DONNÉES (inchangée)
    // =========================================================

    protected function preparerDonnees($donnees, EtatConfig $config): array
    {
        $donneesPrepares = [];
        $donneesOriginales = $donnees;
        $donneesArray = is_object($donnees) && method_exists($donnees, 'toArray')
            ? $donnees->toArray()
            : $donnees;

        foreach ($config->champs_variables ?? [] as $nom => $configChamp) {
            $source = $configChamp['source'] ?? $nom;
            $valeur = data_get($donneesArray, $source);
            $donneesPrepares[$nom] = $this->formaterValeur(
                $valeur,
                $configChamp['type'] ?? 'text',
                $configChamp
            );
        }

        foreach ($config->calculs ?? [] as $nom => $configCalcul) {
            $donneesPrepares[$nom] = $this->executerCalcul(
                $configCalcul,
                $donneesPrepares,
                $donneesArray
            );
        }

        $donneesPrepares['_raw'] = $donneesOriginales;

        return $donneesPrepares;
    }

    // =========================================================
    // FORMATAGE (inchangé)
    // =========================================================

    protected function formaterValeur($valeur, string $type, array $config = [])
    {
        if ($valeur === null) {
            return $config['default'] ?? '';
        }

        switch ($type) {
            case 'money':
            case 'montant':
                $format = $config['format'] ?? 'fr';
                if ($format === 'fr') {
                    return number_format($valeur, 0, ',', ' ') . ' F cfa';
                }
                return number_format($valeur, $config['decimals'] ?? 0);

            case 'date':
                if ($valeur instanceof \DateTime || $valeur instanceof \Carbon\Carbon) {
                    return $valeur->format($config['format'] ?? 'd/m/Y');
                }
                try {
                    return \Carbon\Carbon::parse($valeur)->format($config['format'] ?? 'd/m/Y');
                } catch (\Exception $e) {
                    return $valeur;
                }

            case 'datetime':
                if ($valeur instanceof \DateTime || $valeur instanceof \Carbon\Carbon) {
                    return $valeur->format($config['format'] ?? 'd/m/Y H:i');
                }
                try {
                    return \Carbon\Carbon::parse($valeur)->format($config['format'] ?? 'd/m/Y H:i');
                } catch (\Exception $e) {
                    return $valeur;
                }

            case 'number':
                return number_format(
                    $valeur,
                    $config['decimals'] ?? 0,
                    $config['decimal_separator'] ?? ',',
                    $config['thousands_separator'] ?? ' '
                );

            case 'uppercase':
                return mb_strtoupper($valeur);
            case 'lowercase':
                return mb_strtolower($valeur);
            case 'capitalize':
                return mb_convert_case($valeur, MB_CASE_TITLE);
            case 'boolean':
                return $valeur ? ($config['true_text'] ?? 'Oui') : ($config['false_text'] ?? 'Non');
            default:
                return $valeur;
        }
    }

    // =========================================================
    // CALCULS (inchangés)
    // =========================================================

    protected function executerCalcul(array $configCalcul, array $donneesPrepares, array $donneesRaw)
    {
        $fonction = $configCalcul['fonction'] ?? null;
        if (!$fonction)
            return null;

        $params = [];
        foreach ($configCalcul['params'] ?? [] as $param) {
            if (str_starts_with($param, '_raw.')) {
                $params[] = data_get($donneesRaw, substr($param, 5));
            } else {
                $params[] = $donneesPrepares[$param] ?? null;
            }
        }

        $methodName = 'calcul_' . $fonction;
        if (method_exists($this, $methodName)) {
            return $this->$methodName(...$params);
        }
        return null;
    }

    protected function calcul_nombre_en_lettres($montant): string
    {
        return NombreEnLettres::montantCFA($montant);
    }
    protected function calcul_somme(...$valeurs): float
    {
        return array_sum(array_filter($valeurs, 'is_numeric'));
    }
    protected function calcul_difference($a, $b): float
    {
        return floatval($a) - floatval($b);
    }
    protected function calcul_produit(...$valeurs): float
    {
        $r = 1;
        foreach (array_filter($valeurs, 'is_numeric') as $v) {
            $r *= floatval($v);
        }
        return $r;
    }
    protected function calcul_pourcentage($valeur, $total): float
    {
        return $total == 0 ? 0 : ($valeur / $total) * 100;
    }
    protected function calcul_tva($ht, $taux = 19.25): float
    {
        return $ht * ($taux / 100);
    }
    protected function calcul_ttc($ht, $taux = 19.25): float
    {
        return $ht + $this->calcul_tva($ht, $taux);
    }

    // =========================================================
    // ACTIONS (télécharger / afficher / sauvegarder)
    // =========================================================

    public function sauvegarder(string $codeOuType, $donnees, string $nomFichier, string $disk = 'public', array $options = []): string
    {
        $chemin = 'pdf/' . $nomFichier;
        Storage::disk($disk)->put($chemin, $this->generer($codeOuType, $donnees, $options)->output());
        return $chemin;
    }

    public function telecharger(string $codeOuType, $donnees, string $nomFichier = null, array $options = [])
    {
        $pdf = $this->generer($codeOuType, $donnees, $options);
        $nomFichier ??= $this->nomFichierDefaut($codeOuType, $options);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $nomFichier . '"');
    }

    public function afficher(string $codeOuType, $donnees, string $nomFichier = null, array $options = [])
    {
        $pdf = $this->generer($codeOuType, $donnees, $options);
        $nomFichier ??= $this->nomFichierDefaut($codeOuType, $options);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $nomFichier . '"');
    }

    public function obtenirHtml(string $codeOuType, $donnees, array $options = []): string
    {
        $config = $this->resoudreConfig($codeOuType, $options['variante'] ?? null);
        $donneesPrepares = $this->preparerDonnees($donnees, $config);
        return View::make($config->template, ['config' => $config, 'donnees' => $donneesPrepares, 'options' => $options])->render();
    }

    public function genererLot(string $codeOuType, array $listeDonnees, string $dossierDestination = 'pdf/lot', array $options = []): array
    {
        $fichiers = [];
        foreach ($listeDonnees as $index => $donnees) {
            $nomFichier = $dossierDestination . '/' . $codeOuType . '_' . ($index + 1) . '.pdf';
            $fichiers[] = $this->sauvegarder($codeOuType, $donnees, $nomFichier, 'public', $options);
        }
        return $fichiers;
    }

    private function nomFichierDefaut(string $codeOuType, array $options = []): string
    {
        $config = EtatConfig::where('code', $options['variante'] ?? $codeOuType)->first()
            ?? EtatConfig::where('type_document', $codeOuType)->where('est_defaut', true)->first();
        return str_replace(' ', '-', strtolower($config->nom ?? $codeOuType)) . '.pdf';
    }
}