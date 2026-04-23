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

// ── Ligne budgétaire ──────────────────────────────────────
$ligneBudgetaire = null;
$dotationInitiale = 0;
$disponibleAvant = 0;
$disponibleApres = 0;

if ($nomenclature) {
$ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $engagement->budget_id)
->where('nomenclature_id', $nomenclature->id)
->first();

if ($ligneBudgetaire) {
$dotationInitiale = $ligneBudgetaire->budget_initial
?? $ligneBudgetaire->montant_initial
?? 0;

$virementsEntrants = $ligneBudgetaire->virements_entrants ?? 0;
$virementsSortants = $ligneBudgetaire->virements_sortants ?? 0;

$budgetRectifie = $ligneBudgetaire->budget_rectifie
?? ($dotationInitiale + $virementsEntrants - $virementsSortants);

// ✅ withoutGlobalScope — inclure engagements tous exercices
$totalEngageAvant = \App\Models\Engagement::withoutGlobalScope('exercice')
->where('budget_id', $ligneBudgetaire->budget_id)
->where('nomenclature_principale_id', $ligneBudgetaire->nomenclature_id)
->where('id', '!=', $engagement->id)
->whereIn('statut', ['provisoire', 'definitif'])
->sum('montant_engage');

$disponibleAvant = $budgetRectifie - $totalEngageAvant;
$montantEngage = (float) ($engagement->montant_engage ?? 0);
$disponibleApres = $disponibleAvant - $montantEngage;
}
}

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
    <strong>DOTATION INITIALE :</strong> {{ number_format($dotationInitiale, 0, ',', ' ') }} F CFA
</div>

<div class="info-line">
    <strong>Montant disponible :</strong> {{ number_format($disponibleAvant, 0, ',', ' ') }} F CFA
    <span style="font-size:8px;font-style:italic;">(Avant engagement)</span>
</div>

<div class="info-line">
    Une Autorisation d'Engagement d'un montant de :
</div>

<div class="info-line">
    <strong>Montant TTC de l'engagement (en chiffres) :</strong>
    {{ number_format($engagement->montant_engage, 0, ',', ' ') }} F CFA
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

<div class="info-line">Cette autorisation d'Engagement est imputée de la manière suivante :</div>

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