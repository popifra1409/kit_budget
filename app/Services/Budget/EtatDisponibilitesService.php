<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcul unique de l'"Etat des disponibilites budgetaires", utilise par
 * l'export PDF ET l'export Excel (ils ne peuvent donc pas diverger).
 *
 * Regles (alignees sur le reste de l'application — voir les references) :
 *  - Engage          = somme des `lignes_engagement.montant` de la nomenclature de la ligne,
 *                      pour les engagements emis au plus tard a la date limite (reference :
 *                      `LigneBudgetaire::requeteEngagementsActifs()`). Un engagement sans
 *                      ligne de repartition est reporte sur sa nomenclature principale pour le
 *                      montant de son en-tete : l'argent est engage, il ne doit pas disparaitre.
 *  - Budget rectifie = initial + virements EXECUTES dates <= J - virements sortants <= J
 *                      + mouvements de collectifs ADOPTES dates <= J, non annules
 *                      (reference : `LigneBudgetaire::getBudgetRectifieReel()`, avec la coupure
 *                      chronologique en plus). Il n'est plus lu dans la colonne stockee : la
 *                      colonne dit l'etat d'AUJOURD'HUI, pas celui de la date demandee.
 *  - Ordonne         = somme des OP « standard » non annulees emises <= J, au brut quand il est
 *                      saisi (> 0) sinon au net — regle unique sur
 *                      `OrdonnancePaiement::montantBrutOuNet()`, lue aussi par
 *                      `Engagement::suiviExecution()` pour que les deux ecrans ne puissent plus
 *                      diverger sur la meme OP.
 *                      L'ancienne regle — le montant engage entier des qu'une OP existe —
 *                      sur-estimait l'ordonne de 177 693 381,83 FCFA sur 220 engagements du
 *                      BUDGET PRIMITIF 2026 (constate 2026-11-15).
 *  - Paye            = OP « standard » AU STATUT PAYEE, payees <= J, au NET.
 *  - Taxes reversees = OP « impot » payees <= J, au NET. Le net est garde ici, et pour les deux
 *                      colonnes : le brut d'une OP standard comprend la retenue, qui est comptee
 *                      une deuxieme fois par l'OP « impot » — les sommer au brut les compterait
 *                      deux fois.
 *  - Tx Engagement   = Engage / Budget rectifie
 *  - Tx Ordonnanct.  = Ordonne / Engage
 *  - Tx Execution    = (Paye + Taxes reversees) / Budget rectifie
 *
 * Les statuts des virements et des collectifs sont lus tels qu'ils sont aujourd'hui : le
 * SIGB ne garde pas l'historique des statuts, un virement execute en octobre n'a donc pas
 * ete compte pour juin (`date_virement <= J`), et un mouvement annule n'est compte que si
 * l'annulation est posterieure a J.
 *
 * Chaque engagement etant rattache aux lignes de sa repartition, la somme des engagements
 * affiches sous une ligne est egale a l'engage de la ligne.
 */
class EtatDisponibilitesService
{
    /** Colonnes additionnables (les taux sont recalcules, jamais additionnes). */
    public const MONTANTS = [
        'budgetInitial',
        'virementsEntrants',
        'virementsSortants',
        'budgetRectifie',
        'engage',
        'ordonne',
        'paye',
        'taxesReversees',
        'disponibleEng',
        'disponibleOrd',
    ];

    protected Carbon $jusquAu;

    public function construire(
        Budget $budget,
        bool $detaille = false,
        bool $engageesSeulement = false,
        ?int $programmeId = null,
        ?Carbon $jusquAu = null
    ): array {
        $this->jusquAu = ($jusquAu ?? now())->copy()->endOfDay();

        $lignes = $budget->lignesBudgetaires()->with('nomenclature')->get();

        // Une requete pour les engagements et leurs OP, une pour la repartition par ligne,
        // trois pour les mouvements qui font le budget rectifie : rien par ligne.
        $engagements = $this->chargerEngagements($budget);
        $repartitions = $this->repartitionsParEngagement($budget);
        $rectifies = $this->budgetsRectifiesAu($lignes);

        $parNomenclature = $this->imputerEngagements($engagements, $repartitions);

        // Arbre de rattachement resolu pour TOUTES les lignes en six requetes.
        $classifications = LigneBudgetaire::classificationsStrategiques($lignes);

        $groupes = [];
        $totalGeneral = $this->totalVide();
        $nbLignes = 0;
        $nbEngagements = 0;
        $nbOpEmises = 0;
        $nbOpPayees = 0;
        $libelleProgrammeFiltre = null;

        foreach ($lignes as $ligne) {
            $engagementsLigne = $parNomenclature
                ->get((int) $ligne->nomenclature_id, collect())
                ->values();

            $c = $this->calculerLigne($ligne, $engagementsLigne, $rectifies[(int) $ligne->id] ?? null);

            if ($engageesSeulement && $c['engage'] <= 0) {
                continue;
            }

            $classification = $classifications->get((int) $ligne->id) ?? [];
            $programme = $classification['programme'] ?? null;
            $action = $classification['action'] ?? null;
            // Le sous-programme du rapport est celui de l'EP (`actions.sous_programme_ep_id`),
            // pas celui de la tutelle : `programmes.niveau = 'sous_programme'` ne compte
            // AUCUNE ligne en 2026, et le sous-total qui en dependait ne se rendait donc jamais.
            $sousProgramme = $action?->sousProgrammeEp;

            if ($programmeId && $programme?->id !== $programmeId) {
                continue;
            }

            $cleP = $programme ? 'p' . $programme->id : 'non_affecte';
            $cleSp = $sousProgramme ? 'sp' . $sousProgramme->id : 'sans';
            $cleA = $action ? 'a' . $action->id : 'sans';

            $groupes[$cleP] ??= [
                'programme'    => $programme,
                'libelle'      => $programme ? $this->intitule($programme) : 'NON AFFECTÉ À UN PROGRAMME',
                'ordre'        => $programme?->code ?? 'ZZZZ', // "non affecte" en dernier
                'sous_groupes' => [],
                'total'        => $this->totalVide(),
            ];

            $groupes[$cleP]['sous_groupes'][$cleSp] ??= [
                'sous_programme' => $sousProgramme,
                'libelle'        => $sousProgramme ? $this->intitule($sousProgramme) : null,
                'ordre'          => $sousProgramme?->code ?? 'ZZZZ',
                'actions'        => [],
                'total'          => $this->totalVide(),
            ];

            $groupes[$cleP]['sous_groupes'][$cleSp]['actions'][$cleA] ??= [
                'action'  => $action,
                'libelle' => $action ? $this->intitule($action) : null,
                'ordre'   => $action?->code ?? 'ZZZZ',
                'lignes'  => [],
                'total'   => $this->totalVide(),
            ];

            $groupes[$cleP]['sous_groupes'][$cleSp]['actions'][$cleA]['lignes'][] = [
                'ligne_id'    => (int) $ligne->id,
                'code'        => $ligne->nomenclature?->code ?? '',
                'libelle'     => $ligne->nomenclature?->libelle ?? '',
                'c'           => $c,
                'engagements' => $detaille ? $engagementsLigne->all() : [],
            ];

            $this->ajouter($groupes[$cleP]['sous_groupes'][$cleSp]['actions'][$cleA]['total'], $c);
            $this->ajouter($groupes[$cleP]['sous_groupes'][$cleSp]['total'], $c);
            $this->ajouter($groupes[$cleP]['total'], $c);
            $this->ajouter($totalGeneral, $c);

            $nbLignes++;
            $nbEngagements += $engagementsLigne->count();
            $nbOpEmises += $engagementsLigne->sum(fn(array $e) => $e['nb_op']);
            $nbOpPayees += $engagementsLigne->sum(fn(array $e) => $e['nb_op_payees']);
            $libelleProgrammeFiltre ??= $programmeId ? $groupes[$cleP]['libelle'] : null;
        }

        // Engagement dont aucune imputation n'atteint une ligne du budget : pas d'ecran pour le
        // montrer, mais pas le droit de le perdre sans le dire.
        $enLigne = $lignes->pluck('nomenclature_id')->map(fn($v) => (int) $v)->flip();
        $nonImputes = ['nb' => 0, 'engage' => 0.0, 'ordonne' => 0.0, 'lignes' => []];
        foreach ($parNomenclature as $nomenclatureId => $details) {
            if (isset($enLigne[(int) $nomenclatureId])) {
                continue;
            }
            foreach ($details as $d) {
                $nonImputes['nb']++;
                $nonImputes['engage'] += $d['engage'];
                $nonImputes['ordonne'] += $d['ordonne'];
                $nonImputes['lignes'][] = $d;
            }
        }

        // Tri, sous-totaux et decision d'afficher chaque sous-total, du plus profond vers le
        // plus large. Codes compares en ordre naturel : « 413-A2 » doit precoder « 413-A10 »,
        // que strcmp mettrait a l'envers.
        $nbSousProgrammes = 0;
        $nbActions = 0;

        foreach ($groupes as &$groupe) {
            foreach ($groupe['sous_groupes'] as &$sousGroupe) {
                foreach ($sousGroupe['actions'] as &$groupeAction) {
                    usort($groupeAction['lignes'], fn($a, $b) => $this->comparerCodes($a['code'], $b['code']));
                    $groupeAction['total'] = $this->finaliser($groupeAction['total']);

                    // Une ligne unique n'a rien a sous-totaliser ; un groupe sans action
                    // rapporte son argent au sous-total de son parent.
                    $groupeAction['afficher_total'] = $groupeAction['libelle'] !== null
                        && count($groupeAction['lignes']) > 1;
                }
                unset($groupeAction);

                usort($sousGroupe['actions'], fn($a, $b) => $this->comparerCodes($a['ordre'], $b['ordre']));
                $sousGroupe['actions'] = array_values($sousGroupe['actions']);
                $sousGroupe['total'] = $this->finaliser($sousGroupe['total']);
                $sousGroupe['afficher_total'] = $this->afficherTotal($sousGroupe['actions']);

                if ($sousGroupe['libelle']) {
                    $nbSousProgrammes++;
                }
                $nbActions += count(array_filter($sousGroupe['actions'], fn($a) => $a['libelle'] !== null));
            }
            unset($sousGroupe);

            usort($groupe['sous_groupes'], fn($a, $b) => $this->comparerCodes($a['ordre'], $b['ordre']));
            $groupe['sous_groupes'] = array_values($groupe['sous_groupes']);
            $groupe['total'] = $this->finaliser($groupe['total']);
            $groupe['afficher_total'] = $this->afficherTotal($groupe['sous_groupes']);
        }
        unset($groupe);

        uasort($groupes, fn($a, $b) => $this->comparerCodes($a['ordre'], $b['ordre']));

        return [
            'budget'             => $budget,
            'detaille'           => $detaille,
            'engagees_seulement' => $engageesSeulement,
            'programme_filtre'   => $libelleProgrammeFiltre,
            'jusquau'            => $this->jusquAu->copy()->startOfDay(),
            'groupes'            => array_values($groupes),
            'total'              => $this->finaliser($totalGeneral),
            'nb_lignes_affichees' => $nbLignes,
            'nb_lignes_budget'   => $lignes->count(),
            'budget_total'       => $totalGeneral['budgetRectifie'],
            'nb_engagements'     => $nbEngagements,
            'nb_sous_programmes' => $nbSousProgrammes,
            'nb_actions'         => $nbActions,
            'nb_op_emises'       => $nbOpEmises,
            'nb_op_payees'       => $nbOpPayees,
            'non_imputes'        => $nonImputes,
            // Le paiement n'est saisi nulle part dans beaucoup d'exercices : l'afficher en
            // clair vaut mieux que laisser croire que 0 % est un resultat de calcul.
            'aucun_paiement'     => $nbOpEmises > 0 && $nbOpPayees === 0,
        ];
    }

    // ────────────────────────────────────────────────────────────────

    protected function chargerEngagements(Budget $budget): Collection
    {
        $relations = [
            // OP annulees exclues (le statut 'annulee' est prevu par le reste de l'application)
            'ordonnancesPaiement' => fn($q) => $q->where('statut', '!=', 'annulee'),
        ];

        // Evite une requete par engagement pour le nom du beneficiaire, si la relation existe
        if (method_exists(Engagement::class, 'beneficiaire')) {
            $relations[] = 'beneficiaire';
        }

        return Engagement::withoutGlobalScope('exercice') // le budget_id suffit a cibler l'exercice
            ->where('budget_id', $budget->id)
            // Un engagement sans date de saisie ne doit pas disparaitre de l'etat quand on
            // remonte le temps : faute de date on ne peut prouver qu'il est posterieur a J.
            ->where(fn($q) => $q->whereNull('date_engagement')
                ->orWhereDate('date_engagement', '<=', $this->jusquAu))
            // Annule apres la date limite : l'engagement comptait encore a cette date
            ->where(fn($q) => $q->whereNull('date_annulation')
                ->orWhereDate('date_annulation', '>', $this->jusquAu))
            ->whereNotIn('statut', ['annule'])
            ->with($relations)
            ->orderBy('date_engagement')
            ->orderBy('numero')
            ->get();
    }

    /** engagement_id => [nomenclature_id => montant], tel que saisi par la repartition. */
    protected function repartitionsParEngagement(Budget $budget): array
    {
        $rows = DB::select(
            'select le.engagement_id, le.nomenclature_id, le.montant
               from lignes_engagement le
               join engagements e on e.id = le.engagement_id
              where e.budget_id = ? and e.deleted_at is null',
            [$budget->id]);

        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $out[(int) $r['engagement_id']][(int) $r['nomenclature_id']]
                = ($out[(int) $r['engagement_id']][(int) $r['nomenclature_id']] ?? 0)
                + (float) $r['montant'];
        }

        return $out;
    }

    /**
     * nomenclature_id => collection des details d'engagement imputes a cette nomenclature.
     * La repartition fait foi ; a defaut, l'en-tete de l'engagement est reporte sur sa
     * nomenclature principale.
     */
    protected function imputerEngagements(Collection $engagements, array $repartitions): Collection
    {
        $parNomenclature = collect();

        foreach ($engagements as $e) {
            $montants = $repartitions[$e->id] ?? [];

            if ($montants === []) {
                if (!$e->nomenclature_principale_id) {
                    continue; // ni repartition, ni nomenclature : rien a imputer
                }
                $montants = [(int) $e->nomenclature_principale_id => (float) $e->montant_engage];
            }

            foreach ($montants as $nomenclatureId => $montant) {
                $parNomenclature[$nomenclatureId] ??= collect();
                $parNomenclature[$nomenclatureId]->push($this->detailEngagement($e, (float) $montant));
            }
        }

        return $parNomenclature;
    }

    protected function detailEngagement(Engagement $e, float $montantLigne): array
    {
        // Une OP n'existe a la date demandee que si elle est emise ; le paiement en plus doit
        // etre date. Les OP chargees sont deja privees des annulees.
        $emises = $e->ordonnancesPaiement
            ->filter(fn($op) => $op->date_emission === null
                || $op->date_emission->lessThanOrEqualTo($this->jusquAu));
        $standard = $emises->where('type_ordonnance', 'standard');
        $payeesStandard = $standard->where('statut', 'payee')
            ->filter(fn($op) => $op->date_paiement !== null
                && $op->date_paiement->lessThanOrEqualTo($this->jusquAu));
        $impotsPayes = $emises->where('type_ordonnance', 'impot')->where('statut', 'payee')
            ->filter(fn($op) => $op->date_paiement !== null
                && $op->date_paiement->lessThanOrEqualTo($this->jusquAu));

        // L'engage de la ligne suit la repartition. Une OP n'est rattachee qu'a l'engagement
        // (aucune colonne de ligne dans `ordonnances_paiement`) : ses montants sont donc
        // repartis au prorata de la part de la ligne, sinon un engagement multi-lignes compterait
        // son ordonnance en totalite sur chacune de ses lignes.
        $part = (float) $e->montant_engage > 0
            ? $montantLigne / (float) $e->montant_engage
            : 1.0;

        $ordonne = $part * (float) $standard->sum(fn($op) => $op->montantBrutOuNet());
        $paye = $part * (float) $payeesStandard->sum(fn($op) => (float) $op->montant_net);
        $taxes = $part * (float) $impotsPayes->sum(fn($op) => (float) $op->montant_net);

        return [
            'numero'         => $e->numero,
            'date'           => $e->date_engagement ? Carbon::parse($e->date_engagement)->format('d/m/Y') : '',
            'beneficiaire'   => $e->getNomBeneficiaire() ?? '-',
            'objet'          => (string) ($e->objet ?? ''),
            'numeros_op'     => $standard->pluck('numero')->filter()->implode(', '),
            'statut'         => $e->statut,
            'engage'         => $montantLigne,
            'ordonne'        => $ordonne,
            'paye'           => $paye,
            'taxesReversees' => $taxes,
            'nb_op'          => $emises->count(),
            'nb_op_payees'   => $payeesStandard->count() + $impotsPayes->count(),
        ];
    }

    /**
     * ligne_id => ['initial', 'entrants', 'sortants', 'collectifs', 'rectifie'] a la date limite.
     * Trois agregats, quel que soit le nombre de lignes.
     */
    protected function budgetsRectifiesAu(Collection $lignes): array
    {
        $ligneIds = $lignes->pluck('id')->map(fn($id) => (int) $id)->all();

        if ($ligneIds === []) {
            return [];
        }

        $places = implode(',', array_fill(0, count($ligneIds), '?'));
        $fin = $this->jusquAu->toDateString();

        // Deux `?` de date puis la liste des lignes : meme forme dans les trois agregats.
        $sommes = function (string $sql) use ($ligneIds, $fin): array {
            $out = [];
            foreach (DB::select($sql, array_merge([$fin, $fin], $ligneIds)) as $r) {
                $r = (array) $r;
                $out[(int) $r['ligne_id']] = (float) $r['total'];
            }

            return $out;
        };

        $entrants = $sommes(
            "select ligne_destination_id as ligne_id, coalesce(sum(montant), 0) as total
               from virements_budgetaires
              where statut = 'execute' and date_virement::date <= ?
                and coalesce(date_annulation::date, '9999-12-31') > ?
                and ligne_destination_id in ($places)
              group by 1");

        $sortants = $sommes(
            "select ligne_source_id as ligne_id, coalesce(sum(montant), 0) as total
               from virements_budgetaires
              where statut = 'execute' and date_virement::date <= ?
                and coalesce(date_annulation::date, '9999-12-31') > ?
                and ligne_source_id in ($places)
              group by 1");

        $collectifs = $sommes(
            "select mc.ligne_depense_id as ligne_id, coalesce(sum(mc.montant_modification), 0) as total
               from mouvements_collectifs mc
               join collectifs_budgetaires c on c.id = mc.collectif_budgetaire_id
              where mc.type = 'depense'
                and coalesce(mc.statut, '') not in ('annule', 'annulee')
                and coalesce(mc.date_annulation::date, '9999-12-31') > ?
                and c.statut = 'adopte' and c.date_adoption::date <= ?
                and mc.ligne_depense_id in ($places)
              group by 1");

        $out = [];
        foreach ($lignes as $ligne) {
            $id = (int) $ligne->id;
            $initial = (float) ($ligne->budget_initial ?? 0);
            $e = $entrants[$id] ?? 0.0;
            $s = $sortants[$id] ?? 0.0;
            $m = $collectifs[$id] ?? 0.0;

            $out[$id] = [
                'initial'    => $initial,
                'entrants'   => $e,
                'sortants'   => $s,
                'collectifs' => $m,
                'rectifie'   => $initial + $e - $s + $m,
            ];
        }

        return $out;
    }

    protected function calculerLigne(LigneBudgetaire $ligne, Collection $engagements, ?array $rectifie): array
    {
        $c = [
            'budgetInitial'     => (float) ($ligne->budget_initial ?? 0),
            'virementsEntrants' => $rectifie['entrants'] ?? 0.0,
            'virementsSortants' => $rectifie['sortants'] ?? 0.0,
        ];

        // Le budget rectifie est recalcule a la date demandee, jamais lu tel quel : la colonne
        // stockee dit l'etat du jour. A la date du jour les deux tombent d'accord — c'est ce
        // que verifie le temoin.
        $c['budgetRectifie'] = $rectifie['rectifie']
            ?? ($c['budgetInitial'] + $c['virementsEntrants'] - $c['virementsSortants']);

        foreach (['engage', 'ordonne', 'paye', 'taxesReversees'] as $cle) {
            $c[$cle] = (float) $engagements->sum($cle);
        }

        $c['disponibleEng'] = $c['budgetRectifie'] - $c['engage'];
        $c['disponibleOrd'] = $c['budgetRectifie'] - $c['ordonne'];

        return $this->finaliser($c);
    }

    protected function finaliser(array $t): array
    {
        $br = $t['budgetRectifie'];

        $t['tauxEngagement']     = $br > 0 ? ($t['engage'] / $br) * 100 : 0;
        $t['tauxOrdonnancement'] = $t['engage'] > 0 ? ($t['ordonne'] / $t['engage']) * 100 : 0;
        $t['tauxExecution']      = $br > 0 ? (($t['paye'] + $t['taxesReversees']) / $br) * 100 : 0;

        return $t;
    }

    protected function ajouter(array &$total, array $c): void
    {
        foreach (self::MONTANTS as $cle) {
            $total[$cle] += $c[$cle];
        }
    }

    protected function totalVide(): array
    {
        return array_fill_keys(self::MONTANTS, 0.0);
    }

    /** Intitule d'un niveau de l'arbre : « CODE — libelle », le code pouvant manquer. */
    protected function intitule($modele): string
    {
        return $modele->code ? "{$modele->code} — {$modele->libelle}" : (string) $modele->libelle;
    }

    /**
     * Ordre naturel des codes : « 413-A2 » avant « 413-A10 ». Les niveaux sans code — les
     * groupes « sans rattachement », dont l'ordre est note ZZZZ — passent en dernier.
     */
    protected function comparerCodes(?string $a, ?string $b): int
    {
        return strnatcasecmp($a ?: 'ZZZZ', $b ?: 'ZZZZ');
    }

    /**
     * Un sous-total de niveau ne se rend que s'il apporte une information : soit plusieurs
     * enfants nommes (le total n'est plus la copie conforme d'un seul enfant), soit un enfant
     * unique SANS nom — le groupe « sans rattachement » doit porter son argent quelque part,
     * et son propre sous-total serait une ligne vide de sens.
     */
    protected function afficherTotal(array $enfants): bool
    {
        $nommes = array_filter($enfants, fn($e) => !empty($e['libelle']));

        return count($nommes) > 1 || ($nommes === [] && $enfants !== []);
    }
}
