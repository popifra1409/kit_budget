<?php

namespace App\Services\PdfGenerator;

use App\Models\EtatConfig;
use App\Helpers\NombreEnLettres;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

class PdfGenerator
{
    /**
     * Générer un PDF depuis une configuration
     */
    public function generer(string $codeEtat, $donnees, array $options = [])
    {
        $config = EtatConfig::where('code', $codeEtat)
            ->where('actif', true)
            ->firstOrFail();

        $donneesPrepares = $this->preparerDonnees($donnees, $config);

        $donneesVue = [
            'config' => $config,
            'donnees' => $donneesPrepares,
            'options' => $options,
        ];

        $html = View::make($config->template, $donneesVue)->render();

        // Créer le PDF avec DomPDF
        $pdf = Pdf::loadHTML($html);

        // Orientation
        $orientation = $options['pdf_options']['orientation'] ??
            $config->options_pdf['orientation'] ?? 'portrait';
        $pdf->setPaper('a4', strtolower($orientation));

        return $pdf;
    }

    /**
     * Préparer les données selon la configuration
     */
    protected function preparerDonnees($donnees, EtatConfig $config): array
    {
        $donneesPrepares = [];

        if (is_object($donnees) && method_exists($donnees, 'toArray')) {
            $donnees = $donnees->toArray();
        }

        foreach ($config->champs_variables ?? [] as $nom => $configChamp) {
            $source = $configChamp['source'] ?? $nom;
            $valeur = data_get($donnees, $source);

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
                $donnees
            );
        }

        $donneesPrepares['_raw'] = $donnees;

        return $donneesPrepares;
    }

    /**
     * Formater une valeur selon son type
     */
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
                    $date = \Carbon\Carbon::parse($valeur);
                    return $date->format($config['format'] ?? 'd/m/Y');
                } catch (\Exception $e) {
                    return $valeur;
                }

            case 'datetime':
                if ($valeur instanceof \DateTime || $valeur instanceof \Carbon\Carbon) {
                    return $valeur->format($config['format'] ?? 'd/m/Y H:i');
                }
                try {
                    $date = \Carbon\Carbon::parse($valeur);
                    return $date->format($config['format'] ?? 'd/m/Y H:i');
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

            case 'text':
            default:
                return $valeur;
        }
    }

    /**
     * Exécuter un calcul
     */
    protected function executerCalcul(array $configCalcul, array $donneesPrepares, array $donneesRaw)
    {
        $fonction = $configCalcul['fonction'] ?? null;

        if (!$fonction) {
            return null;
        }

        $params = [];
        foreach ($configCalcul['params'] ?? [] as $param) {
            if (str_starts_with($param, '_raw.')) {
                $key = substr($param, 5);
                $params[] = data_get($donneesRaw, $key);
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

    /**
     * Calcul : Convertir un nombre en lettres
     */
    protected function calcul_nombre_en_lettres($montant): string
    {
        return NombreEnLettres::montantCFA($montant);
    }

    /**
     * Calcul : Somme de plusieurs valeurs
     */
    protected function calcul_somme(...$valeurs): float
    {
        return array_sum(array_filter($valeurs, 'is_numeric'));
    }

    /**
     * Calcul : Différence entre deux valeurs
     */
    protected function calcul_difference($a, $b): float
    {
        return floatval($a) - floatval($b);
    }

    /**
     * Calcul : Produit de plusieurs valeurs
     */
    protected function calcul_produit(...$valeurs): float
    {
        $result = 1;
        foreach (array_filter($valeurs, 'is_numeric') as $valeur) {
            $result *= floatval($valeur);
        }
        return $result;
    }

    /**
     * Calcul : Pourcentage
     */
    protected function calcul_pourcentage($valeur, $total): float
    {
        if ($total == 0) {
            return 0;
        }
        return ($valeur / $total) * 100;
    }

    /**
     * Calcul : TVA
     */
    protected function calcul_tva($montantHT, $tauxTVA = 19.25): float
    {
        return $montantHT * ($tauxTVA / 100);
    }

    /**
     * Calcul : Montant TTC
     */
    protected function calcul_ttc($montantHT, $tauxTVA = 19.25): float
    {
        return $montantHT + $this->calcul_tva($montantHT, $tauxTVA);
    }

    /**
     * Sauvegarder le PDF dans le storage
     */
    public function sauvegarder(string $codeEtat, $donnees, string $nomFichier, string $disk = 'public'): string
    {
        $pdf = $this->generer($codeEtat, $donnees);
        $contenu = $pdf->output();

        $chemin = 'pdf/' . $nomFichier;
        Storage::disk($disk)->put($chemin, $contenu);

        return $chemin;
    }

    /**
     * Télécharger le PDF
     */
    public function telecharger(string $codeEtat, $donnees, string $nomFichier = null)
    {
        $pdf = $this->generer($codeEtat, $donnees);

        if (!$nomFichier) {
            $config = EtatConfig::where('code', $codeEtat)->first();
            $nomFichier = str_replace(' ', '-', strtolower($config->nom ?? $codeEtat)) . '.pdf';
        }

        // Retourner une réponse HTTP de téléchargement avec les bons headers
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $nomFichier . '"');
    }

    /**
     * Afficher le PDF dans le navigateur (inline)
     */
    public function afficher(string $codeEtat, $donnees, string $nomFichier = null)
    {
        $pdf = $this->generer($codeEtat, $donnees);

        if (!$nomFichier) {
            $config = EtatConfig::where('code', $codeEtat)->first();
            $nomFichier = str_replace(' ', '-', strtolower($config->nom ?? $codeEtat)) . '.pdf';
        }

        // Retourner une réponse HTTP correcte avec les bons headers
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $nomFichier . '"');
    }

    /**
     * Obtenir le contenu HTML (pour debug)
     */
    public function obtenirHtml(string $codeEtat, $donnees): string
    {
        $config = EtatConfig::where('code', $codeEtat)
            ->where('actif', true)
            ->firstOrFail();

        $donneesPrepares = $this->preparerDonnees($donnees, $config);

        return View::make($config->template, [
            'config' => $config,
            'donnees' => $donneesPrepares,
            'options' => [],
        ])->render();
    }

    /**
     * Générer plusieurs PDFs en lot
     */
    public function genererLot(string $codeEtat, array $listeDonnees, string $dossierDestination = 'pdf/lot'): array
    {
        $fichiers = [];

        foreach ($listeDonnees as $index => $donnees) {
            $nomFichier = $dossierDestination . '/' . $codeEtat . '_' . ($index + 1) . '.pdf';
            $chemin = $this->sauvegarder($codeEtat, $donnees, $nomFichier);
            $fichiers[] = $chemin;
        }

        return $fichiers;
    }
}
