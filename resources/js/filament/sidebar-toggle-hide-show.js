// ===================================
// SIDEBAR TOGGLE - Hide/Show
// ===================================

document.addEventListener('DOMContentLoaded', function () {
    // Alpine.js component pour le toggle
    window.sidebarToggle = function () {
        return {
            hidden: localStorage.getItem('sidebar-hidden') === 'true',

            init() {
                // Appliquer l'état initial
                this.applyState();

                // Écouter les changements de localStorage
                window.addEventListener('storage', (e) => {
                    if (e.key === 'sidebar-hidden') {
                        this.hidden = e.newValue === 'true';
                        this.applyState();
                    }
                });
            },

            toggle() {
                this.hidden = !this.hidden;
                localStorage.setItem('sidebar-hidden', this.hidden);
                this.applyState();

                // Dispatcher un événement
                window.dispatchEvent(new CustomEvent('sidebar-toggled', {
                    detail: { hidden: this.hidden }
                }));
            },

            applyState() {
                if (this.hidden) {
                    document.body.setAttribute('data-sidebar-hidden', 'true');
                } else {
                    document.body.setAttribute('data-sidebar-hidden', 'false');
                }

                // Forcer le recalcul du layout
                window.dispatchEvent(new Event('resize'));
            },

            show() {
                this.hidden = false;
                localStorage.setItem('sidebar-hidden', this.hidden);
                this.applyState();
            },

            hide() {
                this.hidden = true;
                localStorage.setItem('sidebar-hidden', this.hidden);
                this.applyState();
            }
        }
    }
});

// Fonction utilitaire
window.toggleSidebar = function () {
    const hidden = localStorage.getItem('sidebar-hidden') === 'true';
    localStorage.setItem('sidebar-hidden', !hidden);
    document.body.setAttribute('data-sidebar-hidden', !hidden ? 'true' : 'false');
}