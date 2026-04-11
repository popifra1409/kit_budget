// ===================================
// SIDEBAR TOGGLE - Alpine.js Component
// ===================================

document.addEventListener('DOMContentLoaded', function () {
    // Alpine.js data pour le toggle sidebar
    window.sidebarToggle = function () {
        return {
            collapsed: localStorage.getItem('sidebar-collapsed') === 'true',

            init() {
                // Appliquer l'état initial
                this.applyState();

                // Écouter les changements de localStorage (synchronisation entre onglets)
                window.addEventListener('storage', (e) => {
                    if (e.key === 'sidebar-collapsed') {
                        this.collapsed = e.newValue === 'true';
                        this.applyState();
                    }
                });
            },

            toggle() {
                this.collapsed = !this.collapsed;
                localStorage.setItem('sidebar-collapsed', this.collapsed);
                this.applyState();

                // Dispatcher un événement pour d'autres composants
                window.dispatchEvent(new CustomEvent('sidebar-toggled', {
                    detail: { collapsed: this.collapsed }
                }));
            },

            applyState() {
                if (this.collapsed) {
                    document.body.setAttribute('data-sidebar-collapsed', 'true');
                } else {
                    document.body.removeAttribute('data-sidebar-collapsed');
                }

                // Forcer le recalcul du layout
                window.dispatchEvent(new Event('resize'));
            },

            expand() {
                this.collapsed = false;
                localStorage.setItem('sidebar-collapsed', this.collapsed);
                this.applyState();
            },

            collapse() {
                this.collapsed = true;
                localStorage.setItem('sidebar-collapsed', this.collapsed);
                this.applyState();
            }
        }
    }
});

// Fonction utilitaire pour d'autres scripts
window.getSidebarState = function () {
    return localStorage.getItem('sidebar-collapsed') === 'true';
}

window.setSidebarState = function (collapsed) {
    localStorage.setItem('sidebar-collapsed', collapsed);
    document.body.setAttribute('data-sidebar-collapsed', collapsed ? 'true' : 'false');
    window.dispatchEvent(new Event('resize'));
}