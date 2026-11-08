<?php

namespace App\Services\Budget;

use App\Models\Engagement;
use App\Models\OrdonnancePaiement;
use Carbon\Carbon;

/**
 * Le gel de l'ordonnance de paiement : la copie de ce que le papier imprime.
 *
 * L'ordonnance est le document que l'agence comptable paie. Jusqu'ici, ses gabarits
 * relisaient à chaque impression le bon de commande ou la décision administrative
 * vivant : un avenant, une DA corrigée, un bénéficiaire dont l'adresse change, et la
 * ré-impression d'une OP déjà payée ne disait plus la même chose que le papier qui a
 * circulé.
 *
 * Ce service ne fige PAS la dotation, les collectifs, l'engagé avant ni les
 * disponibles : le gabarit d'ordonnance les lit par
 * `HistoriqueLigneBudgetaireService::etatPourEngagement()`, c'est-à-dire par le gel du
 * certificat. Une copie ici ferait deux vérités sur deux papiers du même circuit.
 *
 * Ce qui est figé, et rien d'autre : les montants que le papier tape (brut, net,
 * retenues, imputation, banque/billetage, montant de l'engagement), le détail des
 * retenues et les taux qui entrent dans les libellés, la hiérarchie d'imputation,
 * l'identité du bénéficiaire et celle du reverseur sur l'OP d'impôt.
 *
 * Les libellés se gèlent en valeurs brutes (code, intitulé), pas en mise en page : le
 * « (12) » d'un sous-programme ou le « IR (10,50 %) » d'un taux se recomposent
 * déterministiquement à partir de ce qui est stocké ici. Ce qui dérive, c'est la
 * source, pas le formatage.
 *
 * Le gel se pose à `created()` : les ordonnances naissent avec le statut « emise »
 * (Engagement::creerOrdonnancesPaiement), il n'y a pas de brouillon à attendre.
 */
class GelOrdonnancePaiementService
{
    /** Les huit colonnes du gel — elles se posent toutes ou aucune. */
    public const COLONNES_GEL = [
        'snapshot_montant_brut',
        'snapshot_montant_net',
        'snapshot_montant_retenu',
        'snapshot_montants',
        'snapshot_retenues',
        'snapshot_imputation',
        'snapshot_identites',
        'snapshot_fige_le',
    ];

    /**
     * Ce qui défait le gel : ce qui change la consistance du document, pas ce qui
     * l'administre. Un visa, une date de paiement, une observation ne le défont pas.
     */
    public const CHAMPS_QUI_GELENT = [
        'engagement_id',
        'type_ordonnance',
        'date_emission',
        'montant_brut',
        'montant_net',
        'montant_impot',
        'montant_ir',
        'montant_tva',
        'montant_tsr',
        'montant_cnps',
        'montant_irnc',
        'montant_autres_taxes',
        'beneficiaire_type',
        'beneficiaire_id',
    ];

    /** Les retenues telles que les deux gabarits les additionnent. */
    public const CLES_RETENUES = ['ir', 'tva', 'tsr', 'cnps', 'irnc', 'feicom', 'redevance_av', 'autres'];

    /** La colonne de tête : un gel sans date de pose n'est pas un gel. */
    public function estGele(?OrdonnancePaiement $op): bool
    {
        return $op !== null && $op->snapshot_fige_le !== null;
    }

    /**
     * Ce que le papier doit imprimer : le gel s'il existe, sinon le calcul vif.
     *
     * Le repli sur le calcul vif garde les ordonnances nées avant ce gel imprimables ;
     * elles restent exposées à la dérive, c'est ce que la commande de backfill vient
     * fermer.
     *
     * @return array{brut: float, net: float, retenu: float, imputation: float, engage: float,
     *         banque: float, billetage: float, retenues: array, lignes: array, taux: array,
     *         hierarchie: array, beneficiaire: array, reverseur: array, gele: bool}
     */
    public function etatPourImpression(?OrdonnancePaiement $op): array
    {
        if ($op === null) {
            return $this->etatVide();
        }

        return $this->estGele($op) ? $this->etatGele($op) : $this->etatVif($op);
    }

    /** Le calcul vif : ce que la base dit aujourd'hui, tel que le gabarit le lisait. */
    public function etatVif(OrdonnancePaiement $op): array
    {
        // A `updating()`, les relations sont chargees avant que le modele ne soit sale :
        // changer de beneficiaire ou d'engagement sans recharger la relation gelerait
        // l'identite d'avant sur les montants d'apres.
        if ($op->relationLoaded('engagement') && $op->isDirty(['engagement_id'])) {
            $op->unsetRelation('engagement');
        }
        if ($op->relationLoaded('beneficiaire') && $op->isDirty(['beneficiaire_type', 'beneficiaire_id'])) {
            $op->unsetRelation('beneficiaire');
        }

        $engagement = $op->engagement;
        $source     = $engagement?->engageable;

        $etat = $this->etatVide();

        $etat['hierarchie']   = $this->hierarchie($engagement);
        $etat['beneficiaire'] = $this->identite(
            $op->beneficiaire ?? $this->beneficiaireSource($engagement, $source)
        );
        $etat['engage']       = (float) ($engagement?->montant_engage ?? 0);

        if (!$engagement || !$source) {
            // Engagement manuel, ou source introuvable : le gabarit retombait sur les
            // colonnes de l'OP. Le gel fige alors exactement cette retombee.
            $etat['brut']  = (float) ($op->montant_brut ?? 0);
            $etat['net']   = (float) ($op->montant_net ?? 0);
            $etat['imputation'] = $etat['brut'];
            $etat['retenues']   = $this->retenuesVide();
            $etat['retenu']     = 0.0;

            return $this->achever($etat);
        }

        $donnees = $engagement->extraireDonneesDocument(true);

        if ($engagement->estBonCommande()) {
            $etat['imputation'] = (float) ($donnees['montant_ht'] ?? 0);
            $etat['banque']     = 0.0;
            $etat['billetage']  = 0.0;
        } else {
            $etat['imputation'] = (float) ($donnees['montant_brut'] ?? 0);
            $etat['banque']     = (float) ($source->banque ?? 0);
            $etat['billetage']  = (float) ($source->billetage ?? 0);
        }

        $detail = $op->type_ordonnance === 'impot'
            ? $op->getDetailImpots()
            : $this->detailStandard($engagement, $donnees);

        $etat['retenues'] = array_intersect_key($detail, array_flip(self::CLES_RETENUES));
        $etat['lignes']   = $this->lignesImpot($op, $engagement, $source);
        $etat['taux']     = $this->taux($engagement, $source);
        $etat['reverseur'] = $op->type_ordonnance === 'impot'
            ? $this->identite($this->beneficiaireSource($engagement, $source) ?? $op->beneficiaire)
            : [];

        // Le brut et le net que les deux gabarits tapent : le brut vient toujours de la
        // source ; le net est ce que le bénéficiaire recoit, ou ce que le Tresor preleve
        // pour l'OP d'impot. Un brut a zero se gele tel quel : c'est ce que le papier
        // imprimait deja, et le gel qui inventerait un montant serait pire que la derive.
        $etat['brut']   = (float) ($donnees['montant_ttc'] ?? 0);
        $etat['net']    = $op->type_ordonnance === 'impot'
            ? (float) $detail['total']
            : (float) ($donnees['montant_net'] ?? 0);
        $etat['retenu'] = (float) ($detail['total'] ?? 0);

        return $this->achever($etat);
    }

    /** Le gel relu, même forme que le calcul vif : les gabarits ne voient pas la différence. */
    protected function etatGele(OrdonnancePaiement $op): array
    {
        $montants   = (array) ($op->snapshot_montants ?? []);
        $retenues   = (array) ($op->snapshot_retenues ?? []);
        $hierarchie = (array) ($op->snapshot_imputation ?? []);
        $identites  = (array) ($op->snapshot_identites ?? []);

        return $this->achever([
            'brut'       => (float) $op->snapshot_montant_brut,
            'net'        => (float) $op->snapshot_montant_net,
            'retenu'     => (float) $op->snapshot_montant_retenu,
            'imputation' => (float) ($montants['imputation'] ?? 0),
            'engage'     => (float) ($montants['engage'] ?? 0),
            'banque'     => (float) ($montants['banque'] ?? 0),
            'billetage'  => (float) ($montants['billetage'] ?? 0),
            'retenues'   => array_replace($this->retenuesVide(), (array) ($retenues['montants'] ?? [])),
            'lignes'     => array_replace($this->lignesVide(), (array) ($retenues['lignes'] ?? [])),
            'taux'       => (array) ($retenues['taux'] ?? []),
            'hierarchie' => $hierarchie,
            'beneficiaire' => (array) ($identites['beneficiaire'] ?? []),
            'reverseur'  => (array) ($identites['reverseur'] ?? []),
            'gele'       => true,
        ]);
    }

    /**
     * Pose le gel sur le modèle, sans écrire : l'appelant enregistre.
     *
     * `forceFill()` et non `update()` : les colonnes de gel ne sont pas dans `$fillable`,
     * un formulaire ne doit pas pouvoir dicter ce qu'un papier a imprimé.
     */
    public function poserGel(OrdonnancePaiement $op): bool
    {
        $etat = $this->etatVif($op);

        // Rien à geler sans bénéficiaire imprimé : un gel dont l'identité absente
        // laisserait le gabarit recalculer la seule chose qui compte pour une OP.
        if ($etat['beneficiaire'] === []) {
            return false;
        }

        $op->forceFill([
            'snapshot_montant_brut'   => round($etat['brut'], 2),
            'snapshot_montant_net'    => round($etat['net'], 2),
            'snapshot_montant_retenu' => round($etat['retenu'], 2),
            'snapshot_montants'       => [
                'imputation' => round($etat['imputation'], 2),
                'engage'     => round($etat['engage'], 2),
                'banque'     => round($etat['banque'], 2),
                'billetage'  => round($etat['billetage'], 2),
            ],
            'snapshot_retenues' => [
                'montants' => array_map(fn($v) => round((float) $v, 2), $etat['retenues']),
                'lignes'   => array_map(fn($v) => round((float) $v, 2), $etat['lignes']),
                'taux'     => array_map(fn($v) => round((float) $v, 4), $etat['taux']),
            ],
            'snapshot_imputation' => $etat['hierarchie'],
            'snapshot_identites'  => [
                'beneficiaire' => $etat['beneficiaire'],
                'reverseur'    => $etat['reverseur'],
            ],
            'snapshot_fige_le' => Carbon::now(),
        ]);

        return true;
    }

    /** Gel puis écriture silencieuse — pour les chemins hors sauvegarde en cours. */
    public function geler(OrdonnancePaiement $op): bool
    {
        if (!$this->poserGel($op)) {
            return false;
        }

        $op->saveQuietly();

        return true;
    }

    /**
     * Le détail des retenues telles que le gabarit d'OP standard le compose : BC et DA
     * n'additionnent pas les mêmes lignes.
     */
    protected function detailStandard(Engagement $engagement, array $donnees): array
    {
        $d = $this->retenuesVide();

        $d['ir']  = (float) ($donnees['montant_ir'] ?? 0);
        $d['tva'] = (float) ($donnees['montant_tva'] ?? 0);

        if ($engagement->estBonCommande()) {
            $d['tsr'] = (float) ($donnees['montant_tsr'] ?? 0);
        } else {
            $d['cnps']          = (float) ($donnees['montant_cnps'] ?? 0);
            $d['irnc']          = (float) ($donnees['montant_irnc'] ?? 0);
            $d['feicom']        = (float) ($donnees['montant_feicom'] ?? 0);
            $d['redevance_av']  = (float) ($donnees['montant_redevance'] ?? 0);
            $d['autres']        = (float) ($donnees['autres_retenues'] ?? 0);
        }

        return $this->avecTotal($d);
    }

    /**
     * Les trois lignes que le gabarit d'OP d'impôt tape pour elle-même.
     *
     * Le gabarit ne lit pas le détail des retenues ici : il prend les colonnes de
     * l'OP, puis retombe sur le document source quand la colonne est à zéro. C'est
     * cette retombe qui dérive — un avenant à la DA changeait le papier d'une OP déjà
     * visée. Le gel enregistre la valeur telle que le gabarit l'a calculée à
     * l'émission, priorité des colonnes comprise.
     */
    protected function lignesImpot(OrdonnancePaiement $op, ?Engagement $engagement, $source): array
    {
        $lignes = $this->lignesVide();

        if (!$engagement || !$source || $op->type_ordonnance !== 'impot') {
            return $lignes;
        }

        if ($engagement->estDecision()) {
            $lignes['ir']   = (float) ($op->montant_ir ?? 0);
            $lignes['irnc'] = (float) ($op->montant_irnc ?? 0);

            if ($lignes['ir'] === 0.0 && $lignes['irnc'] === 0.0) {
                $lignes['ir']   = (float) ($source->montant_ir ?? 0);
                $lignes['irnc'] = (float) ($source->montant_irnc ?? 0);
            }

            return $lignes;
        }

        if ($engagement->estBonCommande()) {
            $lignes['ir']  = (float) ($op->montant_ir ?? 0);
            $lignes['tsr'] = (float) ($op->montant_tsr ?? 0);

            if ($lignes['ir'] === 0.0) {
                $lignes['ir'] = (float) ($source->montant_ir ?? 0);
            }
            if ($lignes['tsr'] === 0.0) {
                $lignes['tsr'] = (float) ($source->montant_tsr ?? 0);
            }
        }

        return $lignes;
    }

    /** Les taux qui entrent dans les libellés imprimés, lus sur le document source. */
    protected function taux(?Engagement $engagement, $source): array
    {
        if (!$engagement || !$source) {
            return ['ir' => 0, 'irnc' => 0, 'tsr' => 0, 'tva' => 0];
        }

        return [
            'ir'    => (float) ($source->taux_ir ?? 0),
            'irnc'  => (float) ($source->taux_irnc ?? 0),
            'tsr'   => (float) ($source->taux_tsr ?? 0),
            'tva'   => (float) ($source->taux_tva ?? 0),
        ];
    }

    /**
     * La hiérarchie d'imputation, en valeurs brutes.
     *
     * Mêmes niveaux que les gabarits : chapitre / paragraphe / article sur la
     * nomenclature, programme → sous-programme → action → activité → tâche sur la
     * chaîne de la tâche. Les libellés se gèlent avec les codes : un re-libellé de
     * nomenclature est précisément une dérive de papier.
     */
    protected function hierarchie(?Engagement $engagement): array
    {
        $h = [
            'chapitre' => '', 'paragraphe' => '', 'article' => '',
            'programme' => '', 'sous_programme' => '', 'action' => '',
            'activite' => '', 'tache' => '',
            'libelle_chapitre' => '', 'libelle_paragraphe' => '', 'libelle_article' => '',
            'libelle_programme' => '', 'libelle_sous_programme' => '',
            'libelle_action' => '', 'libelle_activite' => '', 'libelle_tache' => '',
            'objectif' => '',
        ];

        $nomenclature = $engagement?->nomenclaturePrincipale;
        if (!$nomenclature) {
            return $h;
        }

        $code = (string) ($nomenclature->code ?? '');

        $h['paragraphe']        = $code;
        $h['libelle_paragraphe'] = (string) ($nomenclature->libelle ?? '');
        $h['chapitre']          = substr($code, 0, 2);
        $h['article']           = method_exists($nomenclature, 'getCodeArticle')
            ? (string) $nomenclature->getCodeArticle()
            : substr($code, 0, 4);

        $tache = $nomenclature->tache ?? $nomenclature->taches()->first();
        if (!$tache) {
            return $h;
        }

        $tache->load('activite.action.programme.parent');

        $activite   = $tache->activite;
        $action     = $activite?->action;
        $programme  = $action?->programme;
        $sousProgramme = null;

        if ($programme && $programme->estSousProgramme()) {
            $sousProgramme = $programme;
            $programme     = $programme->parent;
        }

        $h['tache']            = (string) ($tache->code ?? '');
        $h['libelle_tache']    = (string) ($tache->libelle ?? '');
        $h['activite']         = (string) ($activite?->code ?? '');
        $h['libelle_activite'] = (string) ($activite?->libelle ?? '');
        $h['action']           = (string) ($action?->code ?? '');
        $h['libelle_action']   = (string) ($action?->libelle ?? '');
        $h['programme']        = (string) ($programme?->code ?? '');
        $h['libelle_programme'] = (string) ($programme?->libelle ?? '');
        $h['sous_programme']   = (string) ($sousProgramme?->code ?? '');
        $h['libelle_sous_programme'] = (string) ($sousProgramme?->libelle ?? '');

        $h['objectif'] = (string) ($this->objectif($programme)?->libelle ?? '');

        return $h;
    }

    /** L'objectif principal du programme, tel que le gabarit va le chercher. */
    protected function objectif($programme)
    {
        if (!$programme) {
            return null;
        }

        try {
            if (method_exists($programme, 'objectifPrincipal')) {
                return $programme->objectifPrincipal;
            }

            if (method_exists($programme, 'objectifsPrincipaux')) {
                $objectifs = $programme->objectifsPrincipaux;

                return $objectifs instanceof \Illuminate\Support\Collection
                    ? $objectifs->first()
                    : $objectifs;
            }
        } catch (\Exception $e) {
            \Log::warning('Gel OP : objectif illisible', [
                'programme_id' => $programme->id,
                'error'        => $e->getMessage(),
            ]);
        }

        return null;
    }

    /** Le bénéficiaire tel que le papier le nomme : qui, où, ville. */
    protected function identite($beneficiaire): array
    {
        if (!$beneficiaire) {
            return [];
        }

        return [
            'type'    => $beneficiaire::class,
            'id'      => $beneficiaire->id,
            'nom'     => (string) ($beneficiaire->raison_sociale
                ?? $beneficiaire->nom_complet
                ?? $beneficiaire->name
                ?? ''),
            'adresse' => (string) ($beneficiaire->adresse ?? ''),
            'ville'   => (string) ($beneficiaire->ville ?? ''),
        ];
    }

    /** Le fournisseur ou le personnel du document source, comme le font les gabarits. */
    protected function beneficiaireSource(?Engagement $engagement, $source)
    {
        if (!$engagement || !$source) {
            return null;
        }

        if ($engagement->estBonCommande()) {
            return $source->fournisseur;
        }

        return $engagement->estDecision() ? $source->personnel : null;
    }

    protected function retenuesVide(): array
    {
        return array_fill_keys(self::CLES_RETENUES, 0.0);
    }

    /** Les lignes d'impôt que le gabarit tape, quand aucune ligne n'a été calculée. */
    protected function lignesVide(): array
    {
        return ['ir' => 0.0, 'irnc' => 0.0, 'tsr' => 0.0];
    }

    protected function avecTotal(array $d): array
    {
        $d['total'] = array_sum($d);

        return $d;
    }

    /** Solde les clés d'un état et dit s'il sort du gel. */
    protected function achever(array $etat): array
    {
        $etat['retenues'] = array_replace($this->retenuesVide(), $etat['retenues'] ?? []);
        $etat['lignes']   = array_replace($this->lignesVide(), $etat['lignes'] ?? []);

        if (!isset($etat['retenues']['total'])) {
            $etat['retenues']['total'] = array_sum($etat['retenues']);
        }

        $etat['gele'] ??= false;

        return $etat;
    }

    protected function etatVide(): array
    {
        return [
            'brut' => 0.0, 'net' => 0.0, 'retenu' => 0.0,
            'imputation' => 0.0, 'engage' => 0.0, 'banque' => 0.0, 'billetage' => 0.0,
            'retenues' => $this->retenuesVide(),
            'lignes' => $this->lignesVide(),
            'taux' => ['ir' => 0, 'irnc' => 0, 'tsr' => 0, 'tva' => 0],
            'hierarchie' => $this->hierarchie(null),
            'beneficiaire' => [], 'reverseur' => [], 'gele' => false,
        ];
    }
}
