<div class="space-y-4">
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
            📋 Qu'est-ce qu'une Fiche de Contrôle des Engagements ?
        </h3>
        <p class="text-sm text-blue-800 dark:text-blue-200">
            Une fiche de contrôle permet de suivre tous les engagements (BC, DA) effectués sur une ligne budgétaire
            spécifique.
            Elle affiche la dotation initiale, les engagements successifs, et calcule automatiquement les crédits
            disponibles restants.
        </p>
    </div>

    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-green-900 dark:text-green-100 mb-2">
            ✅ Comment utiliser cette fonctionnalité ?
        </h3>
        <ul class="space-y-2 text-sm text-green-800 dark:text-green-200">
            <li class="flex items-start">
                <span class="font-bold mr-2">1.</span>
                <span>Utilisez les <strong>filtres</strong> pour sélectionner l'exercice budgétaire et le budget
                    souhaité</span>
            </li>
            <li class="flex items-start">
                <span class="font-bold mr-2">2.</span>
                <span>Activez le filtre <strong>"Avec engagements uniquement"</strong> pour n'afficher que les lignes
                    qui ont des engagements</span>
            </li>
            <li class="flex items-start">
                <span class="font-bold mr-2">3.</span>
                <span>Cliquez sur <strong>"Aperçu"</strong> pour voir la fiche en HTML avant de la télécharger</span>
            </li>
            <li class="flex items-start">
                <span class="font-bold mr-2">4.</span>
                <span>Cliquez sur <strong>"PDF"</strong> pour télécharger la fiche au format PDF</span>
            </li>
            <li class="flex items-start">
                <span class="font-bold mr-2">5.</span>
                <span>Cliquez sur <strong>"Détails"</strong> pour voir tous les engagements de la ligne
                    budgétaire</span>
            </li>
        </ul>
    </div>

    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-yellow-900 dark:text-yellow-100 mb-2">
            📊 Informations Affichées
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-yellow-800 dark:text-yellow-200">
            <div>
                <strong>Dotation Initiale :</strong> Crédits alloués à la ligne budgétaire
            </div>
            <div>
                <strong>Total Engagé :</strong> Somme de tous les engagements effectués
            </div>
            <div>
                <strong>Disponible :</strong> Crédits restants (Dotation - Engagements)
            </div>
            <div>
                <strong>Taux de Consommation :</strong> Pourcentage des crédits utilisés
            </div>
        </div>
    </div>

    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-red-900 dark:text-red-100 mb-2">
            ⚠️ Alertes Importantes
        </h3>
        <ul class="space-y-2 text-sm text-red-800 dark:text-red-200">
            <li class="flex items-start">
                <span class="mr-2">•</span>
                <span>Un <strong>disponible négatif</strong> (en rouge) indique un dépassement de crédits</span>
            </li>
            <li class="flex items-start">
                <span class="mr-2">•</span>
                <span>Un <strong>taux de consommation > 100%</strong> signale une surconsommation budgétaire</span>
            </li>
            <li class="flex items-start">
                <span class="mr-2">•</span>
                <span>Utilisez le filtre <strong>"Dépassements de crédits"</strong> pour identifier rapidement les
                    lignes en dépassement</span>
            </li>
        </ul>
    </div>

    <div class="bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
            📤 Export en Masse
        </h3>
        <p class="text-sm text-gray-800 dark:text-gray-200 mb-2">
            Pour exporter plusieurs fiches à la fois :
        </p>
        <ol class="space-y-1 text-sm text-gray-800 dark:text-gray-200 pl-4">
            <li>1. Sélectionnez les lignes budgétaires souhaitées (cocher les cases)</li>
            <li>2. Cliquez sur "Actions groupées" > "Générer les Fiches PDF"</li>
            <li>3. Téléchargez le fichier ZIP contenant toutes les fiches</li>
        </ol>
    </div>
</div>
