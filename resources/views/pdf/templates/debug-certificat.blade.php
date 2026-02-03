@extends('pdf.layouts.master', ['typeFooter' => 'engagement'])

@section('title', 'DEBUG - Certificat Engagement')

@php
    $engagement = $donnees['_raw'];

    // Charger les relations
    $engagement->load(['nomenclaturePrincipale', 'beneficiaire', 'exercice']);

    $nomenclature = $engagement->nomenclaturePrincipale;
@endphp

@section('content')
    <h1 style="text-align: center; color: red;">DIAGNOSTIC - CERTIFICAT D'ENGAGEMENT</h1>

    <hr>

    <h2>📋 ÉTAPE 1 : Engagement</h2>
    <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
        <tr>
            <td><strong>ID:</strong></td>
            <td>{{ $engagement->id }}</td>
        </tr>
        <tr>
            <td><strong>Numéro:</strong></td>
            <td>{{ $engagement->numero }}</td>
        </tr>
        <tr>
            <td><strong>Reference:</strong></td>
            <td>{{ $engagement->reference_document }}</td>
        </tr>
        <tr>
            <td><strong>Montant:</strong></td>
            <td>{{ number_format($engagement->montant_engage, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td><strong>nomenclature_principale_id:</strong></td>
            <td>{{ $engagement->nomenclature_principale_id ?? 'NULL' }}</td>
        </tr>
    </table>

    <hr>

    <h2>📦 ÉTAPE 2 : Nomenclature</h2>
    @if ($nomenclature)
        <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
            <tr>
                <td><strong>✅ Nomenclature trouvée:</strong></td>
                <td>OUI</td>
            </tr>
            <tr>
                <td><strong>ID:</strong></td>
                <td>{{ $nomenclature->id }}</td>
            </tr>
            <tr>
                <td><strong>Code:</strong></td>
                <td>{{ $nomenclature->code }}</td>
            </tr>
            <tr>
                <td><strong>Libellé:</strong></td>
                <td>{{ $nomenclature->libelle }}</td>
            </tr>
            <tr>
                <td><strong>Colonnes de la table:</strong></td>
                <td>
                    @php
                        $columns = \Schema::getColumnListing('nomenclatures_budgetaires');
                        echo implode(', ', $columns);
                    @endphp
                </td>
            </tr>
            <tr>
                <td><strong>Relation 'tache' existe?:</strong></td>
                <td>
                    @php
                        try {
                            $testTache = $nomenclature->tache;
                            echo $testTache ? 'OUI (ID: ' . $testTache->id . ')' : 'NON (NULL)';
                        } catch (\Exception $e) {
                            echo 'ERREUR: ' . $e->getMessage();
                        }
                    @endphp
                </td>
            </tr>
            <tr>
                <td><strong>Relation 'taches' (count):</strong></td>
                <td>
                    @php
                        try {
                            $countTaches = $nomenclature->taches()->count();
                            echo $countTaches;
                        } catch (\Exception $e) {
                            echo 'ERREUR: ' . $e->getMessage();
                        }
                    @endphp
                </td>
            </tr>
        </table>
    @else
        <p style="color: red; font-weight: bold;">❌ NOMENCLATURE NON TROUVÉE</p>
    @endif

    <hr>

    <h2>📝 ÉTAPE 3 : Tâches liées à cette nomenclature</h2>
    @if ($nomenclature)
        @php
            try {
                $tacheTrouvee = $nomenclature->tache ?? $nomenclature->taches()->first();
            } catch (\Exception $e) {
                $tacheTrouvee = null;
                echo '<p style="color: red;">ERREUR: ' . $e->getMessage() . '</p>';
            }
        @endphp

        @if ($tacheTrouvee)
            <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
                <tr>
                    <td><strong>✅ Tâche trouvée:</strong></td>
                    <td>OUI</td>
                </tr>
                <tr>
                    <td><strong>ID:</strong></td>
                    <td>{{ $tacheTrouvee->id }}</td>
                </tr>
                <tr>
                    <td><strong>Code:</strong></td>
                    <td>{{ $tacheTrouvee->code ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>Libellé:</strong></td>
                    <td>{{ $tacheTrouvee->libelle }}</td>
                </tr>
                <tr>
                    <td><strong>Niveau:</strong></td>
                    <td>{{ $tacheTrouvee->niveau ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>nomenclature_id:</strong></td>
                    <td>{{ $tacheTrouvee->nomenclature_id ?? 'NULL' }}</td>
                </tr>
                <tr>
                    <td><strong>activite_id:</strong></td>
                    <td>{{ $tacheTrouvee->activite_id ?? 'NULL' }}</td>
                </tr>
                <tr>
                    <td><strong>Colonnes de la table taches:</strong></td>
                    <td>
                        @php
                            $tachesColumns = \Schema::getColumnListing('taches');
                            echo implode(', ', $tachesColumns);
                        @endphp
                    </td>
                </tr>
            </table>
        @else
            <p style="color: red; font-weight: bold;">❌ AUCUNE TÂCHE TROUVÉE pour cette nomenclature</p>
            <p>Vérification SQL directe :</p>
            @php
                $tachesDirectes = \App\Models\Tache::where('nomenclature_id', $nomenclature->id)->get();
                echo "Nombre de tâches avec nomenclature_id={$nomenclature->id} : {$tachesDirectes->count()}<br>";
                if ($tachesDirectes->count() > 0) {
                    echo '<ul>';
                    foreach ($tachesDirectes as $t) {
                        echo "<li>Tâche ID {$t->id}: {$t->libelle}</li>";
                    }
                    echo '</ul>';
                }
            @endphp
        @endif
    @endif

    <hr>

    <h2>🔗 ÉTAPE 4 : Activité (si tâche existe)</h2>
    @if (isset($tacheTrouvee) && $tacheTrouvee)
        @php
            try {
                $activiteTrouvee = $tacheTrouvee->activite;
            } catch (\Exception $e) {
                $activiteTrouvee = null;
                echo '<p style="color: red;">ERREUR: ' . $e->getMessage() . '</p>';
            }
        @endphp

        @if ($activiteTrouvee)
            <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
                <tr>
                    <td><strong>✅ Activité trouvée:</strong></td>
                    <td>OUI</td>
                </tr>
                <tr>
                    <td><strong>ID:</strong></td>
                    <td>{{ $activiteTrouvee->id }}</td>
                </tr>
                <tr>
                    <td><strong>Libellé:</strong></td>
                    <td>{{ $activiteTrouvee->libelle }}</td>
                </tr>
                <tr>
                    <td><strong>action_id:</strong></td>
                    <td>{{ $activiteTrouvee->action_id ?? 'NULL' }}</td>
                </tr>
            </table>
        @else
            <p style="color: red; font-weight: bold;">❌ ACTIVITÉ NON TROUVÉE</p>
            <p>La tâche n'a pas d'activité liée (activite_id = {{ $tacheTrouvee->activite_id ?? 'NULL' }})</p>
        @endif
    @else
        <p style="color: gray;">⏭️ Étape ignorée (pas de tâche)</p>
    @endif

    <hr>

    <h2>🎯 ÉTAPE 5 : Action (si activité existe)</h2>
    @if (isset($activiteTrouvee) && $activiteTrouvee)
        @php
            try {
                $actionTrouvee = $activiteTrouvee->action;
            } catch (\Exception $e) {
                $actionTrouvee = null;
                echo '<p style="color: red;">ERREUR: ' . $e->getMessage() . '</p>';
            }
        @endphp

        @if ($actionTrouvee)
            <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
                <tr>
                    <td><strong>✅ Action trouvée:</strong></td>
                    <td>OUI</td>
                </tr>
                <tr>
                    <td><strong>ID:</strong></td>
                    <td>{{ $actionTrouvee->id }}</td>
                </tr>
                <tr>
                    <td><strong>Libellé:</strong></td>
                    <td>{{ $actionTrouvee->libelle }}</td>
                </tr>
                <tr>
                    <td><strong>programme_id:</strong></td>
                    <td>{{ $actionTrouvee->programme_id ?? 'NULL' }}</td>
                </tr>
            </table>
        @else
            <p style="color: red; font-weight: bold;">❌ ACTION NON TROUVÉE</p>
        @endif
    @else
        <p style="color: gray;">⏭️ Étape ignorée (pas d'activité)</p>
    @endif

    <hr>

    <h2>🏆 ÉTAPE 6 : Programme (si action existe)</h2>
    @if (isset($actionTrouvee) && $actionTrouvee)
        @php
            try {
                $programmeTrouve = $actionTrouvee->programme;
            } catch (\Exception $e) {
                $programmeTrouve = null;
                echo '<p style="color: red;">ERREUR: ' . $e->getMessage() . '</p>';
            }
        @endphp

        @if ($programmeTrouve)
            <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
                <tr>
                    <td><strong>✅ Programme trouvé:</strong></td>
                    <td>OUI</td>
                </tr>
                <tr>
                    <td><strong>ID:</strong></td>
                    <td>{{ $programmeTrouve->id }}</td>
                </tr>
                <tr>
                    <td><strong>Libellé:</strong></td>
                    <td>{{ $programmeTrouve->libelle }}</td>
                </tr>
            </table>
        @else
            <p style="color: red; font-weight: bold;">❌ PROGRAMME NON TROUVÉ</p>
        @endif
    @else
        <p style="color: gray;">⏭️ Étape ignorée (pas d'action)</p>
    @endif

    <hr>

    <h2>📊 RÉSUMÉ DE LA CHAÎNE</h2>
    <table border="1" cellpadding="5" style="width: 100%; font-size: 10pt;">
        <tr>
            <th>Niveau</th>
            <th>Statut</th>
            <th>Valeur</th>
        </tr>
        <tr>
            <td>Engagement</td>
            <td style="background: lightgreen;">✅ OK</td>
            <td>{{ $engagement->numero }}</td>
        </tr>
        <tr>
            <td>Nomenclature</td>
            <td style="background: {{ $nomenclature ? 'lightgreen' : 'salmon' }};">
                {{ $nomenclature ? '✅ OK' : '❌ MANQUANT' }}</td>
            <td>{{ $nomenclature->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Tâche</td>
            <td style="background: {{ isset($tacheTrouvee) && $tacheTrouvee ? 'lightgreen' : 'salmon' }};">
                {{ isset($tacheTrouvee) && $tacheTrouvee ? '✅ OK' : '❌ MANQUANT' }}</td>
            <td>{{ $tacheTrouvee->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Activité</td>
            <td style="background: {{ isset($activiteTrouvee) && $activiteTrouvee ? 'lightgreen' : 'salmon' }};">
                {{ isset($activiteTrouvee) && $activiteTrouvee ? '✅ OK' : '❌ MANQUANT' }}</td>
            <td>{{ $activiteTrouvee->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Action</td>
            <td style="background: {{ isset($actionTrouvee) && $actionTrouvee ? 'lightgreen' : 'salmon' }};">
                {{ isset($actionTrouvee) && $actionTrouvee ? '✅ OK' : '❌ MANQUANT' }}</td>
            <td>{{ $actionTrouvee->libelle ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td>Programme</td>
            <td style="background: {{ isset($programmeTrouve) && $programmeTrouve ? 'lightgreen' : 'salmon' }};">
                {{ isset($programmeTrouve) && $programmeTrouve ? '✅ OK' : '❌ MANQUANT' }}</td>
            <td>{{ $programmeTrouve->libelle ?? 'N/A' }}</td>
        </tr>
    </table>
@endsection
