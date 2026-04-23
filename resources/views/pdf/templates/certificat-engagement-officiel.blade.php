{{-- resources/views/pdf/templates/certificat-engagement-officiel.blade.php --}}

@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
@include('pdf.partials.footer-engagement')
@endsection

@php
$engagement = $donnees['_raw'];
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

// ── Charger les relations ─────────────────────────────────
$engagement->loadMissing(['nomenclaturePrincipale', 'beneficiaire', 'exercice']);

// ✅ Charger engageable sans global scope (exercices clôturés 2025)
if ($engagement->engageable_type && $engagement->engageable_id
&& !$engagement->relationLoaded('engageable')) {
$modelClass = $engagement->engageable_type;
if ($engagement->estBonCommande()) {
$eng = $modelClass::withoutGlobalScope('exercice')
->with(['typeEngagement', 'fournisseur'])
->find($engagement->engageable_id);
} elseif ($engagement->estDecision()) {
$eng = $modelClass::withoutGlobalScope('exercice')
->with(['typeDecision', 'personnel', 'fournisseur'])
->find($engagement->engageable_id);
} else {
$eng = $modelClass::withoutGlobalScope('exercice')
->find($engagement->engageable_id);
}
$engagement->setRelation('engageable', $eng);
} else {
if ($engagement->estBonCommande() && $engagement->engageable)
$engagement->engageable->loadMissing(['typeEngagement', 'fournisseur']);
elseif ($engagement->estDecision() && $engagement->engageable)
$engagement->engageable->loadMissing(['typeDecision', 'personnel', 'fournisseur']);
}

// ── Type et numéro document ───────────────────────────────
if ($engagement->estBonCommande() && $engagement->engageable) {
$typeEngagement = \App\Models\TypeEngagement::find(
$engagement->engageable->type_engagement_id
);
$typeLibelle = $typeEngagement?->libelle ?? 'BON DE COMMANDE';
$numeroDoc = $engagement->engageable->numero ?? '—';
} elseif ($engagement->estDecision() && $engagement->engageable) {
$typeDecision = \App\Models\TypeDecision::find(
$engagement->engageable->type_decision_id
);
$typeLibelle = $typeDecision?->libelle ?? 'DÉCISION';
$numeroDoc = $engagement->engageable->numero ?? '—';
} else {
$typeLibelle = $engagement->type_engagement ?? 'ENGAGEMENT';
$numeroDoc = $engagement->reference_document ?? '—';
}

// ── Numéro engagement ─────────────────────────────────────
$numeroEngagement = $engagement->numero
?? \App\Models\Engagement::withoutGlobalScope('exercice')->find($engagement->id)?->numero
?? '—';

// ── Exercice ──────────────────────────────────────────────
$anneeExercice = \App\Models\Exercice::find($engagement->exercice_id)?->annee
?? $engagement->exercice
?? now()->year;

// ── Nomenclature ──────────────────────────────────────────
$nomenclature = $engagement->nomenclaturePrincipale;

// ── Ligne budgétaire ──────────────────────────────────────
$ligneBudgetaire = null;
$dotationInitiale = 0;
$disponibleAvant = 0;
$disponibleApres = 0;

if ($nomenclature) {
$ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $engagement->budget_id)
->where('nomenclature_id', $nomenclature->id)->first();

if ($ligneBudgetaire) {
$dotationInitiale = $ligneBudgetaire->budget_initial
?? $ligneBudgetaire->montant_initial ?? 0;

$budgetRectifie = $ligneBudgetaire->budget_rectifie
?? ($dotationInitiale
+ ($ligneBudgetaire->virements_entrants ?? 0)
- ($ligneBudgetaire->virements_sortants ?? 0));

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
if (method_exists($programme, 'objectifPrincipal'))
$objectif = $programme->objectifPrincipal;
} catch (\Exception $e) { $objectif = null; }
}
}
}

// ── Sous-programme — chiffre uniquement ───────────────────
$codeSousProgrammeBrut = $sousProgramme?->code ?? $programme?->code ?? '—';
$libelleSousProgramme = ($sousProgramme ?? $programme)?->libelle ?? '—';

if ($codeSousProgrammeBrut !== '—') {
$chiffresOnly = preg_replace('/[^0-9]/', '', $codeSousProgrammeBrut);
$codeSousProgramme = $chiffresOnly !== '' ? (int) $chiffresOnly : $codeSousProgrammeBrut;
} else {
$codeSousProgramme = '—';
}

// ── Article ───────────────────────────────────────────────
$codeArticle = $nomenclature?->getCodeArticle() ?? ($nomenclature?->code ?? '—');

// ── Bénéficiaire principal ────────────────────────────────
$nomBeneficiaire = $engagement->getNomBeneficiaire() ?? 'N/A';

// ── Détection taxes/impôts ────────────────────────────────
$totalTaxes = 0;
$aDesImpots = false;

if ($engagement->engageable) {
$donneesSrc = $engagement->extraireDonneesDocument();

if ($engagement->estBonCommande()) {
$totalTaxes = ((float)($donneesSrc['montant_ir'] ?? 0))
+ ((float)($donneesSrc['montant_tva'] ?? 0))
+ ((float)($donneesSrc['montant_tsr'] ?? 0));
} elseif ($engagement->estDecision()) {
$totalTaxes = ((float)($donneesSrc['montant_cnps'] ?? 0))
+ ((float)($donneesSrc['montant_irnc'] ?? 0))
+ ((float)($donneesSrc['montant_tva'] ?? 0))
+ ((float)($donneesSrc['montant_redevance'] ?? 0))
+ ((float)($donneesSrc['montant_feicom'] ?? 0))
+ ((float)($donneesSrc['autres_retenues'] ?? 0));
}

$aDesImpots = $totalTaxes > 0;
}

// ── Montant en lettres ────────────────────────────────────
$montantLettres = \App\Helpers\NombreEnLettres::montantCFA($engagement->montant_engage ?? 0);

// ── Référence chemin hiérarchique ─────────────────────────
$sigle = $parametres->sigle ?? 'CHUY';
$refChemin = "{$sigle}/DG/DRHF/SDFC/SBC/BBE";
@endphp

@section('title', 'Certificat d\'Engagement')

@section('additional_styles')
<style>
    /* ── Cadre principal ──────────────────────── */
    .cadre-principal {
        border: 1.5px solid #000;
        padding: 8px 10px;
        margin-top: 4px;
    }

    /* ── Titre certificat ─────────────────────── */
    .titre-certificat {
        text-align: center;
        font-size: 10.5pt;
        font-weight: bold;
        background: #e8e8e8;
        padding: 10px 4px;
        margin-bottom: 8px;
        margin-top: 10px;
        border: 1px solid #aaa;
    }

    /* ── Lignes d'info ────────────────────────── */
    .ligne-info {
        margin: 4px 0;
        font-size: 10pt;
        line-height: 1.6;
    }

    /* ── Ligne montant (label + valeur alignée droite) ── */
    .ligne-montant {
        display: table;
        width: 100%;
        margin: 3px 0;
    }

    .lm-label {
        display: table-cell;
        width: 58%;
        font-size: 10.5pt;
    }

    .lm-valeur {
        display: table-cell;
        width: 42%;
        font-weight: bold;
        font-size: 11pt;
        text-align: right;
        padding-right: 10px;
    }

    /* ── Blocs encadrés ───────────────────────── */
    .bloc-encadre {
        border: 1px solid #000;
        padding: 4px 6px;
        margin: 3px 0;
        font-size: 10pt;
        line-height: 1.4;
    }

    .label-sousligne {
        font-weight: bold;
        text-decoration: underline;
    }

    /* ── Tableau récapitulatif ────────────────── */
    .tableau-recap {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        font-size: 9.5pt;
    }

    .tableau-recap th {
        border: 1px solid #000;
        padding: 5px 4px;
        text-align: center;
        background: #d8d8d8;
        font-weight: bold;
        font-size: 9pt;
    }

    .tableau-recap td {
        border: 1px solid #000;
        padding: 8px 4px;
        text-align: center;
        font-size: 9.5pt;
    }

    /* ── Zone signature ───────────────────────── */
    .zone-signature {
        margin-top: 10px;
        text-align: right;
        padding-right: 15px;
        font-size: 9.5pt;
    }

    .zone-signature .ville-date {
        margin-bottom: 20px;
    }

    .zone-signature .titre-signature {
        font-weight: bold;
        font-size: 10pt;
    }
</style>
@endsection

@section('content')

{{-- ══ CADRE PRINCIPAL ═══════════════════════════════════════════════ --}}
<div class="cadre-principal">

    {{-- ── Titre ──────────────────────────────────────────────── --}}
    <div class="titre-certificat">
        CERTIFICAT D'ENGAGEMENT N°{{ $anneeExercice }}/{{ $numeroEngagement }}/{{ $refChemin }}
    </div>

    {{-- ── Type engagement et numéro document ────────────────── --}}
    <div class="ligne-info">
        Type d'engagement : <strong>{{ strtoupper($typeLibelle) }}</strong> N°
        <u>{{ $numeroDoc }}</u>
        &nbsp; {{ $refChemin }} du
        <u>{{ $engagement->date_engagement
            ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y')
            : '____________' }}</u>
    </div>

    {{-- ── Imputation budgétaire ──────────────────────────────── --}}
    <div class="ligne-info">
        Imputation budgétaire de l'engagement :
        <strong>BUDGET PROGRAMME DU {{ strtoupper($sigle) }} DE L'EXERCICE {{ $anneeExercice }}</strong>
    </div>

    {{-- ── Montants alignés à droite ──────────────────────────── --}}
    @if ($ligneBudgetaire)
    <div class="ligne-montant">
        <div class="lm-label">Dotation initiale :</div>
        <div class="lm-valeur">{{ number_format($dotationInitiale, 0, ',', ' ') }}</div>
    </div>
    <div class="ligne-montant">
        <div class="lm-label">Montant disponible sur la ligne :</div>
        <div class="lm-valeur">{{ number_format($disponibleAvant, 0, ',', ' ') }}</div>
    </div>
    <div class="ligne-montant">
        <div class="lm-label">Montant de l'engagement TTC (en chiffres) :</div>
        <div class="lm-valeur">{{ number_format($engagement->montant_engage, 0, ',', ' ') }}</div>
    </div>
    <div class="ligne-montant">
        <div class="lm-label">Montant disponible à nouveau :</div>
        <div class="lm-valeur" style="{{ $disponibleApres < 0 ? 'color:red;' : '' }}">
            {{ number_format($disponibleApres, 0, ',', ' ') }}
            @if($disponibleApres < 0)
                <span style="font-size:7pt;">(⚠️)</span>
                @endif
        </div>
    </div>
    @else
    <div class="ligne-info" style="color:red;">⚠️ Ligne budgétaire non trouvée</div>
    @endif

    {{-- ── Montant en lettres ──────────────────────────────────── --}}
    <div class="ligne-info">
        Montant de l'engagement TTC (en lettres) :
        <strong>{{ strtoupper($montantLettres) }}.</strong>
    </div>

    {{-- ── OBJET ───────────────────────────────────────────────── --}}
    <div class="bloc-encadre">
        <span class="label-sousligne">OBJET :</span>
        {{ strtoupper($engagement->objet) }}
    </div>

    {{-- ── BÉNÉFICIAIRE ────────────────────────────────────────── --}}
    {{-- ✅ Si impôts > 0 → ajouter "ET LE RECEVEUR DES IMPÔTS" --}}
    <div class="bloc-encadre">
        <span class="label-sousligne">BÉNÉFICIAIRE :</span>
        {{ strtoupper($nomBeneficiaire) }}
        @if ($aDesImpots)
        ET LE RECEVEUR DES IMPÔTS
        @endif
    </div>

    {{-- ── SOUS-PROGRAMME ─────────────────────────────────────── --}}
    @if ($sousProgramme || $programme)
    <div class="bloc-encadre">
        <span class="label-sousligne">SOUS-PROGRAMME :</span>
        ({{ $codeSousProgramme }}) {{ strtoupper($libelleSousProgramme) }}
    </div>
    @endif

    {{-- ── ARTICLE/SECTION ────────────────────────────────────── --}}
    @if ($nomenclature)
    <div class="bloc-encadre">
        <span class="label-sousligne">ARTICLE/SECTION :</span>
        ({{ $codeArticle }})
    </div>

    {{-- ── PARAGRAPHE/COMPTE/CODE ─────────────────────────── --}}
    <div class="bloc-encadre">
        <span class="label-sousligne">PARAGRAPHE/COMPTE/CODE :</span>
        ({{ $nomenclature->code }}) {{ strtoupper($nomenclature->libelle) }}
    </div>
    @endif

    {{-- ── TABLEAU RÉCAPITULATIF ───────────────────────────────── --}}
    <table class="tableau-recap">
        <thead>
            <tr>
                <th>ANNÉE</th>
                <th>SOUS-PROGRAMME</th>
                <th>ARTICLE / SECTION</th>
                <th>PARAGRAPHE / COMPTE / CODE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $anneeExercice }}</td>
                <td>{{ $codeSousProgramme }}</td>
                <td>{{ $codeArticle }}</td>
                <td>{{ $nomenclature?->code ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── SIGNATURE ───────────────────────────────────────────── --}}
    <!-- <div class="zone-signature">
        <div class="ville-date">Yaoundé, le ___________________</div>
        <div class="titre-signature">
            {{ $parametres->titre_ordonnateur ?? 'Signature de l\'Ordonnateur' }}
        </div>
        <div style="height:35px;"></div>
        <div>{{ $parametres->nom_ordonnateur ?? '' }}</div>
    </div> -->

</div>{{-- fin cadre-principal --}}

@endsection