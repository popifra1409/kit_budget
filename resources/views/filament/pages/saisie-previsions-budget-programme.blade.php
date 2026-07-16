<x-filament-panels::page>
@php
    $nomenclatures = $this->nomenclatures;
    $anneeN1 = $this->anneeN1;
    $anneeN2 = $this->anneeN2;
    $anneeRef = $this->anneeRef;
@endphp

<div class="space-y-4">

    {{-- Bandeau info --}}
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:.5rem; padding:.75rem 1rem; font-size:.82rem; color:#1e40af; display:flex; justify-content:space-between; align-items:center;">
        <span>
            ✏️ <strong>Saisie des prévisions {{ $anneeN1 }} et {{ $anneeN2 }}</strong>
            — Référence : exercice {{ $anneeRef }}
        </span>
        <span style="font-size:.7rem; color:#64748b;">
            💡 Saisissez 0 pour une ligne non utilisée — laissez vide pour ne pas modifier
        </span>
    </div>

    {{-- Formulaire saisie --}}
    <form wire:submit.prevent="sauvegarder">
    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
        <table style="width:100%; font-size:.78rem; border-collapse:collapse; font-family:Arial,sans-serif;">
            <thead>
                <tr style="background:#1e3a5f; color:white;">
                    <th style="padding:.5rem .75rem; text-align:left; min-width:90px;">Imputation</th>
                    <th style="padding:.5rem .75rem; text-align:left; min-width:300px;">Rubrique</th>
                    <th style="padding:.5rem 1rem; text-align:center; background:#854d0e; min-width:160px;">
                        🟡 Prévisions {{ $anneeN1 }} (FCFA)
                    </th>
                    <th style="padding:.5rem 1rem; text-align:center; background:#854d0e; min-width:160px;">
                        🟡 Prévisions {{ $anneeN2 }} (FCFA)
                    </th>
                </tr>
            </thead>
            <tbody>
                @php $currentChap = null; $rowNum = 0; @endphp
                @foreach($nomenclatures as $chapCode => $lignes)
                    {{-- En-tête chapitre --}}
                    @php
                        $chapLibelle = $lignes->first(fn($n) => strlen($n->code) <= 3)?->libelle
                            ?? $lignes->first()?->libelle ?? '';
                    @endphp
                    <tr style="background:#dbeafe; font-weight:700; font-size:.73rem;">
                        <td style="padding:.4rem .75rem; color:#1e3a5f;">{{ $chapCode }}</td>
                        <td colspan="3" style="padding:.4rem .75rem; color:#1e3a5f; text-transform:uppercase;">
                            {{ $chapLibelle }}
                        </td>
                    </tr>

                    {{-- Lignes articles (code > 3 caractères) --}}
                    @foreach($lignes->filter(fn($n) => strlen($n->code) > 3) as $nomenclature)
                        @php
                            $rowNum++;
                            $bg = $rowNum % 2 === 0 ? '#f8fafc' : 'white';
                            $prevN1 = $this->previsions[$nomenclature->id][$anneeN1] ?? '';
                            $prevN2 = $this->previsions[$nomenclature->id][$anneeN2] ?? '';
                        @endphp
                        <tr style="background:{{ $bg }}; border-bottom:1px solid #e5e7eb;">
                            <td style="padding:.4rem .75rem; font-family:monospace; color:#374151;">
                                {{ $nomenclature->code }}
                            </td>
                            <td style="padding:.4rem .75rem; color:#374151;">
                                {{ $nomenclature->libelle }}
                            </td>
                            <td style="padding:.3rem .5rem; background:#fefce8; border-left:2px solid #fbbf24;">
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    value="{{ $prevN1 }}"
                                    wire:change="setPrevision({{ $nomenclature->id }}, {{ $anneeN1 }}, $event.target.value)"
                                    style="width:100%; padding:.3rem .5rem; border:1px solid #d97706; border-radius:.25rem;
                                           font-size:.78rem; text-align:right; background:#fffbeb;
                                           font-family:Arial,sans-serif;"
                                >
                            </td>
                            <td style="padding:.3rem .5rem; background:#fefce8;">
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    value="{{ $prevN2 }}"
                                    wire:change="setPrevision({{ $nomenclature->id }}, {{ $anneeN2 }}, $event.target.value)"
                                    style="width:100%; padding:.3rem .5rem; border:1px solid #d97706; border-radius:.25rem;
                                           font-size:.78rem; text-align:right; background:#fffbeb;
                                           font-family:Arial,sans-serif;"
                                >
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Bouton sauvegarder en bas --}}
    <div style="display:flex; justify-content:flex-end; gap:1rem; padding:1rem 0;">
        <a href="{{ \App\Filament\Budget\Pages\BudgetProgrammePage::getUrl() }}"
           style="padding:.5rem 1.25rem; background:#e2e8f0; border-radius:.4rem; font-size:.85rem; text-decoration:none; color:#374151;">
            ← Retour
        </a>
        <button type="submit"
            style="padding:.5rem 1.5rem; background:#166534; color:white; border:none; border-radius:.4rem;
                   font-size:.85rem; cursor:pointer; font-weight:600;">
            💾 Sauvegarder les prévisions
        </button>
    </div>
    </form>
</div>
</x-filament-panels::page>