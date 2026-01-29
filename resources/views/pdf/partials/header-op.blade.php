{{-- Header pour Ordonnance de Paiement --}}
<table style="width: 100%; border: none; margin-bottom: 15px;">
    <tr>
        <td style="width: 20%; border: none; padding: 0; vertical-align: top;">
            {{-- Logo --}}
            @if ($parametres->logo_path && file_exists(public_path('storage/' . $parametres->logo_path)))
                <img src="{{ public_path('storage/' . $parametres->logo_path) }}" style="height: 60px; width: auto;">
            @endif
        </td>
        <td style="width: 60%; border: none; padding: 0; text-align: center; vertical-align: middle;">
            <div style="font-weight: bold; font-size: 11pt;">
                {{ strtoupper($parametres->nom_structure) }}
            </div>
            <div style="font-size: 9pt; font-style: italic;">
                {{ strtoupper($parametres->nom_structure_en ?? 'YAOUNDE GENERAL HOSPITAL') }}
            </div>
        </td>
        <td style="width: 20%; border: none; padding: 5px; vertical-align: top; text-align: right;">
            <div style="border: 1px solid #000; padding: 5px; font-size: 8pt;">
                <strong>Mois et exercice d'émission:</strong><br>
                {{ $donnees['mois_emission'] ?? now()->format('m') }}/{{ $donnees['exercice'] ?? now()->year }}
            </div>
        </td>
    </tr>
</table>

{{-- Sous-header avec visa --}}
<table style="width: 100%; border: none; margin-bottom: 10px;">
    <tr>
        <td style="width: 50%; border: none; padding: 0; font-size: 8pt;">
            <strong>Visa du Directeur des</strong><br>
            <strong>Affaires Administratives et</strong><br>
            <strong>Financières</strong>
        </td>
        <td style="width: 50%; border: none; padding: 0; text-align: center;">
            <div style="font-weight: bold; font-size: 11pt;">ORDONNANCE DE PAIEMENT</div>
            <div style="font-style: italic; font-size: 9pt;">PAYMENT ORDER</div>
        </td>
    </tr>
</table>

{{-- Ligne de séparation --}}
<div style="border-bottom: 2px solid #000; margin: 10px 0;"></div>
