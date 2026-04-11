@php
    $signaturesConfig = $signatures ?? ($config->signature_config['signatures'] ?? []);
    $afficherSignatures = $afficher ?? ($config->signature_config['afficher'] ?? true);
@endphp

@if ($afficherSignatures && !empty($signaturesConfig))
    <div class="signature-zone">
        <table class="signature-row">
            <tr>
                @foreach ($signaturesConfig as $signature)
                    <td style="width: {{ $signature['largeur'] ?? 33 }}%;">
                        <div class="signature-titre">
                            {{ $signature['titre'] }}
                        </div>

                        @if (isset($signature['nom']) && $signature['nom'])
                            <div class="signature-nom">
                                {{ $signature['nom'] }}
                            </div>
                        @endif

                        @if (isset($signature['fonction']) && $signature['fonction'])
                            <div style="font-size: 8pt; margin-top: 4px;">
                                {{ $signature['fonction'] }}
                            </div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
@endif
