{{-- Header avec République du Cameroun, Logo et Ministry --}}

@php
    // Vérifier si les informations de structure sont complètes
    $infoStructureComplete = $parametres && !empty($parametres->nom_structure) && !empty($parametres->telephone);
@endphp

@if ($infoStructureComplete)
    {{-- ✅ OPTION 1 : Header avec tableau et informations détaillées --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="republique">
                    RÉPUBLIQUE DU CAMEROUN<br>
                    <em>Paix – Travail – Patrie</em>
                </div>
                <div class="republique" style="margin-top:4px">
                    MINISTERE DE LA SANTE PUBLIQUE
                </div>
            </td>

            <td class="header-center">
                @if ($parametres && $parametres->logo)
                    @php
                        $logoPath = storage_path('app/public/' . $parametres->logo);

                        if (file_exists($logoPath)) {
                            $imageData = base64_encode(file_get_contents($logoPath));
                            $mimeType = mime_content_type($logoPath);
                            $logoBase64 = "data:{$mimeType};base64,{$imageData}";
                        } else {
                            $logoBase64 = null;
                        }
                    @endphp

                    @if (isset($logoBase64))
                        <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
                    @endif
                @endif

                <div class="structure">
                    {{ $parametres->nom_structure }}
                </div>
                <div class="adresse">
                    {{ $parametres->adresse ?? '' }}<br>
                    Tél : {{ $parametres->telephone }}
                </div>
            </td>

            <td class="header-right">
                <div class="republique">
                    REPUBLIC OF CAMEROON<br>
                    <em>Peace – Work – Fatherland</em>
                </div>
                <div class="republique" style="margin-top:4px">
                    <em>MINISTRY OF PUBLIC HEALTH</em>
                </div>
            </td>
        </tr>
    </table>
@elseif($parametres && $parametres->logo)
    {{-- ✅ OPTION 2 : Image PNG complète depuis parametres->logo --}}
    @php
        $headerImagePath = storage_path('app/public/' . $parametres->logo);

        if (file_exists($headerImagePath)) {
            $imageData = base64_encode(file_get_contents($headerImagePath));
            $mimeType = mime_content_type($headerImagePath);
            $headerImageBase64 = "data:{$mimeType};base64,{$imageData}";
        } else {
            $headerImageBase64 = null;
        }
    @endphp

    @if (isset($headerImageBase64))
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="{{ $headerImageBase64 }}" style="width: 100%; max-width: 800px; height: auto;" alt="Header">
        </div>
    @else
        {{-- Fallback si fichier introuvable --}}
        <div style="text-align: center; padding: 20px; border: 2px solid red;">
            <strong style="color: red;">⚠️ Image header introuvable</strong><br>
            <small>Chemin : {{ $parametres->logo }}</small>
        </div>
    @endif
@else
    {{-- ✅ OPTION 3 : Header minimal par défaut --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                <div class="republique">
                    RÉPUBLIQUE DU CAMEROUN<br>
                    <em>Paix – Travail – Patrie</em>
                </div>
            </td>

            <td class="header-center">
                <div class="structure">
                    STRUCTURE
                </div>
            </td>

            <td class="header-right">
                <div class="republique">
                    REPUBLIC OF CAMEROON<br>
                    <em>Peace – Work – Fatherland</em>
                </div>
            </td>
        </tr>
    </table>
@endif
