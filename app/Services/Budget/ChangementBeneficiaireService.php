<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\Avenant;
use App\Models\BonCommande;
use App\Models\DecisionAdministrative;
use App\Models\DossierFournisseur;
use App\Models\Engagement;
use App\Models\Fournisseur;
use App\Models\OrdonnancePaiement;
use App\Models\Personnel;
use App\Services\DossierFournisseurService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Avenant « changement de bénéficiaire » d'un engagement issu d'un BC ou d'une DA.
 *
 *  - BC : nouveau fournisseur (existant ou créé dans l'avenant) ;
 *  - DA : nouvel agent (Personnel) ou nouveau fournisseur.
 *
 * Règles de gestion (validées) :
 *  - refusé si l'OP standard est déjà payée (l'argent est parti : procédure de reversement) ;
 *  - l'ancien dossier fournisseur est conservé tel quel, avec la mention
 *    « Bénéficiaire remplacé par avenant n° … » ; un nouveau dossier est ouvert ;
 *  - les retenues sont recalculées selon le régime fiscal du nouveau fournisseur,
 *    et restent modifiables avant validation.
 *
 * Aucun effet budgétaire : ni la ligne, ni le montant engagé, ni les crédits ne changent.
 * Tout est fait dans UNE transaction : en cas d'erreur, rien n'est modifié.
 */
class ChangementBeneficiaireService
{
    /** Champs de retenues par type de document. */
    public const TAXES_BC = ['montant_ir', 'montant_tva', 'montant_tsr'];
    public const TAXES_DA = ['montant_cnps', 'montant_ir', 'montant_irnc', 'montant_tva', 'autres_retenues'];

    // ════════════════════════════════════════════════════════
    // CONTRÔLES
    // ════════════════════════════════════════════════════════

    /** @throws DomainException si l'engagement ne peut pas changer de bénéficiaire */
    public function verifier(Engagement $engagement): void
    {
        if ($engagement->statut !== 'definitif') {
            throw new DomainException("Seul un engagement définitif peut faire l'objet d'un avenant.");
        }

        if (!$engagement->estBonCommande() && !$engagement->estDecision()) {
            throw new DomainException(
                "Le changement de bénéficiaire concerne uniquement les engagements issus d'un bon de commande ou d'une décision administrative."
            );
        }

        $opPayee = $engagement->ordonnancesPaiement()
            ->where('type_ordonnance', 'standard')
            ->where('statut', 'payee')
            ->exists();

        if ($opPayee) {
            throw new DomainException(
                "L'ordonnance de paiement a déjà été payée à l'ancien bénéficiaire : le changement est impossible. "
                    . "Il faut passer par une procédure de reversement."
            );
        }
    }

    /** Peut-on proposer l'action sur cet engagement ? (pour l'affichage du bouton) */
    public function estPossible(Engagement $engagement): bool
    {
        try {
            $this->verifier($engagement);
            return true;
        } catch (DomainException) {
            return false;
        }
    }

    // ════════════════════════════════════════════════════════
    // VALEURS DU FORMULAIRE
    // ════════════════════════════════════════════════════════

    /** Valeurs initiales du formulaire : retenues actuelles du document. */
    public function valeursInitiales(Engagement $engagement): array
    {
        $document = $this->documentSource($engagement);
        $champs = $document instanceof BonCommande ? self::TAXES_BC : self::TAXES_DA;

        $valeurs = ['type_beneficiaire' => 'fournisseur'];

        if ($document instanceof DecisionAdministrative) {
            $valeurs['type_beneficiaire'] = $document->personnel_id ? 'personnel' : 'fournisseur';
        }

        foreach ($champs as $champ) {
            $valeurs[$champ] = (float) ($document?->{$champ} ?? 0);
        }

        return $valeurs;
    }

    /** IR proposé selon le régime fiscal du fournisseur choisi (base : HT pour un BC, brut pour une DA). */
    public function irPropose(Engagement $engagement, int|string|null $fournisseurId): ?float
    {
        $document = $this->documentSource($engagement);
        $fournisseur = $fournisseurId ? Fournisseur::with('regimeFiscal')->find($fournisseurId) : null;

        if (!$document || !$fournisseur?->regimeFiscal) {
            return null; // pas de régime : on garde la valeur saisie
        }

        $base = $document instanceof BonCommande
            ? (float) ($document->montant_ht ?? 0)
            : (float) ($document->montant_brut ?? 0);

        return round($fournisseur->calculerIR($base), 2);
    }

    /** Libellé lisible d'un bénéficiaire. */
    public function libelleBeneficiaire(?Model $beneficiaire): string
    {
        return match (true) {
            $beneficiaire instanceof Fournisseur => trim($beneficiaire->raison_sociale . ($beneficiaire->nif ? " (NIU {$beneficiaire->nif})" : '')),
            $beneficiaire instanceof Personnel   => trim($beneficiaire->nom_complet . ($beneficiaire->matricule ? " (mat. {$beneficiaire->matricule})" : '')),
            default                              => '—',
        };
    }

    // ════════════════════════════════════════════════════════
    // CRÉATION D'UN BÉNÉFICIAIRE DEPUIS L'AVENANT
    // ════════════════════════════════════════════════════════

    public function creerFournisseur(array $data): int
    {
        if (!auth()->user()?->can('create_fournisseur')) {
            throw new DomainException("Vous n'avez pas le droit de créer un fournisseur.");
        }

        $nif = trim((string) ($data['nif'] ?? ''));

        // Doublon : même NIU, y compris parmi les fournisseurs supprimés
        if ($nif !== '' && Fournisseur::withTrashed()->where('nif', $nif)->exists()) {
            throw ValidationException::withMessages([
                'nif' => "Un fournisseur avec le NIU {$nif} existe déjà : sélectionnez-le dans la liste.",
            ]);
        }

        $fournisseur = Fournisseur::create([
            'raison_sociale'   => trim($data['raison_sociale']),
            'sigle'            => $data['sigle'] ?? null,
            'nif'              => $nif ?: null,
            'rccm'             => $data['rccm'] ?? null,
            'regime_fiscal_id' => $data['regime_fiscal_id'] ?? null,
            'type'             => $data['type'] ?? 'mixte',
            'adresse'          => $data['adresse'] ?? null,
            'ville'            => $data['ville'] ?? null,
            'telephone'        => $data['telephone'] ?? null,
            'email'            => $data['email'] ?? null,
            'banque'           => $data['banque'] ?? null,
            'numero_compte'    => $data['numero_compte'] ?? null,
            'actif'            => true,
            'blackliste'       => false,
            'observations'     => 'Créé lors d\'un avenant de changement de bénéficiaire',
        ]);

        return $fournisseur->id;
    }

    public function creerPersonnel(array $data): int
    {
        if (!auth()->user()?->can('create_personnel')) {
            throw new DomainException("Vous n'avez pas le droit de créer un agent.");
        }

        $cni = trim((string) ($data['numero_cni'] ?? ''));

        if ($cni !== '' && Personnel::withTrashed()->where('numero_cni', $cni)->exists()) {
            throw ValidationException::withMessages([
                'numero_cni' => "Un agent avec la CNI {$cni} existe déjà : sélectionnez-le dans la liste.",
            ]);
        }

        // Matricule généré automatiquement par le modèle (MAT-AAAA-XXXX)
        $personnel = Personnel::create([
            'nom'                    => mb_strtoupper(trim($data['nom'])),
            'prenoms'                => $data['prenoms'] ?? null,
            'fonction'               => $data['fonction'] ?? null,
            'telephone'              => $data['telephone'] ?? null,
            'numero_cni'             => $cni ?: null,
            'banque'                 => $data['banque'] ?? null,
            'numero_compte_bancaire' => $data['numero_compte_bancaire'] ?? null,
            'statut'                 => 'actif',
            'actif'                  => true,
        ]);

        return $personnel->id;
    }

    // ════════════════════════════════════════════════════════
    // APPLICATION DE L'AVENANT
    // ════════════════════════════════════════════════════════

    /**
     * @param string $classeBeneficiaire Fournisseur::class ou Personnel::class
     * @param array  $saisie             retenues saisies (montant_ir, montant_tva...)
     */
    public function appliquer(
        Engagement $engagement,
        string $classeBeneficiaire,
        int $beneficiaireId,
        array $saisie,
        string $motif
    ): Avenant {
        return DB::transaction(function () use ($engagement, $classeBeneficiaire, $beneficiaireId, $saisie, $motif) {
            // Verrou : deux avenants simultanés sur le même engagement sont impossibles
            $engagement = Engagement::withoutGlobalScope('exercice')
                ->whereKey($engagement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->verifier($engagement);

            $document = $this->documentSource($engagement);
            if (!$document) {
                throw new DomainException("Document source de l'engagement introuvable.");
            }

            $nouveau = $this->chargerBeneficiaire($classeBeneficiaire, $beneficiaireId, $document);

            $ancienClasse = $engagement->getBeneficiaireClass();
            $ancienId = (int) $engagement->beneficiaire_id;
            // Ancien bénéficiaire, même supprimé (sans présumer que le modèle gère la suppression douce)
            $ancien = $ancienClasse && class_exists($ancienClasse)
                ? $ancienClasse::query()->withoutGlobalScopes()->find($ancienId)
                : null;

            if ($ancienClasse === $nouveau::class && $ancienId === (int) $nouveau->id) {
                throw new DomainException("Le bénéficiaire choisi est déjà celui de l'engagement.");
            }

            $montants = $this->calculerMontants($document, $saisie);
            $this->verifierOrdonnances($engagement, $montants['total_taxes']);

            // 1. Avenant (numéroté dans la même série que les autres avenants de l'engagement)
            $avenant = Avenant::create([
                'document_source_type'       => $engagement->engageable_type,
                'document_source_id'         => $engagement->engageable_id,
                'document_corrige_type'      => $engagement->engageable_type,
                'document_corrige_id'        => $engagement->engageable_id,
                'engagement_original_id'     => $engagement->id,
                'numero_avenant'             => Avenant::prochainNumero($engagement->id),
                'motif'                      => trim($motif),
                'type_correction'            => Avenant::TYPE_BENEFICIAIRE,
                'montant_original'           => $engagement->montant_engage,
                'montant_corrige'            => $engagement->montant_engage, // aucun effet budgétaire
                'delta_montant'              => 0,
                'nomenclature_originale_id'  => $engagement->nomenclature_principale_id,
                'nomenclature_corrigee_id'   => $engagement->nomenclature_principale_id,
                'montant_taxes_original'     => $montants['total_taxes_original'],
                'montant_taxes_corrige'      => $montants['total_taxes'],
                'corriger_ordonnances'       => true,
                'beneficiaire_original_type' => $ancienClasse,
                'beneficiaire_original_id'   => $ancienId ?: null,
                'beneficiaire_corrige_type'  => $nouveau::class,
                'beneficiaire_corrige_id'    => $nouveau->id,
                'statut'                     => 'applique',
                'applique_par'               => auth()->id(),
                'date_application'           => now(),
                'created_by'                 => auth()->id(),
            ]);

            // 2. Document source, 3. engagement, 4. ordonnances non payées
            $this->mettreAJourDocument($document, $nouveau, $montants);
            $engagement->update([
                'beneficiaire_type' => $nouveau::class,
                'beneficiaire_id'   => $nouveau->id,
            ]);
            $this->mettreAJourOrdonnances($engagement, $nouveau, $montants);

            // 5. Dossiers fournisseurs
            $dossier = $this->gererDossiers($document, $engagement, $nouveau, $avenant, $ancienClasse, $ancienId);
            if ($dossier) {
                $avenant->update(['dossier_fournisseur_cree_id' => $dossier->id]);
            }

            // 6. Traçabilité métier (les mises à jour silencieuses ne passent pas par le journal)
            $details = [
                'avenant'             => $avenant->numero_avenant,
                'ancien_beneficiaire' => $this->libelleBeneficiaire($ancien),
                'nouveau_beneficiaire' => $this->libelleBeneficiaire($nouveau),
                'taxes_avant'         => $montants['total_taxes_original'],
                'taxes_apres'         => $montants['total_taxes'],
                'net_apres'           => $montants['net'],
                'dossier_ouvert'      => $dossier?->numero_dossier,
                'motif'               => trim($motif),
            ];
            ActivityLog::logAction($engagement, 'changer_beneficiaire', $details);
            ActivityLog::logAction($document, 'changer_beneficiaire', $details);

            Log::info('Avenant de changement de bénéficiaire appliqué', ['engagement' => $engagement->numero] + $details);

            return $avenant;
        });
    }

    // ════════════════════════════════════════════════════════
    // ÉTAPES INTERNES
    // ════════════════════════════════════════════════════════

    protected function documentSource(Engagement $engagement): BonCommande|DecisionAdministrative|null
    {
        $classe = $engagement->getEngageableClass();

        if (!in_array($classe, [BonCommande::class, DecisionAdministrative::class], true)) {
            return null;
        }

        return $classe::withoutGlobalScope('exercice')->find($engagement->engageable_id);
    }

    protected function chargerBeneficiaire(string $classe, int $id, Model $document): Fournisseur|Personnel
    {
        $autorises = $document instanceof BonCommande
            ? [Fournisseur::class]
            : [Fournisseur::class, Personnel::class];

        if (!in_array($classe, $autorises, true)) {
            throw new DomainException($document instanceof BonCommande
                ? "Le bénéficiaire d'un bon de commande doit être un fournisseur."
                : "Le bénéficiaire d'une décision doit être un agent ou un fournisseur.");
        }

        $beneficiaire = $classe::find($id);

        if (!$beneficiaire) {
            throw new DomainException("Le bénéficiaire choisi est introuvable.");
        }

        if ($beneficiaire instanceof Fournisseur && ($beneficiaire->blackliste || !$beneficiaire->actif)) {
            throw new DomainException("Le fournisseur {$beneficiaire->raison_sociale} est inactif ou blacklisté : il ne peut pas être retenu.");
        }

        if ($beneficiaire instanceof Personnel && !$beneficiaire->actif) {
            throw new DomainException("L'agent {$beneficiaire->nom_complet} est inactif : il ne peut pas être retenu.");
        }

        return $beneficiaire;
    }

    /** Montants après avenant : brut inchangé, retenues saisies, net recalculé. */
    protected function calculerMontants(Model $document, array $saisie): array
    {
        $champs = $document instanceof BonCommande ? self::TAXES_BC : self::TAXES_DA;
        $brut = $document instanceof BonCommande
            ? (float) ($document->montant_ttc ?? 0)
            : (float) ($document->montant_brut ?? 0);

        $taxes = [];
        $totalOriginal = 0.0;

        foreach ($champs as $champ) {
            $valeur = round((float) ($saisie[$champ] ?? $document->{$champ} ?? 0), 2);

            if ($valeur < 0) {
                throw new DomainException("Les retenues ne peuvent pas être négatives.");
            }

            $taxes[$champ] = $valeur;
            $totalOriginal += (float) ($document->{$champ} ?? 0);
        }

        $total = round(array_sum($taxes), 2);

        if ($total > $brut) {
            throw new DomainException(
                'Le total des retenues (' . number_format($total, 0, ',', ' ') . ' FCFA) dépasse le montant brut ('
                    . number_format($brut, 0, ',', ' ') . ' FCFA).'
            );
        }

        return [
            'brut'                 => $brut,
            'taxes'                => $taxes,
            'total_taxes'          => $total,
            'total_taxes_original' => round($totalOriginal, 2),
            'net'                  => round($brut - $total, 2),
        ];
    }

    /**
     * Les ordonnances existantes doivent pouvoir absorber les nouvelles retenues.
     * La création ou la suppression d'une OP impôt relève de l'avenant « Taxes »
     * (numérotation, collisions) : on ne la duplique pas ici.
     */
    protected function verifierOrdonnances(Engagement $engagement, float $totalTaxes): void
    {
        $ops = $engagement->ordonnancesPaiement()->get();

        if ($ops->isEmpty()) {
            return; // OP non encore émises : elles reprendront les montants du document
        }

        $opImpot = $ops->firstWhere('type_ordonnance', 'impot');

        if (!$opImpot && $totalTaxes > 0) {
            throw new DomainException(
                'Les nouvelles retenues (' . number_format($totalTaxes, 0, ',', ' ') . ' FCFA) nécessitent une OP impôt, '
                    . "absente de cet engagement. Appliquez le changement sans retenue, puis l'avenant « Taxes »."
            );
        }

        if ($opImpot && $totalTaxes <= 0) {
            throw new DomainException(
                "Les retenues deviennent nulles alors qu'une OP impôt existe. "
                    . "Appliquez le changement en gardant les retenues, puis l'avenant « Taxes »."
            );
        }

        if ($opImpot && $opImpot->statut === 'payee' && abs((float) $opImpot->montant_net - $totalTaxes) > 0.01) {
            throw new DomainException(
                "L'OP impôt est déjà payée : les retenues ne peuvent plus être modifiées. Gardez les retenues actuelles."
            );
        }
    }

    protected function mettreAJourDocument(Model $document, Fournisseur|Personnel $nouveau, array $montants): void
    {
        // Mise à jour silencieuse, comme l'avenant existant : évite les recalculs automatiques
        // des observers du document (la trace est assurée par ActivityLog::logAction).
        if ($document instanceof BonCommande) {
            $document->updateQuietly(array_merge($montants['taxes'], [
                'fournisseur_id'  => $nouveau->id,
                'net_a_percevoir' => $montants['net'],
            ]));

            return;
        }

        /** @var DecisionAdministrative $document */
        $donnees = array_merge($montants['taxes'], [
            'total_taxes' => $montants['total_taxes'],
            'montant_net' => $montants['net'],
        ]);

        if ($nouveau instanceof Personnel) {
            $donnees += ['type_beneficiaire' => 'personnel', 'personnel_id' => $nouveau->id, 'fournisseur_id' => null];
        } else {
            $donnees += ['type_beneficiaire' => 'fournisseur', 'fournisseur_id' => $nouveau->id, 'personnel_id' => null];
        }

        if (abs($montants['total_taxes'] - $montants['total_taxes_original']) > 0.01) {
            $donnees['mode_saisie'] = 'forfait'; // même convention que l'avenant « Taxes »
        }

        $document->updateQuietly($donnees);
    }

    protected function mettreAJourOrdonnances(Engagement $engagement, Fournisseur|Personnel $nouveau, array $montants): void
    {
        $detail = [
            'montant_cnps'         => $montants['taxes']['montant_cnps'] ?? 0,
            'montant_ir'           => $montants['taxes']['montant_ir'] ?? 0,
            'montant_irnc'         => $montants['taxes']['montant_irnc'] ?? 0,
            'montant_tva'          => $montants['taxes']['montant_tva'] ?? 0,
            'montant_tsr'          => $montants['taxes']['montant_tsr'] ?? 0,
            'montant_autres_taxes' => $montants['taxes']['autres_retenues'] ?? 0,
        ];

        foreach ($engagement->ordonnancesPaiement()->get() as $op) {
            /** @var OrdonnancePaiement $op */
            if ($op->statut === 'payee') {
                continue; // OP impôt payée : contrôlée plus haut, rien à modifier
            }

            if ($op->type_ordonnance === 'standard') {
                $op->updateQuietly(array_merge($detail, [
                    'beneficiaire_type' => $nouveau::class,
                    'beneficiaire_id'   => $nouveau->id,
                    'montant_brut'      => $montants['brut'],
                    'montant_impot'     => $montants['total_taxes'],
                    'montant_net'       => $montants['net'],
                ]));
            } elseif ($op->type_ordonnance === 'impot') {
                // Le bénéficiaire de l'OP impôt (Trésor) ne change pas : seuls les montants suivent
                $op->updateQuietly(array_merge($detail, [
                    'montant_brut'  => $montants['total_taxes'],
                    'montant_impot' => $montants['total_taxes'],
                    'montant_net'   => $montants['total_taxes'],
                ]));
            }
        }
    }

    /**
     * Nouveau fournisseur : ouvre son dossier (même service et même numérotation qu'un dossier
     * ouvert à l'engagement) et y verse le document, le certificat d'engagement et les OP.
     * Ancien fournisseur : son dossier est conservé tel quel, avec la mention de l'avenant.
     */
    protected function gererDossiers(
        Model $document,
        Engagement $engagement,
        Fournisseur|Personnel $nouveau,
        Avenant $avenant,
        ?string $ancienClasse,
        int $ancienId
    ): ?DossierFournisseur {
        // Mention sur l'ancien dossier (si l'ancien bénéficiaire était un fournisseur)
        if ($ancienClasse === Fournisseur::class && $ancienId) {
            $ancienDossier = DossierFournisseurService::dossierDuDocument($document, $ancienId);

            if ($ancienDossier && (int) $ancienDossier->fournisseur_id === $ancienId) {
                $metadata = $ancienDossier->metadata ?? [];
                $metadata['beneficiaire_remplace'] = [
                    'avenant_id'          => $avenant->id,
                    'numero_avenant'      => $avenant->numero_avenant,
                    'engagement'          => $engagement->numero,
                    'nouveau_beneficiaire' => $this->libelleBeneficiaire($nouveau),
                    'date'                => now()->toDateString(),
                ];
                $ancienDossier->updateQuietly(['metadata' => $metadata]);
            }
        }

        if (!$nouveau instanceof Fournisseur) {
            return null; // un agent n'a pas de dossier fournisseur
        }

        $exerciceId = $document->exercice_id ?? $engagement->exercice_id;
        $dossier = DossierFournisseurService::obtenirOuCreer($nouveau->id, $exerciceId, $document);

        $metadata = $dossier->metadata ?? [];
        $metadata['ouvert_par_avenant'] = [
            'avenant_id'     => $avenant->id,
            'numero_avenant' => $avenant->numero_avenant,
            'engagement'     => $engagement->numero,
        ];
        $dossier->updateQuietly(['metadata' => $metadata]);

        $typeDocument = $document instanceof BonCommande ? 'bon_commande' : 'decision_administrative';
        $prefixe = $document instanceof BonCommande ? 'BC' : 'DA';

        DossierFournisseurService::ajouterPieceAutomatique($dossier, $typeDocument, $document, "{$prefixe}-{$document->numero}.pdf");
        DossierFournisseurService::ajouterPieceAutomatique($dossier, 'certificat_engagement', $engagement, "CE-{$engagement->numero}.pdf");

        foreach ($engagement->ordonnancesPaiement()->get() as $op) {
            $impot = $op->type_ordonnance === 'impot';
            DossierFournisseurService::ajouterPieceAutomatique(
                $dossier,
                $impot ? 'ordonnance_impot' : 'ordonnance_paiement',
                $op,
                ($impot ? 'OPT' : 'OP') . "-{$op->numero}.pdf"
            );
        }

        return $dossier;
    }
}
