{{-- resources/views/filament/forms/components/engagement-document-preview.blade.php --}}
{{-- ✅ Variables injectées directement par viewData() : $document et $type --}}

@if(!isset($document) || !$document)
<div style="padding:1rem;color:#6b7280;font-style:italic;">Aucun document sélectionné.</div>
@else
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:1rem;font-size:.875rem;">

    @if($type === 'bc')
    {{-- ═══ Aperçu BON DE COMMANDE ═══ --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
        <div>
            <span style="background:#dbeafe;color:#1d4ed8;font-weight:700;padding:.25rem .75rem;
                          border-radius:9999px;font-size:.8rem;">
                BON DE COMMANDE
            </span>
            <span style="font-weight:700;font-size:1rem;margin-left:.5rem;">
                {{ $document->numero }}
            </span>
        </div>
        <span style="background:#dcfce7;color:#15803d;padding:.25rem .75rem;
                      border-radius:9999px;font-size:.8rem;font-weight:600;">
            {{ strtoupper($document->statut) }}
        </span>
    </div>

    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="padding:.35rem .5rem;color:#64748b;width:30%;">Fournisseur</td>
            <td style="padding:.35rem .5rem;font-weight:600;">
                {{ $document->fournisseur?->raison_sociale ?? '—' }}
            </td>
            <td style="padding:.35rem .5rem;color:#64748b;width:20%;">Exercice</td>
            <td style="padding:.35rem .5rem;font-weight:600;">
                {{ $document->exercice?->annee ?? '—' }}
            </td>
        </tr>
        <tr style="background:#f1f5f9;">
            <td style="padding:.35rem .5rem;color:#64748b;">Service demandeur</td>
            <td style="padding:.35rem .5rem;">{{ $document->serviceDemandeur?->nom ?? '—' }}</td>
            <td style="padding:.35rem .5rem;color:#64748b;">Date émission</td>
            <td style="padding:.35rem .5rem;">
                {{ $document->date_emission?->format('d/m/Y') ?? '—' }}
            </td>
        </tr>
        <tr>
            <td style="padding:.35rem .5rem;color:#64748b;">Objet</td>
            <td style="padding:.35rem .5rem;" colspan="3">{{ $document->objet ?? '—' }}</td>
        </tr>
    </table>

    {{-- Montants BC --}}
    <div style="margin-top:.75rem;display:flex;gap:.75rem;flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:110px;">
            <div style="color:#64748b;font-size:.75rem;">Montant HT</div>
            <div style="font-weight:700;color:#1e40af;">
                {{ number_format($document->montant_ht, 0, ',', ' ') }} FCFA
            </div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:110px;">
            <div style="color:#64748b;font-size:.75rem;">TVA</div>
            <div style="font-weight:700;color:#b45309;">
                {{ number_format($document->montant_tva, 0, ',', ' ') }} FCFA
            </div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:110px;">
            <div style="color:#64748b;font-size:.75rem;">IR</div>
            <div style="font-weight:700;color:#dc2626;">
                {{ number_format($document->montant_ir, 0, ',', ' ') }} FCFA
            </div>
        </div>
        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:130px;">
            <div style="color:#15803d;font-size:.75rem;">Montant TTC</div>
            <div style="font-weight:800;color:#15803d;font-size:1rem;">
                {{ number_format($document->montant_ttc, 0, ',', ' ') }} FCFA
            </div>
        </div>
        <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:130px;">
            <div style="color:#1d4ed8;font-size:.75rem;">Net à percevoir</div>
            <div style="font-weight:800;color:#1d4ed8;font-size:1rem;">
                {{ number_format($document->net_a_percevoir ?? 0, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>

    {{-- Lignes BC --}}
    @if($document->relationLoaded('lignes') && $document->lignes->count() > 0)
    <div style="margin-top:.75rem;">
        <div style="font-weight:600;margin-bottom:.4rem;color:#374151;">
            Lignes ({{ $document->lignes->count() }})
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
            <thead>
                <tr style="background:#e2e8f0;">
                    <th style="padding:.3rem .5rem;text-align:left;border:1px solid #cbd5e1;">Désignation</th>
                    <th style="padding:.3rem .5rem;text-align:right;border:1px solid #cbd5e1;">Qté</th>
                    <th style="padding:.3rem .5rem;text-align:right;border:1px solid #cbd5e1;">P.U HT</th>
                    <th style="padding:.3rem .5rem;text-align:right;border:1px solid #cbd5e1;">Montant HT</th>
                    <th style="padding:.3rem .5rem;text-align:left;border:1px solid #cbd5e1;">Nomenclature</th>
                </tr>
            </thead>
            <tbody>
                @foreach($document->lignes->take(5) as $ligne)
                <tr style="{{ $loop->odd ? 'background:#f8fafc' : '' }}">
                    <td style="padding:.3rem .5rem;border:1px solid #e2e8f0;">{{ $ligne->designation }}</td>
                    <td style="padding:.3rem .5rem;text-align:right;border:1px solid #e2e8f0;">
                        {{ number_format($ligne->quantite, 0, ',', ' ') }}
                    </td>
                    <td style="padding:.3rem .5rem;text-align:right;border:1px solid #e2e8f0;">
                        {{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}
                    </td>
                    <td style="padding:.3rem .5rem;text-align:right;border:1px solid #e2e8f0;font-weight:600;">
                        {{ number_format($ligne->montant_ht, 0, ',', ' ') }}
                    </td>
                    <td style="padding:.3rem .5rem;border:1px solid #e2e8f0;color:#64748b;">
                        {{ $ligne->nomenclature?->code ?? '—' }}
                    </td>
                </tr>
                @endforeach
                @if($document->lignes->count() > 5)
                <tr>
                    <td colspan="5" style="padding:.3rem .5rem;color:#6b7280;font-style:italic;
                                           text-align:center;border:1px solid #e2e8f0;">
                        … et {{ $document->lignes->count() - 5 }} ligne(s) supplémentaire(s)
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif

    @elseif($type === 'da')
    {{-- ═══ Aperçu DÉCISION ADMINISTRATIVE ═══ --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
        <div>
            <span style="background:#fef9c3;color:#a16207;font-weight:700;padding:.25rem .75rem;
                          border-radius:9999px;font-size:.8rem;">
                DÉCISION ADMINISTRATIVE
            </span>
            <span style="font-weight:700;font-size:1rem;margin-left:.5rem;">
                {{ $document->numero }}
            </span>
        </div>
        <span style="background:#dcfce7;color:#15803d;padding:.25rem .75rem;
                      border-radius:9999px;font-size:.8rem;font-weight:600;">
            {{ strtoupper($document->statut) }}
        </span>
    </div>

    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="padding:.35rem .5rem;color:#64748b;width:30%;">Bénéficiaire</td>
            <td style="padding:.35rem .5rem;font-weight:600;" colspan="3">
                {{ $document->personnel?->nom_complet
                    ?? $document->fournisseur?->raison_sociale
                    ?? '—' }}
            </td>
        </tr>
        <tr style="background:#f1f5f9;">
            <td style="padding:.35rem .5rem;color:#64748b;">Type décision</td>
            <td style="padding:.35rem .5rem;">{{ $document->typeDecision?->libelle ?? '—' }}</td>
            <td style="padding:.35rem .5rem;color:#64748b;width:20%;">Exercice</td>
            <td style="padding:.35rem .5rem;">{{ $document->exercice?->annee ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding:.35rem .5rem;color:#64748b;">Date décision</td>
            <td style="padding:.35rem .5rem;">
                {{ $document->date_decision?->format('d/m/Y') ?? '—' }}
            </td>
            <td style="padding:.35rem .5rem;color:#64748b;">Date effet</td>
            <td style="padding:.35rem .5rem;">
                {{ $document->date_effet?->format('d/m/Y') ?? '—' }}
            </td>
        </tr>
        <tr style="background:#f1f5f9;">
            <td style="padding:.35rem .5rem;color:#64748b;">Objet</td>
            <td style="padding:.35rem .5rem;" colspan="3">{{ $document->objet ?? '—' }}</td>
        </tr>
    </table>

    {{-- Montants DA --}}
    <div style="margin-top:.75rem;display:flex;gap:.75rem;flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:120px;">
            <div style="color:#64748b;font-size:.75rem;">Montant brut</div>
            <div style="font-weight:700;color:#1e40af;">
                {{ number_format($document->montant_brut, 0, ',', ' ') }} FCFA
            </div>
        </div>

        @if(($document->montant_cnps ?? 0) > 0)
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:100px;">
            <div style="color:#64748b;font-size:.75rem;">CNPS</div>
            <div style="font-weight:600;color:#b45309;">
                {{ number_format($document->montant_cnps, 0, ',', ' ') }} FCFA
            </div>
        </div>
        @endif

        @if(($document->montant_irnc ?? 0) > 0)
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:100px;">
            <div style="color:#64748b;font-size:.75rem;">IRNC</div>
            <div style="font-weight:600;color:#b45309;">
                {{ number_format($document->montant_irnc, 0, ',', ' ') }} FCFA
            </div>
        </div>
        @endif

        @if(($document->total_taxes ?? 0) > 0)
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:120px;">
            <div style="color:#64748b;font-size:.75rem;">Total retenues</div>
            <div style="font-weight:700;color:#dc2626;">
                {{ number_format($document->total_taxes, 0, ',', ' ') }} FCFA
            </div>
        </div>
        @endif

        <div style="background:#dcfce7;border:1px solid #86efac;border-radius:.375rem;
                    padding:.5rem .75rem;text-align:center;min-width:130px;">
            <div style="color:#15803d;font-size:.75rem;">Montant net</div>
            <div style="font-weight:800;color:#15803d;font-size:1rem;">
                {{ number_format($document->montant_net, 0, ',', ' ') }} FCFA
            </div>
        </div>
    </div>

    @endif

</div>
@endif