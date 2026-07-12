@php
    $changes = $getState();
    if (!is_array($changes) || empty($changes)) return;

    $ignored = ['updated_at', 'created_at', '_context'];
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-gray-100 dark:bg-gray-800">
                <th class="p-2 text-left border border-gray-200 dark:border-gray-700 font-semibold w-1/4">Champ</th>
                <th class="p-2 text-left border border-gray-200 dark:border-gray-700 font-semibold w-3/8">
                    <span class="text-red-600">❌ Avant</span>
                </th>
                <th class="p-2 text-left border border-gray-200 dark:border-gray-700 font-semibold w-3/8">
                    <span class="text-green-600">✅ Après</span>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($changes as $field => $values)
                @if(!in_array($field, $ignored))
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td class="p-2 border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-600">
                        {{ $field }}
                    </td>
                    <td class="p-2 border border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/10">
                        <span class="text-red-700 dark:text-red-400">
                            {{ is_array($values['ancien']) ? json_encode($values['ancien']) : ($values['ancien'] ?? '—') }}
                        </span>
                    </td>
                    <td class="p-2 border border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/10">
                        <span class="text-green-700 dark:text-green-400 font-semibold">
                            {{ is_array($values['nouveau']) ? json_encode($values['nouveau']) : ($values['nouveau'] ?? '—') }}
                        </span>
                    </td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>