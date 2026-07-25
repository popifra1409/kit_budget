<x-filament-panels::page>
    <div class="mb-4" style="background:{{ $type === 'depense' ? '#fef2f2' : '#f0fdf4' }}; border:1px solid {{ $type === 'depense' ? '#fecaca' : '#bbf7d0' }}; border-radius:.5rem; padding:.75rem 1rem; font-size:.82rem; color:{{ $type === 'depense' ? '#991b1b' : '#166534' }};">
        {{ $type === 'depense' ? '💸' : '💰' }}
        <strong>Saisie des prévisions {{ $type === 'depense' ? 'de Dépenses' : 'de Recettes' }}</strong>
        — Années {{ $anneeN1 }} et {{ $anneeN2 }}
    </div>

    <div class="space-y-4">
        @forelse($this->nomenclatures as $groupeLibelle => $nomenclaturesDuGroupe)
            <div class="rounded-lg border border-gray-200 overflow-hidden">
                <div style="background:{{ $groupeLibelle === 'Non classées / Hors groupe' ? '#fefce8' : '#0f172a' }}; color:{{ $groupeLibelle === 'Non classées / Hors groupe' ? '#854d0e' : 'white' }}; padding:.5rem .75rem; font-weight:700; font-size:.8rem; text-transform:uppercase;">
                    {{ $groupeLibelle === 'Non classées / Hors groupe' ? '⚠️' : '📁' }} {{ $groupeLibelle }}
                </div>

                <table style="width:100%; font-size:.78rem; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th style="padding:.4rem .75rem; text-align:left; width:100px;">Code</th>
                            <th style="padding:.4rem .75rem; text-align:left;">Libellé</th>
                            <th style="padding:.4rem .75rem; text-align:right; width:160px; background:#fefce8;">Prévision {{ $anneeN1 }}</th>
                            <th style="padding:.4rem .75rem; text-align:right; width:160px; background:#fefce8;">Prévision {{ $anneeN2 }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nomenclaturesDuGroupe as $nomenclature)
                            <tr style="border-top:1px solid #e5e7eb;">
                                <td style="padding:.35rem .75rem; font-family:monospace; color:#374151;">{{ $nomenclature->code }}</td>
                                <td style="padding:.35rem .75rem; color:#374151;">{{ $nomenclature->libelle }}</td>
                                <td style="padding:.3rem .5rem; background:#fefce8;">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        wire:model.lazy="previsions.{{ $nomenclature->id }}.{{ $anneeN1 }}"
                                        class="fi-input block w-full rounded-md border-gray-300 text-right text-sm"
                                        placeholder="0"
                                    />
                                </td>
                                <td style="padding:.3rem .5rem; background:#fefce8;">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        wire:model.lazy="previsions.{{ $nomenclature->id }}.{{ $anneeN2 }}"
                                        class="fi-input block w-full rounded-md border-gray-300 text-right text-sm"
                                        placeholder="0"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic">Aucune ligne de nomenclature de type « {{ $type }} ».</p>
        @endforelse
    </div>

    <p style="font-size:.7rem; color:#94a3b8; text-align:right; margin-top:.75rem;">
        💾 N'oublie pas de cliquer sur "Sauvegarder" en haut pour enregistrer les montants saisis.
    </p>
</x-filament-panels::page>