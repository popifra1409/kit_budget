<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BordereauEngagementResource\Pages;
use App\Filament\Resources\BordereauEngagementResource\RelationManagers;
use App\Models\BordereauEngagement;
use App\Models\Budget;
use App\Models\Engagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class BordereauEngagementResource extends Resource
{
    protected static ?string $model = BordereauEngagement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Bordereaux d\'Engagement';

    protected static ?string $modelLabel = 'Bordereau d\'Engagement';

    protected static ?string $pluralModelLabel = 'Bordereaux d\'Engagement';

    protected static ?string $navigationGroup = 'Commandes & Engagement';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations principales')
                    ->schema([
                        Forms\Components\Select::make('budget_id')
                            ->label('Budget')
                            ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('instance_destinataire')
                            ->label('Instance destinataire')
                            ->placeholder('Ex: Contrôle Financier, Tutelle, Direction Générale')
                            ->maxLength(255)
                            ->helperText('Optionnel : sera renseigné lors de la transmission'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Objet')
                    ->schema([
                        Forms\Components\Textarea::make('objet')
                            ->label('Objet du bordereau')
                            ->required()
                            ->rows(3)
                            ->placeholder('Ex: Transmission engagements décembre 2026')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observations')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // ✅ NOUVELLE SECTION : Sélection des engagements
                Forms\Components\Section::make('Engagements à inclure')
                    ->description('Sélectionnez les engagements à attacher à ce bordereau')
                    ->schema([
                        Forms\Components\Select::make('engagements')
                            ->label('Engagements')
                            ->relationship(
                                name: 'engagements',
                                titleAttribute: 'numero',
                                modifyQueryUsing: fn($query) => $query
                                    ->where('statut', 'definitif')
                                    ->whereDoesntHave('bordereaux', function ($q) {
                                        $q->where('statut', 'valide');
                                    })
                                    ->orderBy('date_engagement', 'desc')
                            )
                            ->multiple()
                            ->preload()  // ← CRUCIAL : Charge toutes les options
                            ->searchable(['numero', 'objet', 'reference_document'])
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                sprintf(
                                    '%s - %s (%s FCFA) - %s',
                                    $record->numero,
                                    \Str::limit($record->objet, 50),
                                    number_format($record->montant_engage, 0, ',', ' '),
                                    $record->date_engagement->format('d/m/Y')
                                )
                            )
                            ->helperText('Seuls les engagements définitifs non encore validés dans un bordereau sont affichés')
                            ->columnSpanFull()
                            ->hiddenOn('edit'),  // Masquer en édition (utiliser le RelationManager)
                    ])
                    ->hiddenOn('edit'),  // Masquer toute la section en édition

                Forms\Components\Section::make('Informations automatiques')
                    ->schema([
                        Forms\Components\Placeholder::make('numero')
                            ->label('Numéro')
                            ->content(fn($record) => $record?->numero ?? 'Généré automatiquement'),

                        Forms\Components\Placeholder::make('nombre_engagements')
                            ->label('Nombre d\'engagements')
                            ->content(fn($record) => $record?->nombre_engagements ?? 0),

                        Forms\Components\Placeholder::make('montant_total')
                            ->label('Montant total')
                            ->content(
                                fn($record) =>
                                $record ? number_format($record->montant_total, 0, ',', ' ') . ' FCFA' : '0 FCFA'
                            ),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° Bordereau')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('budget.code')
                    ->label('Budget')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date émission')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('emetteur.name')
                    ->label('Émis par')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('instance_destinataire')
                    ->label('Destinataire')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('nombre_engagements')
                    ->label('Nb Eng.')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('montant_total')
                    ->label('Montant Total')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'info' => 'transmis',
                        'warning' => 'en_cours',
                        'success' => 'valide',
                        'danger' => fn($state) => in_array($state, ['rejete_total', 'rejete_partiel']),
                        'gray' => 'retourne',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon' => 'Brouillon',
                        'transmis' => 'Transmis',
                        'en_cours' => 'En cours',
                        'valide' => 'Validé',
                        'rejete_partiel' => 'Rejeté partiel',
                        'rejete_total' => 'Rejeté total',
                        'retourne' => 'Retourné',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->relationship('budget', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'transmis' => 'Transmis',
                        'en_cours' => 'En cours',
                        'valide' => 'Validé',
                        'rejete_partiel' => 'Rejeté partiel',
                        'rejete_total' => 'Rejeté total',
                        'retourne' => 'Retourné',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->estModifiable()),

                Tables\Actions\Action::make('transmettre')
                    ->label('Transmettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('instance_destinataire')
                            ->label('Instance destinataire')
                            ->required()
                            ->placeholder('Ex: Contrôle Financier')
                            ->helperText('Vers quelle instance transmettre ce bordereau ?'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $record->transmettre(auth()->user(), $data['instance_destinataire']);
                            Notification::make()
                                ->title('Bordereau transmis')
                                ->success()
                                ->body("Transmis à {$data['instance_destinataire']}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('receptionner')
                    ->label('Réceptionner')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('warning')
                    ->visible(fn($record) => $record->statut === 'transmis')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            $record->receptionner(auth()->user());
                            Notification::make()
                                ->title('Bordereau réceptionné')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EngagementsRelationManager::class,
            RelationManagers\MouvementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBordereauEngagements::route('/'),
            'create' => Pages\CreateBordereauEngagement::route('/create'),
            'edit' => Pages\EditBordereauEngagement::route('/{record}/edit'),
            'view' => Pages\ViewBordereauEngagement::route('/{record}'),
        ];
    }
}
