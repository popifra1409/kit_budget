{{-- resources/views/pdf/templates/certificat-engagement-impot.blade.php --}}
{{-- Variante CE avec bénéficiaire + Receveur si impôts/taxes --}}

@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
@include('pdf.partials.footer-engagement')
@endsection

@php
$engagement = $donnees['_raw'];
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

$engagement->load(['nomenclaturePrincipale', 'beneficiaire', 'exercice', 'engageable']);

$nomenclature = $engagement->nomenclaturePrincipale;

$numeroBca = $engagement->reference_document
?? $engagement->engageable?->numero
?? '—';

// ── Ligne budgétaire ──────────────────────────────────
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

$totalEngageAvant = \App\Models\Engagement::where('budget_id', $ligneBudgetaire->budget_id)
->where('nomenclature_principale_id', $ligneBudgetaire->nomenclature_id)
->where('id', '!=', $engagement->id)
->whereIn('statut', ['provisoire', 'definitif'])
->sum('montant_engage');

$disponibleAvant = $budgetRectifie - $totalEngageAvant;
$montantEngage = (float) ($engagement->montant_engage ?? 0);
$disponibleApres = $disponibleAvant - $montantEngage;
}
}

// ── Hiérarchie budgétaire ─────────────────────────────
$tache = $activite = $action = $programme = $sousProgramme = $objectif = null;

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
}
}
}
}

// ── Bénéficiaire principal ────────────────────────────
$nomBeneficiaire = $engagement->getNomBeneficiaire() ?? 'N/A';

// ── Détection impôts/taxes ────────────────────────────
$montantTaxes = 0;
$detailTaxes = [];
$aDesImpots = false;

if ($engagement->engageable) {
$donneesSrc = $engagement->extraireDonneesDocument();

if ($engagement->estBonCommande()) {
$montantTaxes = ($donneesSrc['montant_ir'] ?? 0)
+ ($donneesSrc['montant_tva'] ?? 0)
+ ($donneesSrc['montant_tsr'] ?? 0);

if (($donneesSrc['montant_ir'] ?? 0) > 0) $detailTaxes[] = 'IR : ' . number_format($donneesSrc['montant_ir'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['montant_tva'] ?? 0) > 0) $detailTaxes[] = 'TVA : ' . number_format($donneesSrc['montant_tva'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['montant_tsr'] ?? 0) > 0) $detailTaxes[] = 'TSR : ' . number_format($donneesSrc['montant_tsr'], 0, ',', ' ') . ' FCFA';

} elseif ($engagement->estDecision()) {
$montantTaxes = ($donneesSrc['montant_cnps'] ?? 0)
+ ($donneesSrc['montant_irnc'] ?? 0)
+ ($donneesSrc['montant_tva'] ?? 0)
+ ($donneesSrc['montant_redevance'] ?? 0)
+ ($donneesSrc['montant_feicom'] ?? 0)
+ ($donneesSrc['autres_retenues'] ?? 0);

if (($donneesSrc['montant_cnps'] ?? 0) > 0) $detailTaxes[] = 'CNPS : ' . number_format($donneesSrc['montant_cnps'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['montant_irnc'] ?? 0) > 0) $detailTaxes[] = 'IRNC : ' . number_format($donneesSrc['montant_irnc'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['montant_tva'] ?? 0) > 0) $detailTaxes[] = 'TVA : ' . number_format($donneesSrc['montant_tva'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['montant_redevance'] ?? 0) > 0) $detailTaxes[] = 'Redevance : ' . number_format($donneesSrc['montant_redevance'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['montant_feicom'] ?? 0) > 0) $detailTaxes[] = 'FEICOM : ' . number_format($donneesSrc['montant_feicom'], 0, ',', ' ') . ' FCFA';
if (($donneesSrc['autres_retenues'] ?? 0) > 0) $detailTaxes[] = 'Autres : ' . number_format($donneesSrc['autres_retenues'], 0, ',', ' ') . ' FCFA';
}

$aDesImpots = $montantTaxes > 0;
}
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

    .beneficiaire-bloc {
        /*border: 1px solid #000;*/
        padding: 4px 6px;
        margin: 4px 0;
        font-size: 9pt;
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

{{-- Type engagement --}}
<div class="info-line">
    <strong>Type d'engagement:</strong>
    @if ($engagement->estBonCommande())
    {{ $engagement->engageable?->typeEngagement?->libelle ?? $engagement->type_engagement }}
    @elseif ($engagement->estDecision())
    {{ $engagement->engageable?->typeDecision?->libelle ?? $engagement->type_engagement }}
    @else
    {{ $engagement->type_engagement }}
    @endif
    - N°
    @if ($engagement->estBonCommande())
    {{ $engagement->engageable?->reference_document ?? $engagement->engageable?->numero ?? 'N/A' }}
    @else
    {{ $engagement->engageable?->numero ?? 'N/A' }}
    @endif
</div>

{{-- Introduction --}}
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
<div class="info-line"><strong>Référence :</strong> {{ $numeroBca }}</div>
<div class="info-line">
    <strong>Date d'émission :</strong>
    {{ $engagement->date_engagement
            ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y')
            : '.....................' }}
</div>
<div class="info-line"><strong>Signataire :</strong> {{ $parametres->nom_ordonnateur ?? 'N/A' }}</div>
<div class="info-line"><strong>OBJET :</strong> {{ $engagement->objet }}</div>

{{-- ✅ BÉNÉFICIAIRE — logique impôts --}}
@if ($aDesImpots)
{{-- Engagement avec impôts → Bénéficiaire principal + LE RECEVEUR --}}
<div class="info-line"><strong>BÉNÉFICIAIRES :</strong> {{ strtoupper($nomBeneficiaire) }} <strong>+ RECEVEUR</strong></div>

@else
{{-- Engagement sans impôts → Bénéficiaire seul --}}
<div class="info-line">
    <strong>BÉNÉFICIAIRE :</strong> {{ strtoupper($nomBeneficiaire) }}
</div>
@endif

{{-- Imputation --}}
<div class="info-line">Cette autorisation d'Engagement est imputée de la manière suivante :</div>

@if ($nomenclature)
<div class="info-line">
    <strong>CHAPITRE :</strong> {{ substr($nomenclature->code, 0, 2) }}
</div>
<div class="info-line">
    <strong>PARAGRAPHE/COMPTE/CODE :</strong> ({{ $nomenclature->code }}) - {{ $nomenclature->libelle }}
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