<div class="space-y-4">
    @forelse($transmissions as $transmission)
        <div
            class="border rounded-lg p-4 {{ $transmission->statut === 'en_attente' ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200' }}">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-semibold text-gray-900">
                            {{ $transmission->expediteur->name }}
                        </span>
                        <span class="text-gray-500">→</span>
                        <span class="font-semibold text-gray-900">
                            {{ $transmission->destinataire->name }}
                        </span>

                        <span
                            class="px-2 py-1 text-xs rounded-full bg-{{ $transmission->getStatutColor() }}-100 text-{{ $transmission->getStatutColor() }}-700">
                            {{ $transmission->getStatutLabel() }}
                        </span>
                    </div>

                    <div class="text-sm text-gray-600 mb-2">
                        <strong>Action:</strong> {{ $transmission->getActionLabel() }}
                    </div>

                    @if ($transmission->commentaire)
                        <div class="text-sm text-gray-700 bg-white p-2 rounded border mb-2">
                            <strong>Commentaire:</strong> {{ $transmission->commentaire }}
                        </div>
                    @endif

                    @if ($transmission->reponse)
                        <div class="text-sm text-gray-700 bg-white p-2 rounded border mb-2">
                            <strong>Réponse:</strong> {{ $transmission->reponse }}
                        </div>
                    @endif

                    <div class="flex gap-4 text-xs text-gray-500 mt-2">
                        <span>📅 Transmis le {{ $transmission->date_transmission->format('d/m/Y à H:i') }}</span>

                        @if ($transmission->date_traitement)
                            <span>✅ Traité le {{ $transmission->date_traitement->format('d/m/Y à H:i') }}</span>
                        @endif

                        @if ($transmission->date_limite)
                            <span class="{{ $transmission->estEnRetard() ? 'text-red-600 font-semibold' : '' }}">
                                ⏰ Limite: {{ $transmission->date_limite->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="ml-4">
                    <span
                        class="px-2 py-1 text-xs rounded bg-{{ $transmission->getPrioriteColor() }}-100 text-{{ $transmission->getPrioriteColor() }}-700">
                        {{ ucfirst($transmission->priorite) }}
                    </span>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center text-gray-500 py-8">
            Aucune transmission pour ce document
        </div>
    @endforelse
</div>