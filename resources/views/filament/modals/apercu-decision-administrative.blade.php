@php
    $da = $da ?? $record;
    $beneficiaire = $da->type_beneficiaire === 'personnel'
        ? $da->personnel
        : $da->fournisseur;
@endphp

<div class="p-1">
<style>
    .da-apercu { font-family: inherit; font-size: .85rem; }
    .da-section-title {
        font-size: .7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: #64748b;
        border-bottom: 2px solid #e2e8f0; padding-bottom: .3rem;
        margin: 1rem 0 .6rem;
    }
    .da-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem 1rem; margin-bottom: 1rem; }
    @media(max-width:640px) { .da-grid { grid-template-columns: 1fr 1fr; } }
    .da-item label { display: block; font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-bottom: .1rem; }
    .da-item span { font-size: .82rem; color: #1e293b; }
    .dark .da-item span { color: #e2e8f0; }
    .da-badge { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 600; }
    .da-montants { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; margin-bottom: 1rem; }
    .da-montant-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .6rem .8rem; text-align: right; }
    .dark .da-montant-card { background: #1e293b; border-color: #334155; }
    .da-montant-card .label { font-size: .65rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; }
    .da-montant-card .value { font-size: .95rem; font-weight: 700; color: #1e293b; }
    .dark .da-montant-card .value { color: #f1f5f9; }
    .da-montant-card.net .value { color: #166534; font-size: 1.05rem; }
    .da-avertissement { background: #fef9c3; border: 1px solid #fde047; border-radius: .5rem; padding: .6rem .8rem; font-size: .78rem; color: #854d0e; margin-bottom: .8rem; }
</style>

<div class="da-apercu">

    {{-- Avertissement --}}
    <div class="da-avertissement">
        ⚠️ <strong>Vérifiez attentivement toutes les informations.</strong>
        Une fois validée, la décision ne peut être modifiée qu'après annulation.
    </div>

    {{-- En-tête --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.8rem;">
        <div>
            <div style="font-size:1.1rem; font-weight:700; color:#1e40af;">
                {{ $da->numero ?? '—' }}
            </div>
            <div style="font-size:.75rem; color:#64748b;">
                {{ $da->typeDecision?->libelle ?? 'Décision Administrative' }}
                — {{ $da->date_decision?->format('d/m/Y') ?? '—' }}
            </div>
        </div>
        <span class="da-badge" style="background:#fef3c7; color:#92400e;">
            {{ ucfirst($da->statut ?? 'brouillon') }}
        </span>
    </div>

    {{-- Bénéficiaire --}}
    <div class="da-section-title">👤 Bénéficiaire</div>
    <div class="da-grid">
        <div class="da-item">
            <label>Type</label>
            <span>{{ $da->type_beneficiaire === 'personnel' ? '👤 Personnel' : '🏢 Fournisseur' }}</span>
        </div>
        <div class="da-item" style="grid-column: span 2">
            <label>Nom / Raison sociale</label>
            <span style="font-weight:600;">{{ $da->getNomCompletPersonnel() ?? '—' }}</span>
        </div>
        @if($da->type_beneficiaire === 'personnel')
        <div class="da-item">
            <label>Matricule</label>
            <span>{{ $da->personnel?->matricule ?? '—' }}</span>
        </div>
        <div class="da-item">
            <label>Fonction</label>
            <span>{{ $da->personnel?->fonction ?? '—' }}</span>
        </div>
        @else
        <div class="da-item">
            <label>N° Contribuable</label>
            <span>{{ $da->fournisseur?->numero_contribuable ?? '—' }}</span>
        </div>
        <div class="da-item">
            <label>Régime fiscal</label>
            <span>{{ $da->fournisseur?->regime_fiscal ?? '—' }}</span>
        </div>
        @endif
    </div>

    {{-- Informations budgétaires --}}
    <div class="da-section-title">💰 Informations budgétaires</div>
    <div class="da-grid">
        <div class="da-item">
            <label>Exercice</label>
            <span>{{ $da->exercice?->annee ?? now()->year }}</span>
        </div>
        <div class="da-item">
            <label>Budget</label>
            <span>{{ $da->budget?->libelle ?? '—' }}</span>
        </div>
        <div class="da-item">
            <label>Type d'engagement</label>
            <span>{{ $da->type_engagement ?? '—' }}</span>
        </div>
        {{-- ✅ Ligne d'imputation budgétaire --}}
            @php
                $nomenclature = null;
                $engDA = App\Models\Engagement::where('engageable_id', $da->id)
                    ->where('engageable_type', 'App\\Models\\DecisionAdministrative')
                    ->first();
                if ($engDA && $engDA->nomenclature_principale_id) {
                    $nomBudg = App\Models\NomenclatureBudgetaire::find($engDA->nomenclature_principale_id);
                    if ($nomBudg) {
                        $nomenclature = $nomBudg->code . ' — ' . Str::limit($nomBudg->libelle ?? '', 50);
                    }
                }
            @endphp
        <div class="da-item" style="grid-column: span 3">
            <label>📌 Ligne d'imputation budgétaire</label>
            <span style="font-weight:600; color:#1e40af;">
                {{ $nomenclature ?? "Sera définie lors de l'engagement budgétaire" }}
            </span>
        </div>
    </div>

    {{-- Objet --}}
    @if($da->objet)
    <div class="da-section-title">📋 Objet</div>
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:.4rem; padding:.6rem .8rem; margin-bottom:1rem; font-size:.82rem;">
        {{ $da->objet }}
    </div>
    @endif

    {{-- Montants --}}
    <div class="da-section-title">💵 Montants</div>
    <div class="da-montants">
        <div class="da-montant-card">
            <div class="label">Montant Brut</div>
            <div class="value">{{ number_format($da->montant_brut ?? 0, 0, ',', ' ') }} <small>FCFA</small></div>
        </div>
        <div class="da-montant-card">
            <div class="label">TVA</div>
            <div class="value">{{ number_format($da->montant_tva ?? 0, 0, ',', ' ') }} <small>FCFA</small></div>
        </div>
        <div class="da-montant-card">
            <div class="label">IR retenu</div>
            <div class="value" style="color:#991b1b;">{{ number_format($da->montant_ir ?? 0, 0, ',', ' ') }} <small>FCFA</small></div>
        </div>
        <div class="da-montant-card net">
            <div class="label">Net à Payer</div>
            <div class="value">{{ number_format($da->montant_net ?? 0, 0, ',', ' ') }} <small>FCFA</small></div>
        </div>
    </div>

    {{-- Mémoire lié --}}
    @php
        $memoire = \App\Models\MemoireDepense::where('decision_administrative_id', $da->id)->first();
    @endphp
    @if($memoire)
    <div class="da-section-title">📄 Mémoire de dépense lié</div>
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:.4rem; padding:.6rem .8rem; margin-bottom:.8rem; font-size:.82rem;">
        📄 <strong>{{ $memoire->numero }}</strong>
        — Statut : {{ ucfirst($memoire->statut) }}
        — Montant TTC : {{ number_format($memoire->montant_ttc ?? 0, 0, ',', ' ') }} FCFA
    </div>
    @endif

    {{-- Observations --}}
    @if($da->observations)
    <div class="da-section-title">📝 Observations</div>
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:.4rem; padding:.6rem .8rem; font-size:.82rem; color:#475569;">
        {{ $da->observations }}
    </div>
    @endif

</div>
</div>