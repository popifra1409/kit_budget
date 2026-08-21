<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\FactureProformaResource\Pages;
use App\Filament\Budget\Resources\FactureProformaResource\RelationManagers\LignesRelationManager;
use App\Models\FactureProforma;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FactureProformaResource extends Resource
{
    protected static ?string $model = FactureProforma::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationLabel = 'Factures Proforma';
    protected static ?string $modelLabel = 'Facture Proforma';
    protected static ?string $pluralModelLabel = 'Factures Proforma';
    protected static ?string $navigationGroup = 'Gestion Budgétaire';
    protected static ?int $navigationSort = 4;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_facture_proforma') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_facture_proforma') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_facture_proforma') ?? false;
    }

    public static function canEdit($record): bool
    {
        return (auth()->user()?->can('update_facture_proforma') ?? false)
            && $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('delete_facture_proforma') ?? false)
            && $record->estModifiable();
    }

    // ========================================
    // FORM
    // ========================================

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations de la facture proforma')
                ->schema([
                    Forms\Components\Select::make('exercice_id')
                        ->label('Exercice')
                        ->relationship('exercice', 'annee')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn() => Exercice::getActif()?->id),

                    Forms\Components\TextInput::make('numero')
                        ->label('Numéro')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Généré automatiquement')
                        ->visibleOn('edit'),

                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('raison_sociale')->required(),
                        ]),

                    Forms\Components\DatePicker::make('date_facture')
                        ->label('Date de la facture proforma')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),

                    Forms\Components\TextInput::make('objet')
                        ->label('Objet')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options([
                            'brouillon' => 'Brouillon',
                            'validee'   => 'Validée',
                            'utilisee'  => 'Utilisée (BC généré)',
                            'expiree'   => 'Expirée',
                            'annulee'   => 'Annulée',
                        ])
                        ->default('brouillon')
                        ->disabled(fn($record) => $record && !$record->estModifiable())
                        ->dehydrated(fn($record) => !$record || $record->estModifiable()),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('fournisseur.raison_sociale')
                    ->label('Fournisseur')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('date_facture')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant_ttc')
                    ->label('Montant TTC')
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', ' ') . ' FCFA')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Lignes')
                    ->counts('lignes')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'success'   => 'validee',
                        'info'      => 'utilisee',
                        'warning'   => 'expiree',
                        'danger'    => 'annulee',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'brouillon' => 'Brouillon',
                        'validee'   => 'Validée',
                        'utilisee'  => 'Utilisée',
                        'expiree'   => 'Expirée',
                        'annulee'   => 'Annulée',
                        default     => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'validee'   => 'Validée',
                        'utilisee'  => 'Utilisée',
                        'expiree'   => 'Expirée',
                        'annulee'   => 'Annulée',
                    ]),

                Tables\Filters\SelectFilter::make('fournisseur_id')
                    ->label('Fournisseur')
                    ->relationship('fournisseur', 'raison_sociale')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('valider')
                        ->label('Valider')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn($record) => $record->statut === 'brouillon')
                        ->requiresConfirmation()
                        ->modalDescription('Une fois validée, la facture proforma ne sera plus modifiable (mais restera utilisable pour générer un BC).')
                        ->action(function ($record) {
                            if ($record->lignes()->doesntExist()) {
                                Notification::make()
                                    ->title('❌ Impossible de valider')
                                    ->body('Ajoutez au moins une ligne avant de valider.')
                                    ->danger()->send();
                                return;
                            }
                            $record->update(['statut' => 'validee']);
                            Notification::make()->title('✅ Facture proforma validée')->success()->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ========================================
    // RELATIONS
    // ========================================

    public static function getRelations(): array
    {
        return [
            LignesRelationManager::class,
        ];
    }

    // ========================================
    // PAGES
    // ========================================

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFactureProformas::route('/'),
            'create' => Pages\CreateFactureProforma::route('/create'),
            'view'   => Pages\ViewFactureProforma::route('/{record}'),
            'edit'   => Pages\EditFactureProforma::route('/{record}/edit'),
        ];
    }
}
