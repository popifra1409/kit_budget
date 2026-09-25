{{ $item['texte'] }}@if (!empty($item['alertes']))
    <span class="mx-marque" title="{{ implode(' • ', $item['alertes']) }}">⚠</span>
@endif