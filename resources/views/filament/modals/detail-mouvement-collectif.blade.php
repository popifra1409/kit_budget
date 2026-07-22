<div class="space-y-3 p-2" style="font-size:.82rem;">

    {{-- Type et montant --}}
    <div style="display:flex; justify-content:space-between; align-items:center;
        background:#eff6ff; padding:.6rem .8rem; border-radius:.4rem; border:1px solid #bfdbfe;">
        <span style="font-weight:700; color:#1e3a5f;">
            {{ match($record->type) {
                'depense'  => '💰 Dépense',
                'recette'  => '📥 Recette',
                'virement' => '↔️ Virement',
                default    => ucfirst($record->type)
            } }}
        </span>
        <span style="font-size:1rem; font-weight:700; color:#166534;">
            {{ number_format($record->montant_modification, 0, ',', ' ') }} FCFA
        </span>
    </div>

    {{-- Virement --}}
    @if($record->type === 'virement')
    <table style="width:100%; border-collapse:collapse;">
        <tr style="background:#f8fafc;">
            <td style="padding:.4rem .6rem; font-weight:600; width:40%;">↓ Ligne Source (débitée)</td>
            <td style="padding:.4rem .6rem;">
                @if($ligneSource)
                    <strong>{{ $ligneSource->nomenclature?->code }}</strong>
                    — {{ $ligneSource->nomenclature?->libelle }}
                    <br><small style="color:#64748b;">
                        Disponible avant : {{ number_format($ligneSource->disponible_engagement ?? 0, 0, ',', ' ') }} FCFA
                    </small>
                @else
                    <span style="color:#94a3b8;">—</span>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:.4rem .6rem; font-weight:600;">↑ Ligne Destination (créditée)</td>
            <td style="padding:.4rem .6rem;">
                @if($ligneDestination)
                    <strong>{{ $ligneDestination->nomenclature?->code }}</strong>
                    — {{ $ligneDestination->nomenclature?->libelle }}
                    <br><small style="color:#64748b;">
                        Disponible avant : {{ number_format($ligneDestination->disponible_engagement ?? 0, 0, ',', ' ') }} FCFA
                    </small>
                @else
                    <span style="color:#94a3b8;">—</span>
                @endif
            </td>
        </tr>
        @if($virement)
        <tr style="background:#f8fafc;">
            <td style="padding:.4rem .6rem; font-weight:600;">Statut virement</td>
            <td style="padding:.4rem .6rem;">
                <span style="padding:.1rem .5rem; border-radius:999px; font-size:.75rem; font-weight:600;
                    background:{{ match($virement->statut) {
                        'execute' => '#dcfce7', 'en_attente' => '#fef9c3',
                        'rejete'  => '#fee2e2', default => '#f1f5f9'
                    } }};
                    color:{{ match($virement->statut) {
                        'execute' => '#166534', 'en_attente' => '#854d0e',
                        'rejete'  => '#991b1b', default => '#475569'
                    } }};">
                    {{ ucfirst(str_replace('_', ' ', $virement->statut)) }}
                </span>
            </td>
        </tr>
        @endif
    </table>

    {{-- Dépense --}}
    @elseif($record->type === 'depense' && $ligneDepense)
    <div style="padding:.5rem .8rem; background:#f8fafc; border-radius:.4rem;">
        <strong>{{ $ligneDepense->nomenclature?->code }}</strong>
        — {{ $ligneDepense->nomenclature?->libelle }}
        <br>
        <small>Budget rectifié : {{ number_format($ligneDepense->budget_rectifie ?? 0, 0, ',', ' ') }} FCFA</small>
        &nbsp;|&nbsp;
        <small>Disponible : {{ number_format($ligneDepense->disponible_engagement ?? 0, 0, ',', ' ') }} FCFA</small>
    </div>

    {{-- Recette --}}
    @elseif($record->type === 'recette' && $ligneRecette)
    <div style="padding:.5rem .8rem; background:#f8fafc; border-radius:.4rem;">
        <strong>{{ $ligneRecette->nomenclature?->code }}</strong>
        — {{ $ligneRecette->nomenclature?->libelle }}
    </div>
    @endif

    {{-- Motif --}}
    <div style="padding:.5rem .8rem; background:#fefce8; border-radius:.4rem; border:1px solid #fde047;">
        <span style="font-weight:600; font-size:.75rem; color:#854d0e;">📝 Motif :</span><br>
        {{ $record->motif ?? '—' }}
    </div>

    {{-- Dates --}}
    <div style="color:#94a3b8; font-size:.72rem; text-align:right;">
        Créé le {{ $record->created_at?->format('d/m/Y à H:i') }}
    </div>
</div>