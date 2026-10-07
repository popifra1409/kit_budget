<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\ClotureExerciceResource\Pages;
use App\Filament\Budget\Resources\ClotureExerciceResource\RelationManagers\LignesRelationManager;
use App\Models\Budget;
use App\Models\ClotureExercice;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Clôture d'exercice : reports de crédits et annulations de fin de gestion. */
class ClotureExerciceResource extends Resource
{
    protected static ?string $model = ClotureExercice::class;
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?string $navigationLabel = "Clôture de l'exercice";
    protected static ?string $modelLabel = "clôture d'exercice";
    protected static ?string $pluralModelLabel = "clôtures d'exercice";
    protected static ?int $navigationSort = 90;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_cloture_exercice') ?? false;
    }
    public static function canView($record): bool
    {
        return static::canViewAny();
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('gerer_cloture_exercice') ?? false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return $record->statut === 'preparation' && static::canCreate();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Exercice à clôturer")->schema([
                Forms\Components\Select::make('exercice_id')
                    ->label('Exercice')
                    ->options(fn() => Exercice::whereIn('statut', ['actif', 'cloture'])->orderByDesc('annee')->pluck('annee', 'id'))
                    ->default(fn() => Exercice::getActif()?->id)
                    ->required()->live()
                    ->afterStateUpdated(fn(Forms\Set $set) => $set('budget_id', null)),
                Forms\Components\Select::make('budget_id')
                    ->label('Budget')
                    ->options(fn(Forms\Get $get) => Budget::withoutGlobalScope('exercice')
                        ->where('exercice_id', $get('exercice_id'))
                        ->get()->mapWithKeys(fn($b) => [$b->id => ($b->code ?? '') . ' — ' . $b->libelle . ($b->actif ? ' (actif)' : '')]))
                    ->required()
                    ->helperText('La situation des lignes est calculée à la création, puis recalculable tant que les reports ne sont pas arrêtés.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exercice.annee')->label('Exercice')->weight('bold'),
                Tables\Columns\TextColumn::make('budget.libelle')->label('Budget')->limit(30),
                Tables\Columns\TextColumn::make('statut')->badge()
                    ->formatStateUsing(fn($state) => ClotureExercice::STATUTS[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'preparation' => 'gray',
                        'arretee' => 'warning',
                        'avis_ca' => 'info',
                        'reprise' => 'success',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('totaux.report_retenu')->label('Reports')->numeric(0),
                Tables\Columns\TextColumn::make('totaux.annule')->label('Annulations')->numeric(0),
                Tables\Columns\TextColumn::make('date_calcul')->label('Calculée le')->dateTime('d/m/Y H:i'),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // ⚠️ Le paramètre doit s'appeler $state : Filament injecte les arguments d'après leur nom
        $f = fn($state) => number_format((float) $state, 0, ',', ' ') . ' FCFA';

        return $infolist->schema([
            Infolists\Components\Section::make('Synthèse : dotation = payé + reporté + annulé')->schema([
                Infolists\Components\TextEntry::make('exercice.annee')->label('Exercice')->weight('bold'),
                Infolists\Components\TextEntry::make('statut')->badge()->formatStateUsing(fn($state) => ClotureExercice::STATUTS[$state] ?? $state),
                Infolists\Components\TextEntry::make('totaux.dotation')->label('Dotation actualisée')->formatStateUsing($f),
                Infolists\Components\TextEntry::make('totaux.paye')->label('Payé')->formatStateUsing($f),
                Infolists\Components\TextEntry::make('totaux.engage_non_paye')->label('Engagé non payé')->formatStateUsing($f),
                Infolists\Components\TextEntry::make('totaux.report_retenu')->label('Reports retenus')->formatStateUsing($f)->weight('bold')->color('info')
                    ->helperText(fn($record) => ($record->totaux['lignes_reportees'] ?? 0) . ' ligne(s) ; proposé : ' . $f($record->totaux['report_propose'] ?? 0)),
                Infolists\Components\TextEntry::make('totaux.annule')->label('Annulations')->formatStateUsing($f)->weight('bold')
                    ->helperText(fn($record) => ($record->totaux['taux_annulation'] ?? 0) . ' % de la dotation'),
                Infolists\Components\TextEntry::make('totaux.non_reporte_non_paye')->label('Engagé non payé NON reporté')->formatStateUsing($f)
                    ->color(fn($state) => (float) $state > 0 ? 'danger' : 'success')
                    ->helperText('Dettes engagées sans crédit en N+1 : à solder, annuler ou reprendre.'),
                Infolists\Components\TextEntry::make('report_fonctionnement_autorise')->label('Report en fonctionnement')
                    ->formatStateUsing(fn($state) => $state ? 'Autorisé (paramètre)' : 'Non autorisé (investissement seul)'),
            ])->columns(3),

            Infolists\Components\Section::make("Période d'exécution")
                ->schema([
                    Infolists\Components\TextEntry::make('phase_execution')->label('Phase')
                        ->getStateUsing(fn($record) => app(\App\Services\Budget\PeriodeExecutionService::class)->situation($record->exercice)['libelle'])
                        ->badge()
                        ->color(fn($record) => match (app(\App\Services\Budget\PeriodeExecutionService::class)->situation($record->exercice)['phase']) {
                            'gestion' => 'success',
                            'complementaire' => 'warning',
                            default => 'gray'
                        }),
                    Infolists\Components\TextEntry::make('fin_gestion')->label('Fin de la gestion (engagements)')
                        ->getStateUsing(fn($record) => app(\App\Services\Budget\PeriodeExecutionService::class)->situation($record->exercice)['fin_gestion']->format('d/m/Y')),
                    Infolists\Components\TextEntry::make('fin_complementaire')->label('Fin de la période complémentaire')
                        ->getStateUsing(fn($record) => app(\App\Services\Budget\PeriodeExecutionService::class)->situation($record->exercice)['fin_complementaire']->format('d/m/Y'))
                        ->helperText(fn($record) => ($j = app(\App\Services\Budget\PeriodeExecutionService::class)->situation($record->exercice)['jours_restants']) !== null
                            ? "{$j} jour(s) restant(s) dans la phase en cours" : 'Liquidation, ordonnancement et paiement de N bloqués'),
                    Infolists\Components\TextEntry::make('operations_a_regulariser')->label('À régulariser avant la fin de la période')
                        ->getStateUsing(function ($record) {
                            $e = static::etats($record)['etats'];
                            return 'DENO ' . number_format((float) $e['deno']['montant'], 0, ',', ' ')
                                . ' · reste à payer ' . number_format((float) $e['reste_a_payer']['montant'], 0, ',', ' ') . ' FCFA';
                        })
                        ->columnSpanFull(),
                ])->columns(3)->collapsible(),

            Infolists\Components\Section::make('États de fin de gestion')
                ->description('Calculés à la date d\'affichage, sur le budget de la clôture.')
                ->schema([
                    Infolists\Components\TextEntry::make('etat_rar')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_ETATS['rar'])
                        ->getStateUsing(fn($record) => number_format((float) static::etats($record)['etats']['rar']['montant'], 0, ',', ' ') . ' FCFA')
                        ->helperText(fn($record) => static::etats($record)['etats']['rar']['note']),
                    Infolists\Components\TextEntry::make('etat_deno')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_ETATS['deno'])
                        ->getStateUsing(fn($record) => number_format((float) static::etats($record)['etats']['deno']['montant'], 0, ',', ' ') . ' FCFA'),
                    Infolists\Components\TextEntry::make('etat_reste_a_payer')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_ETATS['reste_a_payer'])
                        ->getStateUsing(fn($record) => number_format((float) static::etats($record)['etats']['reste_a_payer']['montant'], 0, ',', ' ') . ' FCFA')
                        ->helperText(fn($record) => static::etats($record)['etats']['reste_a_payer']['nombre'] . ' OP'),
                    Infolists\Components\TextEntry::make('etat_arrieres')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_ETATS['arrieres'])
                        ->getStateUsing(fn($record) => number_format((float) static::etats($record)['etats']['arrieres']['montant'], 0, ',', ' ') . ' FCFA')
                        ->color(fn($state) => str_starts_with((string) $state, '0 ') ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('etat_dette')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_ETATS['dette'])
                        ->getStateUsing(fn($record) => number_format((float) static::etats($record)['etats']['dette']['montant'], 0, ',', ' ') . ' FCFA')
                        ->weight('bold')->helperText(fn($record) => 'dont engagé sans service fait : ' . number_format((float) static::etats($record)['etats']['dette']['engage_sans_service_fait'], 0, ',', ' ')),
                ])->columns(3)->collapsible(),

            Infolists\Components\Section::make('Taux de fin de gestion')
                ->schema([
                    Infolists\Components\TextEntry::make('taux_engagement')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_TAUX['engagement'])
                        ->getStateUsing(fn($record) => ($v = static::etats($record)['taux']['engagement']['valeur']) === null ? '—' : $v . ' %')
                        ->helperText(fn($record) => static::etats($record)['taux']['engagement']['libelle_base'] . ' : ' . number_format((float) static::etats($record)['taux']['engagement']['base'], 0, ',', ' '))
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('taux_liquidation')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_TAUX['liquidation'])
                        ->getStateUsing(fn($record) => ($v = static::etats($record)['taux']['liquidation']['valeur']) === null ? '—' : $v . ' %')
                        ->helperText(fn($record) => static::etats($record)['taux']['liquidation']['libelle_base'] . ' : ' . number_format((float) static::etats($record)['taux']['liquidation']['base'], 0, ',', ' '))
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('taux_ordonnancement')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_TAUX['ordonnancement'])
                        ->getStateUsing(fn($record) => ($v = static::etats($record)['taux']['ordonnancement']['valeur']) === null ? '—' : $v . ' %')
                        ->helperText(fn($record) => static::etats($record)['taux']['ordonnancement']['libelle_base'] . ' : ' . number_format((float) static::etats($record)['taux']['ordonnancement']['base'], 0, ',', ' '))
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('taux_paiement')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_TAUX['paiement'])
                        ->getStateUsing(fn($record) => ($v = static::etats($record)['taux']['paiement']['valeur']) === null ? '—' : $v . ' %')
                        ->helperText(fn($record) => static::etats($record)['taux']['paiement']['libelle_base'] . ' : ' . number_format((float) static::etats($record)['taux']['paiement']['base'], 0, ',', ' '))
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('taux_recouvrement')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_TAUX['recouvrement'])
                        ->getStateUsing(fn($record) => ($v = static::etats($record)['taux']['recouvrement']['valeur']) === null ? '—' : $v . ' %')
                        ->helperText(fn($record) => static::etats($record)['taux']['recouvrement']['libelle_base'] . ' : ' . number_format((float) static::etats($record)['taux']['recouvrement']['base'], 0, ',', ' '))
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('taux_realisation_physique')->label(\App\Services\Budget\EtatsClotureService::LIBELLES_TAUX['realisation_physique'])
                        ->getStateUsing(fn($record) => ($v = static::etats($record)['taux']['realisation_physique']['valeur']) === null ? '—' : $v . ' %')
                        ->helperText(fn($record) => static::etats($record)['taux']['realisation_physique']['libelle_base'] . ' : ' . number_format((float) static::etats($record)['taux']['realisation_physique']['base'], 0, ',', ' '))
                        ->weight('bold'),
                ])->columns(3)->collapsible(),

            Infolists\Components\Section::make('Reprise en N+1')
                ->visible(fn($record) => $record->collectif_reports_id !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('collectifReports.numero')->label('Collectif de reports')
                        ->badge()->color('success')
                        ->helperText(fn($record) => 'Exercice ' . ($record->bilan_reprise['exercice_suivant'] ?? '?') . ' — statut : ' . ($record->collectifReports?->statut ?? '?')),
                    Infolists\Components\TextEntry::make('bilan_reprise.montant_repris')->label('Montant repris')
                        ->formatStateUsing(fn($state) => number_format((float) $state, 0, ',', ' ') . ' FCFA')
                        ->helperText(fn($record) => ($record->bilan_reprise['lignes_reprises'] ?? 0) . ' ligne(s)'),
                    Infolists\Components\TextEntry::make('bilan_reprise.recette_equilibre')->label("Recette d'équilibre")
                        ->placeholder('⚠️ À ajouter au collectif')->color(fn($state) => $state ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('lignes_manquantes')->label('Lignes absentes du budget N+1 (à créer)')
                        ->getStateUsing(fn($record) => collect($record->bilan_reprise['lignes_manquantes'] ?? [])
                            ->map(fn($l) => "{$l['code']} — {$l['libelle']} : " . number_format($l['montant'], 0, ',', ' '))->implode("\n") ?: 'Aucune')
                        ->color(fn($state) => $state === 'Aucune' ? 'success' : 'danger')
                        ->columnSpanFull(),
                ])->columns(3),

            Infolists\Components\Section::make('Actes')->schema([
                Infolists\Components\TextEntry::make('reference_arrete')->label("Arrêté de l'ordonnateur")->placeholder('—')
                    ->helperText(fn($record) => $record->date_arrete?->format('d/m/Y')),
                Infolists\Components\TextEntry::make('avis_ca')->label('Avis du CA')->placeholder('—')
                    ->formatStateUsing(fn($state) => $state === 'conforme' ? '✅ Conforme' : '❌ Défavorable')
                    ->helperText(fn($record) => trim(($record->reference_avis_ca ?? '') . ' ' . ($record->date_avis_ca?->format('d/m/Y') ?? '')) ?: null),
                Infolists\Components\TextEntry::make('observations')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    /** États et taux de fin de gestion, calculés une fois par affichage. */
    protected static array $etatsCache = [];

    public static function etats($record): array
    {
        return static::$etatsCache[$record->id] ??= app(\App\Services\Budget\EtatsClotureService::class)
            ->calculer($record->exercice, $record->budget);
    }

    public static function getRelations(): array
    {
        return [LignesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCloturesExercice::route('/'),
            'create' => Pages\CreateClotureExercice::route('/create'),
            'view'   => Pages\ViewClotureExercice::route('/{record}'),
        ];
    }
}
