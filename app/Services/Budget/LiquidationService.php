<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\Engagement;
use App\Models\Fournisseur;
use App\Models\Liquidation;
use App\Models\NatureServiceFait;
use App\Models\Personnel;
use App\Models\Reception;
use App\Services\DossierFournisseurService;
use App\Services\ParametresExecution;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Liquidation : du service fait à la dette.
 *
 *  1. Service fait (comptable matières) : preuves obligatoires de la nature fournies.
 *  2. Liquidation (ordonnateur)         : contrôles (rapprochement avec l'engagement, droits du
 *                                         créancier, calculs) ; montant de la dette ARRÊTÉ ;
 *                                         échéance de paiement FIGÉE (délai en vigueur à cette date).
 *  3. Visa de régularité (CF)           : obligatoire ou non selon le paramètre.
 *  Rejet à toute étape : retour en brouillon, avec motif.
 *
 * Tous les seuils viennent des paramètres d'exécution (App\Services\ParametresExecution).
 */
class LiquidationService
{
    // ════════════════════════════════════════════════════════
    // ENGAGEMENT ET MONTANTS
    // ════════════════════════════════════════════════════════

    /** Montant déjà liquidé (ou en cours de liquidation) sur l'engagement. */
    public function montantLiquide(Engagement $engagement, ?int $sauf = null): float
    {
        return (float) Liquidation::where('engagement_id', $engagement->id)
            ->when($sauf, fn($q) => $q->whereKeyNot($sauf))
            ->sum('montant_liquide');
    }

    public function resteALiquider(Engagement $engagement, ?int $sauf = null): float
    {
        return max(0, round((float) $engagement->montant_engage - $this->montantLiquide($engagement, $sauf), 2));
    }

    /** Réceptions conformes (PV signé ou intégrées) du BC de l'engagement. */
    public function receptionsConformes(Engagement $engagement)
    {
        if (!$engagement->estBonCommande() || !$engagement->engageable_id) {
            return collect();
        }

        return Reception::where('bon_commande_id', $engagement->engageable_id)
            ->whereIn('statut', ['pv_signe', 'integre'])
            ->orderByDesc('date_reception')
            ->get();
    }

    // ════════════════════════════════════════════════════════
    // CRÉATION
    // ════════════════════════════════════════════════════════

    /**
     * Crée une liquidation en brouillon, avec la liste des preuves attendues pour la nature.
     * Pour une réception conforme, les preuves « bon de livraison » et « réception » sont pré-renseignées.
     */
    public function creer(Engagement $engagement, array $data): Liquidation
    {
        return DB::transaction(function () use ($engagement, $data) {
            $engagement = Engagement::withoutGlobalScope('exercice')->whereKey($engagement->id)->lockForUpdate()->firstOrFail();

            if ($engagement->statut !== 'definitif') {
                throw new DomainException("Seul un engagement définitif peut être liquidé.");
            }

            $montant = round((float) ($data['montant_liquide'] ?? $this->resteALiquider($engagement)), 2);
            $reste = $this->resteALiquider($engagement);

            if ($montant <= 0 || $montant > $reste + 0.01) {
                throw new DomainException('Montant à liquider invalide : il doit être positif et ne pas dépasser le reste à liquider ('
                    . number_format($reste, 0, ',', ' ') . ' FCFA).');
            }

            $reception = !empty($data['reception_id']) ? Reception::find($data['reception_id']) : null;

            $liquidation = Liquidation::create([
                'exercice_id'            => $engagement->exercice_id,
                'engagement_id'          => $engagement->id,
                'nature_service_fait_id' => $data['nature_service_fait_id'] ?? null,
                'reception_id'           => $reception?->id,
                'montant_liquide'        => $montant,
                'date_service_fait'      => $data['date_service_fait'] ?? $reception?->date_reception ?? now(),
                'observations'           => $data['observations'] ?? null,
                'statut'                 => 'brouillon',
            ]);

            $this->initialiserPreuves($liquidation, $reception);

            ActivityLog::logAction($engagement, 'liquidation_creee', [
                'liquidation' => $liquidation->numero,
                'montant'     => $montant,
                'reception'   => $reception?->numero,
            ]);

            return $liquidation;
        });
    }

    /** Liste des preuves attendues pour la nature (complétée par la réception s'il y en a une). */
    public function initialiserPreuves(Liquidation $liquidation, ?Reception $reception = null): void
    {
        $nature = $liquidation->nature_service_fait_id ? NatureServiceFait::with('preuves')->find($liquidation->nature_service_fait_id) : null;

        foreach ($nature?->preuves ?? [] as $preuve) {
            $reference = null;

            if ($reception) {
                $libelle = mb_strtolower($preuve->libelle);
                $reference = match (true) {
                    str_contains($libelle, 'livraison') => $reception->numero_bordereau_livraison ?: null,
                    str_contains($libelle, 'réception') => $reception->numero . ($reception->date_pv ? ' (PV du ' . $reception->date_pv->format('d/m/Y') . ')' : ''),
                    str_contains($libelle, 'facture')   => $reception->numero_facture ?: null,
                    default                             => null,
                };
            }

            $liquidation->preuves()->firstOrCreate(
                ['preuve_service_fait_id' => $preuve->id],
                ['libelle' => $preuve->libelle, 'obligatoire' => $preuve->obligatoire, 'reference_document' => $reference, 'fourni' => filled($reference)]
            );
        }
    }

    // ════════════════════════════════════════════════════════
    // CONTRÔLES
    // ════════════════════════════════════════════════════════

    /**
     * Contrôles de liquidation. Chaque contrôle : [libelle, ok (bool), bloquant (bool), detail].
     */
    public function controler(Liquidation $liquidation): array
    {
        $liquidation->loadMissing(['engagement', 'preuves', 'reception']);
        $engagement = $liquidation->engagement;
        $controles = [];

        // 1. Rapprochement avec l'engagement : montant
        $reste = $this->resteALiquider($engagement, $liquidation->id);
        $controles[] = [
            'libelle'  => "Montant liquidé ≤ reste à liquider de l'engagement",
            'ok'       => (float) $liquidation->montant_liquide <= $reste + 0.01,
            'bloquant' => true,
            'detail'   => number_format((float) $liquidation->montant_liquide, 0, ',', ' ') . ' / ' . number_format($reste, 0, ',', ' ') . ' FCFA',
        ];

        // 2. Engagement définitif
        $controles[] = [
            'libelle'  => 'Engagement définitif',
            'ok'       => $engagement->statut === 'definitif',
            'bloquant' => true,
            'detail'   => "Statut : {$engagement->statut}",
        ];

        // 3. Droits du créancier : bénéficiaire actif
        $beneficiaire = $engagement->getBeneficiaire();
        $actif = match (true) {
            $beneficiaire instanceof Fournisseur => $beneficiaire->actif && !$beneficiaire->blackliste,
            $beneficiaire instanceof Personnel   => (bool) $beneficiaire->actif,
            default                              => $beneficiaire !== null,
        };
        $controles[] = [
            'libelle'  => 'Droits du créancier : bénéficiaire actif et non blacklisté',
            'ok'       => $actif,
            'bloquant' => true,
            'detail'   => $beneficiaire?->raison_sociale ?? $beneficiaire?->nom_complet ?? 'Bénéficiaire introuvable',
        ];

        // 4. Certification du service fait : preuves obligatoires fournies
        $manquantes = $liquidation->preuvesManquantes();
        $controles[] = [
            'libelle'  => 'Preuves obligatoires du service fait fournies',
            'ok'       => $manquantes->isEmpty(),
            'bloquant' => true,
            'detail'   => $manquantes->isEmpty() ? 'Complètes' : 'Manquantes : ' . $manquantes->pluck('libelle')->implode(', '),
        ];

        // 5. Réception conforme (BC) — avertissement
        if ($engagement->estBonCommande()) {
            $controles[] = [
                'libelle'  => 'Réception conforme rattachée (comptabilité matières)',
                'ok'       => $liquidation->reception_id !== null,
                'bloquant' => false,
                'detail'   => $liquidation->reception?->numero ?? 'Aucune réception rattachée',
            ];
        }

        // 6. Dossier fournisseur — avertissement
        if ($beneficiaire instanceof Fournisseur && $engagement->engageable) {
            $dossier = DossierFournisseurService::dossierDuDocument($engagement->engageable);
            $controles[] = [
                'libelle'  => 'Dossier fournisseur ouvert',
                'ok'       => $dossier !== null,
                'bloquant' => false,
                'detail'   => $dossier?->numero_dossier ?? 'Aucun dossier',
            ];
        }

        return $controles;
    }

    protected function verifierBloquants(array $controles): void
    {
        $echecs = collect($controles)->filter(fn($c) => $c['bloquant'] && !$c['ok']);

        if ($echecs->isNotEmpty()) {
            throw new DomainException("Contrôles non satisfaits :\n• " . $echecs->map(fn($c) => "{$c['libelle']} ({$c['detail']})")->implode("\n• "));
        }
    }

    // ════════════════════════════════════════════════════════
    // CIRCUIT
    // ════════════════════════════════════════════════════════

    /** Étape 1 — comptable matières : certification du service fait. */
    public function certifierServiceFait(Liquidation $liquidation): void
    {
        $this->exigerStatut($liquidation, 'brouillon');

        $manquantes = $liquidation->load('preuves')->preuvesManquantes();
        if ($manquantes->isNotEmpty()) {
            throw new DomainException('Preuves obligatoires manquantes : ' . $manquantes->pluck('libelle')->implode(', '));
        }

        $liquidation->update([
            'statut'             => 'service_fait_certifie',
            'certifie_par'       => auth()->id(),
            'date_certification' => now(),
            'motif_rejet'        => null,
        ]);

        ActivityLog::logAction($liquidation, 'certifier_service_fait', ['montant' => $liquidation->montant_liquide]);
    }

    /** Étape 2 — ordonnateur : liquidation (arrêt du montant de la dette, échéance figée). */
    public function liquider(Liquidation $liquidation, ?string $dateLiquidation = null): void
    {
        $this->exigerStatut($liquidation, 'service_fait_certifie');

        DB::transaction(function () use ($liquidation, $dateLiquidation) {
            $controles = $this->controler($liquidation);
            $this->verifierBloquants($controles);

            $date = Carbon::parse($dateLiquidation ?? now())->startOfDay();
            $delai = (int) ParametresExecution::get('delai_paiement_jours', $date);

            $liquidation->update([
                'statut'                 => 'liquidee',
                'liquide_par'            => auth()->id(),
                'date_liquidation'       => $date,
                'delai_paiement_jours'   => $delai,
                'date_echeance_paiement' => $date->copy()->addDays($delai),
                'controles'              => $controles,
                'motif_rejet'            => null,
            ]);

            ActivityLog::logAction($liquidation, 'liquider', [
                'montant'  => $liquidation->montant_liquide,
                'delai'    => $delai,
                'echeance' => $liquidation->date_echeance_paiement?->format('d/m/Y'),
            ]);
        });
    }

    /** Étape 3 — contrôleur financier : visa de régularité. */
    public function viser(Liquidation $liquidation): void
    {
        $this->exigerStatut($liquidation, 'liquidee');

        $liquidation->update(['statut' => 'visee', 'vise_par' => auth()->id(), 'date_visa' => now()]);

        ActivityLog::logAction($liquidation, 'viser', ['montant' => $liquidation->montant_liquide]);
    }

    /** Rejet à toute étape avant ordonnancement : retour en brouillon, motivé. */
    public function rejeter(Liquidation $liquidation, string $motif): void
    {
        if (!in_array($liquidation->statut, ['service_fait_certifie', 'liquidee', 'visee'], true)) {
            throw new DomainException('Seule une liquidation en cours de circuit peut être rejetée.');
        }

        $ancien = $liquidation->statut;

        $liquidation->update([
            'statut' => 'brouillon',
            'motif_rejet' => $motif,
            'certifie_par' => null,
            'date_certification' => null,
            'liquide_par' => null,
            'date_liquidation' => null,
            'vise_par' => null,
            'date_visa' => null,
            'delai_paiement_jours' => null,
            'date_echeance_paiement' => null,
        ]);

        ActivityLog::logAction($liquidation, 'rejeter', ['statut_precedent' => $ancien, 'motif' => $motif]);
    }

    /** La liquidation permet-elle l'ordonnancement ? (visa du CF exigé selon le paramètre) */
    public function estPretePourOrdonnancement(Liquidation $liquidation): bool
    {
        $visaObligatoire = (bool) ParametresExecution::get('visa_cf_obligatoire', $liquidation->date_liquidation);

        return $liquidation->statut === 'visee' || (!$visaObligatoire && $liquidation->statut === 'liquidee');
    }

    protected function exigerStatut(Liquidation $liquidation, string $statut): void
    {
        if ($liquidation->statut !== $statut) {
            throw new DomainException("Action impossible : la liquidation est « {$liquidation->statut_label} ».");
        }
    }
}
