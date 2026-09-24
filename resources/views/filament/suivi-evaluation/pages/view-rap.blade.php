<x-filament-panels::page>
    @php
        $etat = $this->getEtat();
        $totaux = $this->record->getTotauxExecution($etat);
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        @foreach ([
            'CP prévus' => number_format($totaux['cp_prevu'], 0, ',', ' ') . ' FCFA',
            'Engagé' => number_format($totaux['engage'], 0, ',', ' ') . ' FCFA',
            'Disponible' => number_format($totaux['disponible'], 0, ',', ' ') . ' FCFA',
            "Taux d'exécution" => $totaux['taux_execution'] . ' %',
        ] as $label => $valeur)
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="text-xl font-bold">{{ $valeur }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-3 text-lg font-bold">État de mise en œuvre par sous-programme</h2>
        <div class="overflow-x-auto">
            <table class="w-full border text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="border p-2 text-left">Sous-programme</th>
                        <th class="border p-2">CP prévus</th>
                        <th class="border p-2">Engagé</th>
                        <th class="border p-2">Taux</th>
                        <th class="border p-2 text-left">Indicateurs (réalisé / cible)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($etat as $row)
                        <tr>
                            <td class="border p-2 font-semibold">{{ $row['sous_programme']->libelle }}</td>
                            <td class="border p-2 text-right">{{ number_format($row['cp_prevu'], 0, ',', ' ') }}</td>
                            <td class="border p-2 text-right">{{ number_format($row['engage'], 0, ',', ' ') }}</td>
                            <td class="border p-2 text-right">
                                <span @class([
                                    'font-bold',
                                    'text-danger-600' => $row['taux_execution'] < 50,
                                    'text-warning-600' => $row['taux_execution'] >= 50 && $row['taux_execution'] < 80,
                                    'text-success-600' => $row['taux_execution'] >= 80,
                                ])>{{ $row['taux_execution'] }} %</span>
                            </td>
                            <td class="border p-2">
                                @forelse ($row['indicateurs'] as $ind)
                                    <div>{{ $ind['libelle'] }} : <strong>{{ $ind['realise'] ?? '—' }}</strong> / {{ $ind['cible'] ?? '—' }}
                                        @if ($ind['taux'] !== null) <span class="text-xs text-gray-500">({{ $ind['taux'] }} %)</span> @endif
                                    </div>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @foreach ([
        'Contexte de mise en œuvre' => $this->record->contexte_mise_oeuvre,
        'Difficultés et solutions' => $this->record->difficultes_solutions,
        'Bilan stratégique et perspectives' => $this->record->bilan_strategique_perspectives,
        'Leçons apprises (→ prochain CDMT)' => $this->record->lecons_apprises,
    ] as $titre => $texte)
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
            <h3 class="font-bold">{{ $titre }}</h3>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $texte ?: '—' }}</p>
        </div>
    @endforeach
</x-filament-panels::page>