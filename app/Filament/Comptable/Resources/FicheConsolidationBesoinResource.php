<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\FicheConsolidationBesoinResource\Pages;
use App\Models\FicheConsolidationBesoin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FicheConsolidationBesoinResource extends Resource
{
    protected static ?string $model           = FicheConsolidationBesoin::class;
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Fiches de Consolidation';
    protected static ?string $modelLabel      = 'Fiche de Consolidation';
    protected static ?string $pluralModelLabel = 'Fiches de Consolidation';
    protected static ?string $navigationGroup = 'Acquisition des biens';
    protected static ?int    $navigationSort  = 21;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_fiche_consolidation_besoin') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_fiche_consolidation_besoin')
            && $record->statut === 'ouverte';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Fiche')
                ->schema([
                    Forms\Components\TextInput::make('numero')->disabled()->dehydrated(),
                    Forms\Components\Select::make('statut')
                        ->options(['ouverte' => 'Ouverte', 'cloturee' => 'Clôturée'])
                        ->required(),
                    Forms\Components\Textarea::make('observations')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations')
                ->schema([
                    Infolists\Components\TextEntry::make('numero')->label('N°')->weight('bold'),
                    Infolists\Components\TextEntry::make('comptableMatieres.name')->label('Comptable-matières'),
                    Infolists\Components\TextEntry::make('date_consolidation')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('statut')->badge(),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Expressions consolidées')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('expressions')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('numero')->label('N° Expression'),
                            Infolists\Components\TextEntry::make('serviceDemandeur.nom')->label('Service'),
                            Infolists\Components\TextEntry::make('statut')->badge(),
                        ])
                        ->columns(3),
                ]),

            Infolists\Components\Section::make('Besoins consolidés par article')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes_consolidees')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('article.designation')->label('Article'),
                            Infolists\Components\TextEntry::make('quantite_demandee')->label('Demandé'),
                            Infolists\Components\TextEntry::make('quantite_en_stock')->label('En stock'),
                            Infolists\Components\TextEntry::make('quantite_a_commander')->label('À commander')->color('warning'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('comptableMatieres.name')->label('Comptable-matières'),
                Tables\Columns\TextColumn::make('date_consolidation')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('expressions_count')->counts('expressions')->label('Expressions')->badge(),
                Tables\Columns\BadgeColumn::make('statut')
                    ->colors(['warning' => 'ouverte', 'success' => 'cloturee']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFicheConsolidationBesoins::route('/'),
            'view'  => Pages\ViewFicheConsolidationBesoin::route('/{record}'),
            'edit'  => Pages\EditFicheConsolidationBesoin::route('/{record}/edit'),
        ];
    }
}
