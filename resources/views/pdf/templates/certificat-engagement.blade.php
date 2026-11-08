{{-- resources/views/pdf/engagement/certificat-engagement.blade.php --}}

@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
@include('pdf.partials.footer-engagement')
@endsection

@php
$engagement = $donnees['_raw'];

$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

// ✅ Charger toutes les relations nécessaires avec withoutGlobalScope
// pour les engagements des exercices clôturés (reports 2025→2026)
$engagement->loadMissing([
'nomenclaturePrincipale',
'beneficiaire',
'exercice',
]);

// ✅ Recharger l'engageable avec withoutGlobalScope si pas chargé
if ($engagement->engageable_id && !$engagement->relationLoaded('engageable')) {
if ($engagement->estBonCommande()) {
$engageable = \App\Models\BonCommande::withoutGlobalScope('exercice')
->with(['typeEngagement', 'fournisseur'])
->find($engagement->engageable_id);
$engagement->setRelation('engageable', $engageable);
} elseif ($engagement->estDecision()) {
$engageable = \App\Models\DecisionAdministrative::withoutGlobalScope('exercice')
->with(['typeDecision', 'personnel', 'fournisseur'])
->find($engagement->engageable_id);
$engagement->setRelation('engageable', $engageable);
}
} else {
// Charger les sous-relations manquantes
if ($engagement->estBonCommande() && $engagement->engageable) {
$engagement->engageable->loadMissing(['typeEngagement', 'fournisseur']);
} elseif ($engagement->estDecision() && $engagement->engageable) {
$engagement->engageable->loadMissing(['typeDecision', 'personnel', 'fournisseur']);
}
}

if ($engagement->estBonCommande() && $engagement->engageable) {

// ✅ Chargement direct — TypeEngagement n'a pas de global scope
$typeEngagement = \App\Models\TypeEngagement::find(
$engagement->engageable->type_engagement_id
);

$typeLibelle = $typeEngagement?->libelle
?? match($typeEngagement?->code ?? 'BC') {
'BC' => 'Bon de Commande Administratif',
'LC' => 'Lettre-Commande',
'MA' => 'Marché Public',
'DL' => 'Décompte Lettre-Commande',
'DM' => 'Décompte Marché',
default => 'Bon de Commande',
};

$numeroDoc = $engagement->engageable->numero ?? 'N/A';

} elseif ($engagement->estDecision() && $engagement->engageable) {

// ✅ Chargement direct — TypeDecision n'a pas de global scope
$typeDecision = \App\Models\TypeDecision::find(
$engagement->engageable->type_decision_id
);

$typeLibelle = $typeDecision?->libelle ?? 'Décision Administrative';
$numeroDoc = $engagement->engageable->numero ?? 'N/A';

} else {
// Fallback manuel
$typeLibelle = $engagement->reference_document
? 'Engagement Manuel'
: ($engagement->type_engagement ?? 'Engagement');
$numeroDoc = $engagement->reference_document ?? 'N/A';
}

// ✅ Numéro engagement — toujours disponible même pour exercice 2025
$numeroEngagement = $engagement->numero
?? \App\Models\Engagement::withoutGlobalScope('exercice')->find($engagement->id)?->numero
?? 'N/A';

$nomenclature = $engagement->nomenclaturePrincipale;

// ── Données du bon ────────────────────────────────────────
$numeroBca = $donnees['numero_bca'] ?? $numeroDoc ?? '—';

// ── Ligne budgétaire et montants ──────────────────────────
// L'état imprimé est celui du jour de l'engagement (source unique, partagée avec le
// certificat officiel et l'ordonnance) : ni le budget rectifié d'aujourd'hui, ni les
// seuls collectifs de dépense — les virements y passaient aussi, à leur date.
$etatLigne = app(\App\Services\Budget\HistoriqueLigneBudgetaireService::class)
    ->etatPourEngagement($engagement);

$ligneBudgetaire   = $etatLigne['ligne'];
$dotationInitiale  = $etatLigne['dotation_initiale'];
$budgetRectifie    = $etatLigne['dotation_finale'];
$collectifsLigne   = $etatLigne['modifications'];
$virementsEntrants = $etatLigne['virements_entrants'];
$virementsSortants = $etatLigne['virements_sortants'];
$disponibleAvant   = $etatLigne['disponible_avant'];
$disponibleApres   = $etatLigne['disponible_apres'];
$montantEngage     = (float) ($engagement->montant_engage ?? 0);


        // ── Hiérarchie budgétaire ─────────────────────────────────
        $tache = null;
        $activite = null;
        $action = null;
        $programme = null;
        $sousProgramme = null;
        $objectif = null;

        if ($nomenclature) {
        $tache = $nomenclature->tache ?? $nomenclature->taches()->first();

        if ($tache) {
        $tache->load('activite.action.programme.parent');

        $activite = $tache->activite;
        $action = $activite?->action;
        $programme = $action?->programme;

        if ($programme) {
        if ($programme->estSousProgramme()) {
        $sousProgramme = $programme;
        $programme = $programme->parent;
        } else {
        $sousProgramme = null;
        }

        try {
        if (method_exists($programme, 'objectifPrincipal')) {
        $objectif = $programme->objectifPrincipal;
        } elseif (method_exists($programme, 'objectifsPrincipaux')) {
        $objectifs = $programme->objectifsPrincipaux;
        $objectif = $objectifs instanceof \Illuminate\Support\Collection
        ? $objectifs->first()
        : $objectifs;
        }
        } catch (\Exception $e) {
        \Log::warning('Erreur récupération objectif', [
        'programme_id' => $programme->id,
        'error' => $e->getMessage(),
        ]);
        $objectif = null;
        }
        }
        }
        }

        $nomBeneficiaire = $engagement->getNomBeneficiaire() ?? 'N/A';

        // ✅ Exercice de l'engagement — pas celui du document source
        $anneeExerciceEngagement = \App\Models\Exercice::find($engagement->exercice_id)?->annee
        ?? $engagement->exercice
        ?? now()->year;

        @endphp

        @section('title', 'Certificat d\'Engagement')

        @section('montant_lettres')
        {{ \App\Helpers\NombreEnLettres::montantCFA($engagement->montant_engage ?? 0) }}
        @endsection

        @section('additional_styles')
        <style>
            .doc-title-wrapper {
                margin: 6px 0 8px 0 !important;
            }

            .doc-title {
                margin: 4px 0 8px 0 !important;
                padding: 4px 0 !important;
                font-size: 10pt !important;
            }

            .bas-page {
                margin-top: 20px !important;
            }

            .section-title {
                font-weight: bold;
                font-size: 8.5pt;
                margin: 6px 0 4px 0;
            }

            .info-line {
                margin: 3px 0;
                font-size: 9pt;
                line-height: 1.3;
            }

            .info-line strong {
                font-weight: bold;
            }

            /* ── Notice snapshot ─────────────────────── */
            .snapshot-notice {
                font-size: 7pt;
                color: #555;
                font-style: italic;
                margin: 2px 0 4px 0;
                padding: 2px 5px;
                border-left: 2px solid #999;
                background: #f5f5f5;
            }

            .hierarchie-table {
                width: 100%;
                margin: 5px 0;
                border-collapse: collapse;
                font-size: 8.5pt;
            }

            .hierarchie-table th,
            .hierarchie-table td {
                border: 1px solid #000;
                padding: 3px 5px;
                text-align: left;
                vertical-align: top;
                line-height: 1.3;
            }

            .hierarchie-table th {
                background-color: #f0f0f0;
                font-weight: bold;
                width: 22%;
            }
        </style>
        @endsection

        @section('content')

        {{-- Titre --}}
        <div class="doc-title doc-title-wrapper">CERTIFICAT D'ENGAGEMENT</div>

        {{-- ✅ Type et numéro du document source --}}
        <div class="info-line">
            <strong>Type d'engagement :</strong> {{ $typeLibelle }} - N° {{ $numeroDoc }}
        </div>

        {{-- ✅ Numéro de l'engagement --}}
        <div class="info-line">
            <strong>N° Engagement :</strong> {{ $numeroEngagement }}
        </div>

        {{-- Imputation budgétaire --}}
        <div class="info-line">
            <strong>Imputation budgétaire de l'engagement</strong> : BUDGET PROGRAMME DU
            <strong>{{ $parametres->sigle }}</strong> DE L'EXERCICE
            <strong>{{ $engagement->exercice }}</strong>
        </div>

        @if ($ligneBudgetaire)

        <div class="info-line">
            <strong>DOTATION INITIALE :</strong>
            {{ number_format($dotationInitiale, 0, ',', ' ') }} F CFA
        </div>

        {{-- Les mouvements qui ont touché la dotation tiennent sur une ligne. Le certificat
             autorise un engagement, il n'est pas l'état des mouvements de la ligne : rendu
             ligne à ligne, six collectifs mangeaient le tiers de la page. Rien n'est perdu
             pour autant — le détail reste dans le gel (`snapshot_modifications`) et s'imprime
             sur l'état des mouvements de crédits. --}}
        @if ($collectifsLigne->isNotEmpty())
            @php
                $nb    = $collectifsLigne->count();
                $plus  = (float) $collectifsLigne->where('montant', '>=', 0)->sum('montant');
                $moins = (float) $collectifsLigne->where('montant', '<', 0)->sum('montant');
                $net   = $plus + $moins;

                $origines  = $collectifsLigne->pluck('origine')->unique()->values();
                // mb_strtoupper et non strtoupper : « Collectif budgétaire » passe par là,
                // et l'uppercase byte par byte cassait l'accent du titre imprimé.
                $libelle   = match (true) {
                    $nb === 1 => mb_strtoupper($origines->first()),
                    $origines->count() === 1 => ['Collectif budgétaire' => 'COLLECTIFS BUDGÉTAIRES',
                        'Virement budgétaire' => 'VIREMENTS BUDGÉTAIRES'][$origines->first()]
                        ?? mb_strtoupper($origines->first()),
                    default => 'COLLECTIFS ET VIREMENTS BUDGÉTAIRES',
                };

                $references = $collectifsLigne->pluck('reference')->filter()->unique()->values();
                $jours      = $collectifsLigne->pluck('date')->filter()
                    ->sortBy(fn($d) => $d->timestamp)->values();

                $parties = [$nb.' opération'.($nb > 1 ? 's' : '')];
                if ($references->count() === 1) {
                    $parties[] = $references->first();
                } elseif ($references->count() > 1) {
                    $parties[] = $references->count().' références';
                }
                if ($jours->isNotEmpty()) {
                    $parties[] = $jours->first()->toDateString() === $jours->last()->toDateString()
                        ? 'du '.$jours->first()->format('d/m/Y')
                        : 'du '.$jours->first()->format('d/m/Y').' au '.$jours->last()->format('d/m/Y');
                }

                $mouvements = array_values(array_filter([
                    $plus  > 0 ? '+'.number_format($plus, 0, ',', ' ').' F CFA' : null,
                    $moins < 0 ? number_format($moins, 0, ',', ' ').' F CFA' : null,
                ], fn($t) => $t !== null));
                if ($mouvements === []) {
                    // Mouvements soldés à zéro : la ligne reste, sinon le « soit » pend dans
                    // le vide et le lecteur croit à une impression cassée.
                    $mouvements = [number_format($net, 0, ',', ' ').' F CFA'];
                }

                $solde = ($net > 0 ? '+' : '').number_format($net, 0, ',', ' ').' F CFA';

                // Les lignes en contrepartie sont la justification du mouvement : un virement
                // sans destinataire ne dit plus rien. Agrégées par sens et bornées, elles
                // tiennent en queue de la même ligne, en petit caractère.
                $codes = function (bool $entrant) use ($collectifsLigne) {
                    return $collectifsLigne
                        ->filter(fn($m) => $m['contrepartie']
                            && ($entrant ? $m['montant'] >= 0 : $m['montant'] < 0))
                        ->pluck('contrepartie')->unique()->values();
                };
                $bornir = function ($liste) {
                    return $liste->count() > 6
                        ? $liste->take(6)->implode(', ').', +'.($liste->count() - 6).' autres'
                        : $liste->implode(', ');
                };
                $contreparties = array_values(array_filter([
                    $codes(true)  ->isNotEmpty() ? 'depuis '.$bornir($codes(true)) : null,
                    $codes(false) ->isNotEmpty() ? 'vers '.$bornir($codes(false)) : null,
                ], fn($t) => $t !== null));
            @endphp

            {{-- Le « soit » ne sert que quand les deux sens se partagent la ligne : à un
                 seul signe, le répéter ferait croire à deux montants différents. La ligne
                 reste d'un tenant — une coupure avant la virgule s'imprime telle quelle. --}}
            <div class="info-line">
                <strong>{{ $libelle }}</strong> ({{ implode(' · ', $parties) }}) : {{ implode(' · ', $mouvements) }}@if ($plus > 0 && $moins < 0), soit <strong>{{ $solde }}</strong>@endif@if ($contreparties !== []) <span style="font-size:8px;font-style:italic;">— {{ implode(' · ', $contreparties) }}</span>@endif
            </div>

            <div class="info-line">
                <strong>DOTATION APRÈS MODIFICATIONS :</strong>
                {{ number_format($dotationInitiale + $net, 0, ',', ' ') }} F CFA
            </div>
        @endif

        <div class="info-line">
            <strong>Montant disponible :</strong>
            {{ number_format($disponibleAvant, 0, ',', ' ') }} F CFA
            <span style="font-size:8px;font-style:italic;">(Avant engagement)</span>
        </div>

        <div class="info-line">
            Une Autorisation d'Engagement d'un montant de :
        </div>

        <div class="info-line">
            <strong>Montant TTC de l'engagement (en chiffres) :</strong>
            {{ number_format($montantEngage, 0, ',', ' ') }} F CFA
        </div>

        <div class="info-line">
            <strong>En lettres :</strong> @yield('montant_lettres')
        </div>

        <div class="info-line">
            <strong>Nouveau montant disponible :</strong>
            <span style="{{ $disponibleApres < 0 ? 'color:red;font-weight:bold;' : '' }}">
                {{ number_format($disponibleApres, 0, ',', ' ') }} F CFA
            </span>
            <span style="font-size:8px;font-style:italic;">(Après engagement)</span>
            @if ($disponibleApres < 0)
                <span style="color:red;font-size:8pt;"> (⚠️ Dépassement)</span>
                @endif
        </div>

        @else
        <div class="info-line" style="color:red;">⚠️ Ligne budgétaire non trouvée</div>
        @endif

        <div class="info-line">Est réservée pour l'acte Administratif ci-après :</div>

        <div class="info-line">
            <strong>Référence :</strong> {{ $numeroBca }}
        </div>

        <div class="info-line">
            <strong>Date d'émission :</strong>
            {{ $engagement->date_engagement
        ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y')
        : '.....................' }}
        </div>

        <div class="info-line">
            <strong>Signataire :</strong> {{ $parametres->nom_ordonnateur ?? 'N/A' }}
        </div>

        <div class="info-line">
            <strong>OBJET :</strong> {{ $engagement->objet }}
        </div>

        <div class="info-line">
            <strong>BÉNÉFICIAIRE :</strong> {{ $nomBeneficiaire }}
        </div>

        <div class="info-line">
            Cette autorisation d'Engagement est imputée de la manière suivante :
        </div>

        @if ($nomenclature)
        <div class="info-line">
            <strong>CHAPITRE :</strong> {{ substr($nomenclature->code, 0, 2) }}
        </div>
        <div class="info-line">
            <strong>PARAGRAPHE/COMPTE/CODE :</strong>
            ({{ $nomenclature->code }}) - {{ $nomenclature->libelle }}
        </div>
        @endif

        {{-- Tableau hiérarchique --}}
        <table class="hierarchie-table">
            @if ($sousProgramme)
            <tr>
                <th>SOUS-PROGRAMME :</th>
                <td>{{ $sousProgramme->code }} - {{ $sousProgramme->libelle }}</td>
            </tr>
            @elseif ($programme)
            <tr>
                <th>PROGRAMME :</th>
                <td>{{ $programme->code }} - {{ $programme->libelle }}</td>
            </tr>
            @endif

            @if ($nomenclature)
            <tr>
                <th>ARTICLE :</th>
                <td>{{ $nomenclature->getCodeArticle() }}</td>
            </tr>
            @endif

            @if ($objectif)
            <tr>
                <th>OBJECTIF :</th>
                <td>{{ $objectif->libelle }}</td>
            </tr>
            @endif

            @if ($action)
            <tr>
                <th>ACTION :</th>
                <td>{{ $action->libelle }}</td>
            </tr>
            @endif

            @if ($activite)
            <tr>
                <th>ACTIVITÉ :</th>
                <td>{{ $activite->libelle }}</td>
            </tr>
            @endif

            @if ($tache)
            <tr>
                <th>TÂCHE :</th>
                <td>{{ $tache->libelle }}</td>
            </tr>
            @endif
        </table>

        @endsection