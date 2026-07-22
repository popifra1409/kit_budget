<div class="space-y-4 p-2">

    {{-- Récapitulatif ligne --}}
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:.5rem; padding:.75rem;">
        <div style="font-size:.75rem; color:#64748b;">Ligne budgétaire</div>
        <div style="font-weight:700; color:#1e3a5f; font-size:.9rem;">
            {{ $record->nomenclature?->code }} — {{ $record->nomenclature?->libelle }}
        </div>
        <div class="grid grid-cols-3 gap-2 mt-2" style="font-size:.78rem;">
            <div>
                <span style="color:#64748b;">Budget initial :</span>
                <strong>{{ number_format($record->budget_initial ?? 0, 0, ',', ' ') }} FCFA</strong>
            </div>
            <div>
                <span style="color:#64748b;">Budget rectifié :</span>
                <strong style="color:#1e40af;">{{ number_format($record->budget_rectifie ?? 0, 0, ',', ' ') }} FCFA</strong>
            </div>
            <div>
                <span style="color:#64748b;">Disponible :</span>
                <strong style="color:{{ ($record->disponible_engagement ?? 0) >= 0 ? '#166534' : '#991b1b' }};">
                    {{ number_format($record->disponible_engagement ?? 0, 0, ',', ' ') }} FCFA
                </strong>
            </div>
        </div>
    </div>

    {{-- Mouvements collectifs --}}
    @if($mouvements->isNotEmpty())
    <div>
        <div style="font-size:.75rem; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:.4rem;">
            📋 Mouvements de collectifs budgétaires
        </div>
        <table style="width:100%; font-size:.78rem; border-collapse:collapse;">
            <thead>
                <tr style="background:#1e3a5f; color:white;">
                    <th style="padding:.4rem .6rem; text-align:left;">Collectif</th>
                    <th style="padding:.4rem .6rem; text-align:left;">Date</th>
                    <th style="padding:.4rem .6rem; text-align:right;">Montant</th>
                    <th style="padding:.4rem .6rem; text-align:left;">Motif</th>
                    <th style="padding:.4rem .6rem; text-align:center;">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($mouvements as $m)
                <tr style="border-bottom:1px solid #e5e7eb; background:{{ $loop->even ? '#f8fafc' : 'white' }};">
                    <td style="padding:.4rem .6rem; font-weight:600; color:#1e40af;">
                        {{ $m->collectif?->numero ?? '—' }}
                    </td>
                    <td style="padding:.4rem .6rem; color:#64748b;">
                        {{ $m->created_at?->format('d/m/Y') }}
                    </td>
                    <td style="padding:.4rem .6rem; text-align:right; font-weight:700;
                        color:{{ $m->montant_modification >= 0 ? '#166534' : '#991b1b' }};">
                        {{ $m->montant_modification >= 0 ? '+' : '' }}{{ number_format($m->montant_modification, 0, ',', ' ') }} FCFA
                    </td>
                    <td style="padding:.4rem .6rem; color:#374151;">
                        {{ \Str::limit($m->motif ?? '—', 40) }}
                    </td>
                    <td style="padding:.4rem .6rem; text-align:center;">
                        @php
                            $statut = $m->collectif?->statut ?? '—';
                            $color = match($statut) {
                                'adopte'  => '#166534',
                                'annule'  => '#991b1b',
                                'projet'  => '#854d0e',
                                default   => '#64748b',
                            };
                        @endphp
                        <span style="background:{{ $color }}20; color:{{ $color }};
                            padding:.1rem .4rem; border-radius:999px; font-size:.7rem; font-weight:600;">
                            {{ ucfirst($statut) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Virements --}}
    @if($virements->isNotEmpty())
    <div>
        <div style="font-size:.75rem; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:.4rem;">
            ↔️ Virements budgétaires
        </div>
        <table style="width:100%; font-size:.78rem; border-collapse:collapse;">
            <thead>
                <tr style="background:#1e3a5f; color:white;">
                    <th style="padding:.4rem .6rem; text-align:left;">N° Virement</th>
                    <th style="padding:.4rem .6rem; text-align:left;">Date</th>
                    <th style="padding:.4rem .6rem; text-align:center;">Direction</th>
                    <th style="padding:.4rem .6rem; text-align:right;">Montant</th>
                    <th style="padding:.4rem .6rem; text-align:left;">Motif</th>
                    <th style="padding:.4rem .6rem; text-align:center;">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($virements as $v)
                @php
                    $isSource = $v->ligne_source_id === $record->id;
                    $direction = $isSource ? '↓ Sortant' : '↑ Entrant';
                    $dirColor  = $isSource ? '#991b1b' : '#166534';
                    $montantSigne = $isSource
                        ? '-' . number_format($v->montant, 0, ',', ' ')
                        : '+' . number_format($v->montant, 0, ',', ' ');
                @endphp
                <tr style="border-bottom:1px solid #e5e7eb; background:{{ $loop->even ? '#f8fafc' : 'white' }};">
                    <td style="padding:.4rem .6rem; font-weight:600; color:#1e40af;">{{ $v->numero }}</td>
                    <td style="padding:.4rem .6rem; color:#64748b;">{{ $v->date_virement?->format('d/m/Y') }}</td>
                    <td style="padding:.4rem .6rem; text-align:center;">
                        <span style="color:{{ $dirColor }}; font-weight:600; font-size:.75rem;">{{ $direction }}</span>
                    </td>
                    <td style="padding:.4rem .6rem; text-align:right; font-weight:700; color:{{ $dirColor }};">
                        {{ $montantSigne }} FCFA
                    </td>
                    <td style="padding:.4rem .6rem; color:#374151;">{{ \Str::limit($v->motif ?? '—', 35) }}</td>
                    <td style="padding:.4rem .6rem; text-align:center;">
                        @php
                            $sc = match($v->statut) {
                                'execute'   => '#166534',
                                'rejete'    => '#991b1b',
                                'approuve'  => '#1e40af',
                                'en_attente'=> '#854d0e',
                                default     => '#64748b',
                            };
                        @endphp
                        <span style="background:{{ $sc }}20; color:{{ $sc }};
                            padding:.1rem .4rem; border-radius:999px; font-size:.7rem; font-weight:600;">
                            {{ ucfirst(str_replace('_', ' ', $v->statut)) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($mouvements->isEmpty() && $virements->isEmpty())
    <div style="text-align:center; color:#94a3b8; padding:2rem; font-size:.85rem;">
        Aucune opération enregistrée sur cette ligne budgétaire.
    </div>
    @endif

</div>