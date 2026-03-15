{{-- resources/views/pdf/engagement/certificat-engagement.blade.php --}}

@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('footer_override')
    @include('pdf.partials.footer-engagement')
@endsection

@php
    $engagement = $donnees['_raw'];

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    $engagement->load(['nomenclaturePrincipale', 'beneficiaire', 'exercice']);

    $nomenclature = $engagement->nomenclaturePrincipale;

    // Récupérer les données du bon
    $bonCommande = $donnees['_raw'];
    $numeroBca = $donnees['numero_bca'] ?? ($bonCommande->numero ?? '.........');

    // Récupérer la ligne budgétaire
    $ligneBudgetaire = null;
    $dotationInitiale = 0;
    $disponibleAvant = 0;
    $disponibleApres = 0;

    if ($nomenclature) {
        // Récupérer la ligne budgétaire associée
        $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $engagement->budget_id)
            ->where('nomenclature_id', $nomenclature->id)
            ->first();

        if ($ligneBudgetaire) {
            // Dotation initiale
            $dotationInitiale = $ligneBudgetaire->budget_initial ?? ($ligneBudgetaire->montant_initial ?? 0);

            // Disponible AVANT engagement
            $disponibleAvant = $ligneBudgetaire->disponible_engagement ?? 0;

            // Disponible APRÈS engagement (disponible - montant engagé)
            $montantEngage = $engagement->montant_engage ?? 0;
            $disponibleApres = $disponibleAvant - $montantEngage;
        }
    }

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
                    // C'est un sous-programme
                $sousProgramme = $programme;
                $programme = $programme->parent;
            } else {
                // C'est un programme principal
                    $sousProgramme = null;
                }

                // Récupérer l'objectif
            try {
                if (method_exists($programme, 'objectifPrincipal')) {
                    $objectif = $programme->objectifPrincipal;
                } elseif (method_exists($programme, 'objectifsPrincipaux')) {
                    $objectifs = $programme->objectifsPrincipaux;
                    if ($objectifs instanceof \Illuminate\Support\Collection) {
                        $objectif = $objectifs->first();
                    } else {
                        $objectif = $objectifs;
                    }
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
@endphp

@section('title', 'Certificat d\'Engagement')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($engagement->montant_engage ?? 0) }}
@endsection

@section('additional_styles')
    <style>
        .doc-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin: 10px auto 20px auto;
            padding: 6px 12px;
            border: 1px solid #000;
            display: inline-block;
            text-decoration: none;
        }

        .section-title {
            font-weight: bold;
            font-size: 9pt;
            margin: 10px 0 6px 0;
        }

        .info-line {
            margin: 6px 0;
            font-size: 11pt;
            line-height: 1.3;
        }

        .info-line strong {
            font-weight: bold;
        }

        .hierarchie-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
            font-size: 9.5pt;
        }

        .hierarchie-table th,
        .hierarchie-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        .hierarchie-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            width: 20%;
        }
    </style>
@endsection

@section('content')
    {{-- Titre --}}
    <div class="doc-title doc-title-wrapper">
        CERTIFICAT D'ENGAGEMENT
    </div>

    <div class="info-line">
        <strong>Type d'engagement:</strong> {{ $engagement->type_engagement }} -
        N°
        @if ($engagement->type_engagement === 'BC')
            {{ $bonCommande->numero ?? 'N/A' }}
        @elseif($engagement->type_engagement === 'DA')
            {{ $decisionAdministrative->numero ?? 'N/A' }}
        @else
            {{ $engagement->engageable->numero ?? 'N/A' }}
        @endif
    </div>

    {{-- Introduction --}}
    <div class="info-line">
        <strong>Imputation budgétaire de l'engagement </strong>: BUDGET PROGRAMME DU <strong>{{ $parametres->sigle }}
        </strong>DE L'EXERCICE
        <strong> {{ $engagement->exercice }} </strong>
    </div>

    @if ($ligneBudgetaire)
        <div class="info-line">
            <strong>DOTATION INITIALE:</strong> {{ number_format($dotationInitiale, 0, ',', ' ') }} F CFA
        </div>

        <div class="info-line">
            <strong>Montant disponible:</strong> {{ number_format($disponibleAvant, 0, ',', ' ') }} F CFA
            <span class="info-line" style="font-size:9px; font-style:italic">
                (Avant engagement)
            </span>
        </div>

        <div class="info-line">
            Une Autorisation d'Engagement d'un montant de :
        </div>

        <div class="info-line">
            <strong>Montant TTC de l'engagement (en chiffres):</strong>
            {{ number_format($engagement->montant_engage, 0, ',', ' ') }} F
            CFA
            <div class="info-line">
                <strong>En lettres:</strong> @yield('montant_lettres')
            </div>
        </div>

        <div class="info-line">
            <strong>Nouveau montant disponible :</strong>
            <span style="{{ $disponibleApres < 0 ? 'color: red; font-weight: bold;' : '' }}">
                {{ number_format($disponibleApres, 0, ',', ' ') }} F CFA
            </span>
            <span class="info-line" style="font-size:9px; font-style:italic">
                (Après engagement)
            </span>
            @if ($disponibleApres < 0)
                <span style="color: red; font-size: 8pt;"> (⚠️ Dépassement)</span>
            @endif
        </div>
    @else
        <div class="info-line" style="color: red;">
            ⚠️ Ligne budgétaire non trouvée
        </div>
    @endif

    {{-- Réservation --}}
    <div class="info-line">
        Est réservée pour l'acte Administratif ci-après:
    </div>

    <div class="info-line">
        <strong>Référence:</strong> {{ $numeroBca ?? 'BON DE COMMANDE' }}
    </div>

    <div class="info-line">
        <strong>Date d'émission:</strong>
        {{ $engagement->date_engagement ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y') : '.....................' }}
    </div>

    <div class="info-line">
        <strong>Signataire:</strong> {{ $parametres->nom_ordonnateur ?? 'N/A' }}
    </div>

    <div class="info-line">
        <strong>OBJET:</strong> {{ $engagement->objet }}
    </div>

    {{-- ✅ CORRIGER ICI - Utiliser la variable calculée --}}
    <div class="info-line">
        <strong>BENEFICIAIRE:</strong> {{ $nomBeneficiaire }}
    </div>

    {{-- Imputation --}}
    <div class="info-line">
        Cette autorisation d'Engagement est imputée de la manière suivante:
    </div>

    @if ($nomenclature)
        <div class="info-line">
            <strong>CHAPITRE:</strong> {{ substr($nomenclature->code, 0, 2) }}
        </div>

        <div class="info-line">
            <strong>PARAGRAPHE/COMPTE/CODE:</strong> ({{ $nomenclature->code }}) - {{ $nomenclature->libelle }}
        </div>
    @endif

    {{-- Tableau hiérarchique --}}
    <table class="hierarchie-table">
        {{-- ✅ CORRECTION : Afficher sous-programme OU programme (priorité au sous-programme) --}}
        @if ($sousProgramme)
            {{-- Si sous-programme existe, afficher SEULEMENT le sous-programme --}}
            <tr>
                <th>SOUS-PROGRAMME:</th>
                <td>{{ $sousProgramme->code }} - {{ $sousProgramme->libelle }}</td>
            </tr>
        @elseif ($programme)
            {{-- Si PAS de sous-programme, afficher le programme --}}
            <tr>
                <th>PROGRAMME:</th>
                <td>{{ $programme->code }} - {{ $programme->libelle }}</td>
            </tr>
        @endif

        {{-- ✅ Chaque ligne s'affiche seulement si données existent --}}
        @if ($nomenclature)
            <tr>
                <th>ARTICLE:</th>
                <td>{{ $nomenclature->getCodeArticle() }}</td>
            </tr>
        @endif

        @if ($objectif)
            <tr>
                <th>OBJECTIF:</th>
                <td>{{ $objectif->libelle }}</td>
            </tr>
        @endif

        @if ($action)
            <tr>
                <th>ACTION:</th>
                <td>{{ $action->libelle }}</td>
            </tr>
        @endif

        @if ($activite)
            <tr>
                <th>ACTIVITÉ:</th>
                <td>{{ $activite->libelle }}</td>
            </tr>
        @endif

        @if ($tache)
            <tr>
                <th>TÂCHE:</th>
                <td>{{ $tache->libelle }}</td>
            </tr>
        @endif
    </table>
@endsection
