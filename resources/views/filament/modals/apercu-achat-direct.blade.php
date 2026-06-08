@php
    $lignes = $depense->lignes;

    // ✅ Totaux = somme des valeurs déjà arrondies (comme affichées dans chaque colonne)
    $totalHt = 0;
    $totalTva = 0;
    $totalTtc = 0;
    $totalIr = 0;
    $totalNap = 0;

    foreach ($lignes as $ligne) {
        $totalHt += (int) round((float) ($ligne->montant_ht ?? 0), 0);
        $totalTva += (int) round((float) ($ligne->montant_tva ?? 0), 0);
        $totalTtc += (int) round((float) ($ligne->montant_ttc ?? 0), 0);
        $totalIr += (int) round((float) ($ligne->montant_ir ?? 0), 0);
        $totalNap += (int) round((float) ($ligne->montant_net ?? 0), 0);
    }

    $statut = match ($depense->statut) {
        'brouillon' => ['label' => 'Brouillon', 'color' => '#6b7280'],
        'valide' => ['label' => 'Validé', 'color' => '#f59e0b'],
        'paye' => ['label' => 'Payé', 'color' => '#10b981'],
        'annule' => ['label' => 'Annulé', 'color' => '#ef4444'],
        default => ['label' => $depense->statut, 'color' => '#6b7280'],
    };

    $fournisseur = $depense->fournisseur?->raison_sociale
        ?? $depense->fournisseur_libre
        ?? '—';

    $params = \App\Models\ParametresStructure::where('actif', true)->first();
    $logoPath = null;
    $logoExists = false;
    if ($params?->logo) {
        $logoPath = public_path('storage/' . ltrim($params->logo, '/'));
        $logoExists = file_exists($logoPath);
    }
@endphp

<div style="font-family: Arial, sans-serif; font-size: 9pt; color: #1e293b; padding: 8px;">

    {{-- ══ EN-TÊTE ══ --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
        <tr>
            <td style="width:20%; vertical-align:top; font-size:7.5pt;">
                <strong>REPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                <span style="font-size:6.5pt;">MINISTERE DE LA SANTE PUBLIQUE</span>
            </td>
            <td style="width:60%; text-align:center; vertical-align:top;">
                @if($logoExists)
                    <img src="{{ $logoPath }}" style="height:38px; margin-bottom:3px;"><br>
                @endif
                <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">
                    {{ $params?->nom_complet ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE' }}
                </div>
                <div style="font-size:8pt; font-style:italic;">
                    {{ $params?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL' }}
                </div>
                <div style="font-size:8.5pt; font-weight:bold; margin-top:4px; text-transform:uppercase;">
                    ACHAT DIRECT — RÉGIE D'AVANCE
                </div>
            </td>
            <td style="width:20%; vertical-align:top; text-align:right; font-size:7.5pt;">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                <span style="font-size:6.5pt;">MINISTRY OF PUBLIC HEALTH</span>
            </td>
        </tr>
    </table>

    <hr style="border:1px solid #1e293b; margin:8px 0;">

    {{-- ══ TITRE + STATUT ══ --}}
    <table style="width:100%; margin-bottom:10px;">
        <tr>
            <td style="font-size:12pt; font-weight:bold; text-decoration:underline;">
                ACHAT DIRECT N° {{ $depense->numero ?? '—' }}
            </td>
            <td style="text-align:right;">
                <span style="
                    background:{{ $statut['color'] }};
                    color:#fff;
                    padding:3px 10px;
                    border-radius:4px;
                    font-size:8.5pt;
                    font-weight:bold;
                ">
                    {{ $statut['label'] }}
                </span>
            </td>
        </tr>
    </table>

    {{-- ══ INFORMATIONS GÉNÉRALES ══ --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:10px; font-size:8.5pt;">
        <tr>
            <td style="width:50%; padding:3px 0;">
                <strong>Date :</strong>
                {{ $depense->date_depense?->format('d/m/Y') ?? '—' }}
            </td>
            <td style="width:50%; padding:3px 0;">
                <strong>Régie :</strong>
                {{ $depense->regieAvance?->numero }} —
                {{ $depense->regieAvance?->libelle }}
            </td>
        </tr>
        <tr>
            <td style="padding:3px 0;">
                <strong>Fournisseur :</strong> {{ $fournisseur }}
            </td>
            <td style="padding:3px 0;">
                <strong>Nomenclature :</strong>
                {{ $depense->ligneRegieAvance?->nomenclature?->code ?? '—' }}
                — {{ $depense->ligneRegieAvance?->nomenclature?->libelle ?? '—' }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:3px 0;">
                <strong>Objet :</strong> {{ $depense->objet }}
            </td>
        </tr>
        @if($depense->provisionLigneRegie)
            <tr>
                <td colspan="2" style="padding:3px 0;">
                    <strong>Tranche provision :</strong>
                    {{ $depense->provisionLigneRegie?->decaissement?->libelle_tranche ?? '—' }}
                </td>
            </tr>
        @endif
    </table>

    {{-- ══ TABLEAU DES LIGNES ══ --}}
    <div style="font-weight:bold; font-size:8.5pt; margin-bottom:4px;">
        Détail des dépenses
    </div>

    <table style="
        width:100%;
        border-collapse:collapse;
        font-size:8pt;
        margin-bottom:10px;
    ">
        <thead>
            <tr style="background:#e2e8f0;">
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:left; width:4%;">N°</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:left; width:32%;">Désignation</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:center; width:6%;">Qté</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:center; width:6%;">TVA%</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:center; width:6%;">IR%</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right; width:12%;">MHT</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right; width:10%;">TVA</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right; width:10%;">TTC</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right; width:10%;">IR</th>
                <th style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right; width:14%;">NAP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $index => $ligne)
                <tr style="{{ $index % 2 === 0 ? 'background:#f8fafc;' : '' }}">
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:center;">
                        {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px;">
                        {{ $ligne->nature_depense }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:center;">
                        {{ number_format($ligne->quantite, 2, ',', ' ') }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:center;">
                        {{ number_format($ligne->taux_tva, 2, ',', ' ') }}%
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:center;">
                        {{ number_format($ligne->taux_ir, 2, ',', ' ') }}%
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:right;">
                        {{ number_format($ligne->montant_ht, 0, ',', ' ') }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:right;">
                        {{ number_format($ligne->montant_tva, 0, ',', ' ') }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:right; font-weight:bold;">
                        {{ number_format($ligne->montant_ttc, 0, ',', ' ') }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:right; color:#b45309;">
                        {{ number_format($ligne->montant_ir, 0, ',', ' ') }}
                    </td>
                    <td style="border:1px solid #cbd5e1; padding:4px; text-align:right;
                                                font-weight:bold; color:#047857;">
                        {{ number_format($ligne->montant_net, 0, ',', ' ') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="border:1px solid #cbd5e1; padding:8px;
                                                            text-align:center; color:#94a3b8;">
                        Aucune ligne de dépense
                    </td>
                </tr>
            @endforelse
        </tbody>
        @php
            $seuil = (float) (optional(\App\Models\ParametresStructure::where('actif', true)->first())
                ->seuil_achat_direct_regie ?? 500000);
            $depasse = $totalTtc >= $seuil;
        @endphp

        @if($depasse)
            <div style="
                    background:#fef2f2;
                    border:2px solid #ef4444;
                    border-radius:6px;
                    padding:10px 14px;
                    margin:10px 0;
                    color:#991b1b;
                    font-weight:bold;
                    font-size:9pt;
                ">
                🚫 ATTENTION : Le montant TTC ({{ number_format($totalTtc, 0, ',', ' ') }} FCFA)
                dépasse le seuil achat direct ({{ number_format($seuil, 0, ',', ' ') }} FCFA).
                Cet achat ne peut pas être validé — utilisez un BCR/BCM.
            </div>
        @else
            <div style="
                    background:#f0fdf4;
                    border:1px solid #86efac;
                    border-radius:6px;
                    padding:8px 14px;
                    margin:10px 0;
                    color:#166534;
                    font-size:8.5pt;
                ">
                ✅ Montant conforme au seuil achat direct
                (&lt; {{ number_format($seuil, 0, ',', ' ') }} FCFA TTC)
            </div>
        @endif

        {{-- Ligne totaux --}}
        <tfoot>
            <tr style="background:#f1f5f9; font-weight:bold;">
                <td colspan="5" style="border:1px solid #cbd5e1; padding:5px 4px;
                                       text-align:right;">
                    TOTAUX
                </td>
                <td style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right;">
                    {{ number_format($totalHt, 0, ',', ' ') }}
                </td>
                <td style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right;">
                    {{ number_format($totalTva, 0, ',', ' ') }}
                </td>
                <td style="border:1px solid #cbd5e1; padding:5px 4px; text-align:right;">
                    {{ number_format($totalTtc, 0, ',', ' ') }}
                </td>
                <td style="border:1px solid #cbd5e1; padding:5px 4px;
                            text-align:right; color:#b45309;">
                    {{ number_format($totalIr, 0, ',', ' ') }}
                </td>
                <td style="border:1px solid #cbd5e1; padding:5px 4px;
                            text-align:right; color:#047857;">
                    {{ number_format($totalNap, 0, ',', ' ') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- ══ ARRÊTÉ ══ --}}
    <div style="
        text-align:center;
        font-weight:bold;
        font-size:9pt;
        border:1px solid #cbd5e1;
        border-radius:4px;
        padding:8px;
        margin-bottom:12px;
        background:#f0fdf4;
        color:#065f46;
    ">
        Arrêté le présent achat direct à la somme nette de :
        <span style="font-size:10pt;">
            {{ \App\Helpers\NombreEnLettres::montantCFA($totalNap) }}
        </span>
    </div>

    {{-- ══ SIGNATURES ══ --}}
    <table style="width:100%; border-collapse:collapse; margin-top:20px;">
        <tr>
            <td style="width:33%; text-align:center; vertical-align:top; padding:4px;">
                <div style="font-weight:bold; font-size:8pt;">Le Régisseur</div>
                <div style="border:1px solid #cbd5e1; min-height:55px;
                            margin-top:4px; border-radius:3px;"></div>
                <div style="font-size:7.5pt; margin-top:4px; color:#475569;">
                    {{ $depense->regieAvance?->responsable?->name ?? '—' }}
                </div>
            </td>
            <td style="width:33%; text-align:center; vertical-align:top; padding:4px;">
                <div style="font-weight:bold; font-size:8pt;">
                    Le Contrôleur Financier
                </div>
                <div style="border:1px solid #cbd5e1; min-height:55px;
                            margin-top:4px; border-radius:3px;"></div>
            </td>
            <td style="width:33%; text-align:center; vertical-align:top; padding:4px;">
                <div style="font-weight:bold; font-size:8pt;">L'Agent Comptable</div>
                <div style="border:1px solid #cbd5e1; min-height:55px;
                            margin-top:4px; border-radius:3px;"></div>
            </td>
        </tr>
    </table>

    {{-- ══ PIED DE PAGE ══ --}}
    <div style="
        margin-top:12px;
        padding-top:6px;
        border-top:1px dashed #cbd5e1;
        font-size:7pt;
        color:#94a3b8;
        display:flex;
        justify-content:space-between;
    ">
        <span>Aperçu généré le {{ now()->format('d/m/Y à H:i') }}</span>
        <span>{{ $params?->sigle ?? '' }} — Achat Direct {{ $depense->numero ?? '' }}</span>
        <span>Statut : {{ $statut['label'] }}</span>
    </div>
</div>