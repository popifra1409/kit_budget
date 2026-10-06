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
        $f = fn($v) => number_format((float) $v, 0, ',', ' ') . ' FCFA';

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
