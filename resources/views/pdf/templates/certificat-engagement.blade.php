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
            $dotationInitiale = $ligneBudgetaire->budget_initial
                ?? $ligneBudgetaire->montant_initial
                ?? 0;

            // Virements budgétaires
            $virementsEntrants = $ligneBudgetaire->virements_entrants ?? 0;
            $virementsSortants = $ligneBudgetaire->virements_sortants ?? 0;

            // Budget rectifié
            $budgetRectifie = $ligneBudgetaire->budget_rectifie
                ?? ($dotationInitiale + $virementsEntrants - $virementsSortants);

            // Total engagé AVANT cet engagement (exclure l'engagement actuel)
            $totalEngageAvant = \App\Models\Engagement::where('budget_id', $ligneBudgetaire->budget_id)
                ->where('nomenclature_principale_id', $ligneBudgetaire->nomenclature_id)
                ->where('id', '!=', $engagement->id)
                ->whereIn('statut', ['provisoire', 'definitif']) // ← exclure les annulés
                ->sum('montant_engage');

            // Disponible AVANT cet engagement
            $disponibleAvant = $budgetRectifie - $totalEngageAvant;

            // Montant de cet engagement
            $montantEngage = (float) ($engagement->montant_engage ?? 0);

            // Disponible APRÈS cet engagement
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
        @section('additional_styles')
                <style>
                /* ── Écrasement des marges master pour ce document ──  */

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

                /* ── Styles spécifiques certificat ────────────────── */
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
    </style>
@endsection

@section('content')
    {{-- Titre --}}
    <div class="doc-title doc-title-wrapper">
        CERTIFICAT D'ENGAGEMENT
    </div>

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
        @elseif ($engagement->estDecision())
            {{ $engagement->engageable?->numero ?? 'N/A' }}
        @else
            {{ $engagement->engageable?->numero ?? 'N/A' }}
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