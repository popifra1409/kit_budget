import './bootstrap';

window.addEventListener('offline', () => {
    alert('⚠️ Connexion perdue. Vos données sont sauvegardées automatiquement.');
});

window.addEventListener('online', () => {
    alert('✅ Connexion rétablie. Vous pouvez continuer la saisie.');
});
