<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\DecisionPrevisionnelleResource\Pages;
use App\Models\DecisionAdministrative;
use App\Models\TypeDecision;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class DecisionPrevisionnelleResource extends Resource
{
    protected static ?string $model = DecisionAdministrative::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Décisions Prévisionnelles';
    protected static ?string $modelLabel = 'Décision Prévisionnelle';
    protected static ?string $pluralModelLabel = 'Décisions Prévisionnelles';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int $navigationSort = 35;

    // ✅ Filtrer uniquement les décisions prévisionnelles
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('est_previsionnel', true);
    }

    // ── Permissions ───────────────────────────────────────────
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_decision_administrative') ?? false;
    }
    public static function canView($record): bool
    {
        return auth()->user()?->can('view_decision_administrative') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_decision_administrative') ?? false;
    }
    public static function canEdit($record): bool
    {
        return (auth()->user()?->can('update_decision_administrative') ?? false) && $record->statut === 'brouillon';
    }
    public static function canDelete($record): bool
    {
        return (auth()->user()?->can('delete_decision_administrative') ?? false) && $record->statut === 'brouillon';
    }

    public static function form(Form $form): Form
    {
        // ✅ Réutilise exactement le même formulaire que DA normale
        // en ajoutant juste le champ est_previsionnel = true (caché)
        $daForm = DecisionAdministrativeResource::form($form);

        return $form->schema([
            // ✅ Badge informatif en haut
            Forms\Components\Placeholder::make('info_previsionnel')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    '<div style="background:#fef3c7;border:1px solid #d97706;border-radius:.5rem;
                                 padding:.75rem 1rem;font-size:.85rem;display:flex;align-items:center;gap:.5rem;">
                        <svg width="18" height="18" fill="none" stroke="#d97706" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span>
                            <strong>Décision Prévisionnelle</strong> — L\'engagement créé ne déduira
                            <strong>pas</strong> de montant du budget. Simulation uniquement.
                        </span>
                    </div>'
                ))
                ->columnSpanFull(),

            // ✅ Champ caché — force est_previsionnel = true
            Forms\Components\Hidden::make('est_previsionnel')->default(true),

            // ✅ Reste du formulaire DA (réutilisation totale)
            ...$daForm->getComponents(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->searchable()->sortable()->weight('bold')->copyable(),

                // ✅ Badge distinctif
                Tables\Columns\BadgeColumn::make('type_label')
                    ->label('')
                    ->state('🔮 PRÉVISIONNELLE')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('date_decision')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('typeDecision.libelle')
                    ->label('Type')->badge()->color('info'),

                Tables\Columns\TextColumn::make('personnel.nom_complet')
                    ->label('Bénéficiaire')->searchable()->limit(25)
                    ->formatStateUsing(
                        fn($record) =>
                        $record->type_beneficiaire === 'personnel'
                        ? ($record->personnel?->nom . ' ' . $record->personnel?->prenoms)
                        : $record->fournisseur?->raison_sociale ?? '—'
                    ),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant Net')->money('XAF')->sortable()->alignEnd()->weight('bold'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning' => 'validee',
                        'success' => 'engagee',
                        'primary' => fn($state) => str_contains($state ?? '', 'op'),
                        'danger' => 'annulee',
                    ]),

                // ✅ Indique si convertie en DA réelle
                Tables\Columns\IconColumn::make('est_converti')
                    ->label('Convertie')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'validee' => 'Validée',
                        'engagee' => 'Engagée',
                        'annulee' => 'Annulée',
                    ]),
                Tables\Filters\TernaryFilter::make('da_reelle_id')
                    ->label('Convertie en DA réelle')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->statut === 'brouillon'),

                // ── Valider ────────────────────────────────────
                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->valider(auth()->user());
                        Notification::make()->title('✅ Décision prévisionnelle validée')->success()->send();
                    }),

                // ── Engager (sans déduction budget) ───────────
                Tables\Actions\Action::make('engager')
                    ->label('Simuler engagement')
                    ->icon('heroicon-o-calculator')
                    ->color('primary')
                    ->visible(fn($record) => in_array($record->statut, ['validee', 'valide']) && !$record->engagee)
                    ->form([
                        Forms\Components\Select::make('nomenclature_id')
                            ->label('Nomenclature budgétaire (simulation)')
                            ->options(
                                fn($record) =>
                                \App\Models\LigneBudgetaire::where('budget_id', $record->budget_id)
                                    ->with('nomenclature')
                                    ->get()
                                    ->filter(fn($l) => $l->nomenclature)
                                    ->mapWithKeys(fn($l) => [
                                        $l->nomenclature_id =>
                                            $l->nomenclature->code . ' - ' . $l->nomenclature->libelle .
                                            ' (Dispo: ' . number_format($l->disponible_engagement, 0, ',', ' ') . ' FCFA)'
                                    ])
                            )
                            ->required()->searchable()
                            ->helperText('⚠️ Aucune déduction ne sera effectuée sur cette ligne'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $engagement = $record->engagerBudget($data['nomenclature_id']);
                            Notification::make()
                                ->title('🔮 Engagement simulé')
                                ->info()
                                ->body("Engagement N° {$engagement->numero} créé (simulé — budget non impacté)")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                        }
                    }),

                // ── Convertir en DA réelle ─────────────────────
                Tables\Actions\Action::make('convertir_en_da_reelle')
                    ->label('→ DA Réelle')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->statut !== 'annulee' && !$record->est_converti)
                    ->requiresConfirmation()
                    ->modalHeading('Convertir en Décision Administrative réelle')
                    ->modalDescription('Une nouvelle DA réelle sera créée avec les mêmes données. L\'engagement réel déduira du budget.')
                    ->action(function ($record) {
                        try {
                            $daReelle = $record->convertirEnDAReelle();
                            Notification::make()
                                ->title('✅ Convertie en DA réelle')
                                ->success()
                                ->body("DA N° {$daReelle->numero} créée. Engagez-la pour impacter le budget.")
                                ->send();

                            // Rediriger vers la DA réelle
                            redirect(DecisionAdministrativeResource::getUrl('view', ['record' => $daReelle->id]));
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDecisionsPrevisionnelles::route('/'),
            'create' => Pages\CreateDecisionPrevisionnelle::route('/create'),
            'view' => Pages\ViewDecisionPrevisionnelle::route('/{record}'),
            'edit' => Pages\EditDecisionPrevisionnelle::route('/{record}/edit'),
        ];
    }
}