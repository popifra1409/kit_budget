<?php
// app/Services/Budget/PaiementExceptionnelService.php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\Fournisseur;
use App\Models\LigneBudgetaire;
use App\Models\OrdonnancePaiement;
use App\Models\PaiementExceptionnel;
use App\Models\Personnel;
use App\Services\ParametresExecution;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Procédure exceptionnelle : paiement sans ordonnancement préalable.
 *
 *  - autorisation : ordonnateur, acte formel, contrôle du disponible (réservations comprises) ;
 *  - paiement     : agent comptable ; date limite de régularisation FIGÉE (délai paramétré) ;
 *  - régularisation : rattachement à une OP issue du circuit normal, même bénéficiaire,
 *                     montant suffisant, non déjà utilisée pour une autre régularisation.
 */
class PaiementExceptionnelService
{
    /** Montant des paiements exceptionnels autorisés ou payés, non encore régularisés, sur une ligne. */
    public function reserveSurLigne(int $ligneId, ?int $sauf = null): float
    {
        return (float) PaiementExceptionnel::where('ligne_budgetaire_id', $ligneId)
            ->whereIn('statut', ['autorise', 'paye'])
            ->when($sauf, fn($q) => $q->whereKeyNot($sauf))
            ->sum('montant');
    }

    /** Disponible de la ligne, déduction faite des paiements exceptionnels non régularisés. */
    public function disponibleNet(?LigneBudgetaire $ligne, ?int $sauf = null): ?float
    {
        return $ligne ? (float) $ligne->disponible_engagement - $this->reserveSurLigne($ligne->id, $sauf) : null;
    }

    public function autoriser(PaiementExceptionnel $p, array $acte): void
    {
        $this->exigerStatut($p, 'brouillon');

        if (blank($acte['reference_autorisation'] ?? null) || blank($acte['date_autorisation'] ?? null)) {
            throw new DomainException("L'autorisation exige un acte formel : référence et date.");
        }

        $disponible = $this->disponibleNet($p->ligneBudgetaire, $p->id);
        if ($disponible !== null && (float) $p->montant > $disponible + 0.01) {
            throw new DomainException('Crédits insuffisants sur la ligne d\'imputation : disponible net de '
                . number_format($disponible, 0, ',', ' ') . ' FCFA (paiements exceptionnels non régularisés déduits).');
        }

        $p->update([
            'statut'                 => 'autorise',
            'autorise_par'           => auth()->id(),
            'reference_autorisation' => $acte['reference_autorisation'],
            'date_autorisation'      => $acte['date_autorisation'],
            'piece_autorisation'     => $acte['piece_autorisation'] ?? $p->piece_autorisation,
        ]);

        ActivityLog::logAction($p, 'autoriser', ['montant' => $p->montant, 'acte' => $acte['reference_autorisation']]);
    }

    public function enregistrerPaiement(PaiementExceptionnel $p, array $paiement): void
    {
        $this->exigerStatut($p, 'autorise');

        $date = Carbon::parse($paiement['date_paiement'] ?? now())->startOfDay();
        $delai = (int) ParametresExecution::get('delai_regularisation_jours', $date);

        $p->update([
            'statut'                     => 'paye',
            'paye_par'                   => auth()->id(),
            'date_paiement'              => $date,
            'mode_paiement'              => $paiement['mode_paiement'] ?? null,
            'reference_paiement'         => $paiement['reference_paiement'] ?? null,
            'piece_paiement'             => $paiement['piece_paiement'] ?? null,
            'delai_regularisation_jours' => $delai,
            'date_limite_regularisation' => $date->copy()->addDays($delai),
        ]);

        ActivityLog::logAction($p, 'payer_sans_ordonnancement', [
            'montant'        => $p->montant,
            'limite_regul.'  => $p->date_limite_regularisation?->format('d/m/Y'),
        ]);
    }

    /** OP utilisables pour régulariser : même bénéficiaire, montant suffisant, non déjà utilisées. */
    public function opsRegularisables(PaiementExceptionnel $p)
    {
        $formes = \App\Models\Engagement::formesDuType((string) $p->beneficiaire_type);

        return OrdonnancePaiement::query()
            ->where('type_ordonnance', 'standard')
            ->whereIn('beneficiaire_type', $formes)
            ->where('beneficiaire_id', $p->beneficiaire_id)
            ->where('montant_brut', '>=', (float) $p->montant - 0.01)
            ->whereNotIn('id', PaiementExceptionnel::whereNotNull('ordonnance_paiement_id')->whereKeyNot($p->id)->pluck('ordonnance_paiement_id'))
            ->orderByDesc('id')
            ->get();
    }

    public function regulariser(PaiementExceptionnel $p, int $ordonnanceId, ?string $observations = null): void
    {
        $this->exigerStatut($p, 'paye');

        $op = $this->opsRegularisables($p)->firstWhere('id', $ordonnanceId);
        if (!$op) {
            throw new DomainException("L'OP choisie ne peut pas régulariser ce paiement : bénéficiaire différent, montant insuffisant ou OP déjà utilisée.");
        }

        DB::transaction(function () use ($p, $op, $observations) {
            $p->update([
                'statut'                 => 'regularise',
                'ordonnance_paiement_id' => $op->id,
                'regularise_par'         => auth()->id(),
                'date_regularisation'    => now(),
                'observations'           => trim(($p->observations ?? '') . "\n" . ($observations ?? '')) ?: null,
            ]);

            ActivityLog::logAction($p, 'regulariser', [
                'op'         => $op->numero,
                'en_retard'  => $p->date_limite_regularisation && now()->startOfDay()->gt($p->date_limite_regularisation),
            ]);
        });
    }

    public function annuler(PaiementExceptionnel $p, string $motif): void
    {
        if (!in_array($p->statut, ['brouillon', 'autorise'], true)) {
            throw new DomainException('Un paiement déjà effectué ne peut pas être annulé : il doit être régularisé.');
        }

        $p->update(['statut' => 'annule', 'observations' => trim(($p->observations ?? '') . "\nAnnulé : {$motif}")]);
        ActivityLog::logAction($p, 'annuler', ['motif' => $motif]);
    }

    /** Synthèse pour le tableau de bord. */
    public function synthese(): array
    {
        $aRegulariser = PaiementExceptionnel::where('statut', 'paye')->get();
        $enRetard = $aRegulariser->filter->estEnRetard();

        return [
            'a_regulariser_nombre'  => $aRegulariser->count(),
            'a_regulariser_montant' => (float) $aRegulariser->sum('montant'),
            'en_retard_nombre'      => $enRetard->count(),
            'en_retard_montant'     => (float) $enRetard->sum('montant'),
            'autorises_nombre'      => PaiementExceptionnel::where('statut', 'autorise')->count(),
        ];
    }

    public static function libelleBeneficiaireType(?string $type): string
    {
        return match ($type) {
            Fournisseur::class => 'Fournisseur',
            Personnel::class => 'Agent',
            default => '—'
        };
    }

    protected function exigerStatut(PaiementExceptionnel $p, string $statut): void
    {
        if ($p->statut !== $statut) {
            throw new DomainException("Action impossible : le paiement est « {$p->statut_label} ».");
        }
    }
}
