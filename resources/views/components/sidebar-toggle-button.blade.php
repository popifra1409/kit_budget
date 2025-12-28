{{-- Bouton Toggle Sidebar dans le Topbar --}}
<button type="button" x-data="sidebarToggle()" x-init="init()" class="sidebar-toggle-btn" @click="toggle()"
    x-bind:title="collapsed ? 'Élargir la barre latérale' : 'Réduire la barre latérale'"
    x-bind:aria-label="collapsed ? 'Élargir la barre latérale' : 'Réduire la barre latérale'">
    {{-- Icône double chevron --}}
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
        class="transition-transform duration-300" x-bind:class="{ 'rotate-180': collapsed }">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
    </svg>
</button>

{{-- Script pour ajouter les tooltips aux items de menu --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ajouter les tooltips aux items de menu
        function addTooltips() {
            document.querySelectorAll('.fi-sidebar-item').forEach(item => {
                const label = item.querySelector('.fi-sidebar-item-label');
                if (label) {
                    item.setAttribute('data-tooltip', label.textContent.trim());
                }
            });
        }

        // Initialiser les tooltips
        addTooltips();

        // Réinitialiser après navigation Livewire
        document.addEventListener('livewire:navigated', addTooltips);
    });
</script>
