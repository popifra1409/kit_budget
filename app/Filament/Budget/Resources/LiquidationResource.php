<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\LiquidationResource\Pages;
use App\Filament\Budget\Resources\LiquidationResource\RelationManagers\PreuvesRelationManager;
use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\NatureServiceFait;
use App\Services\Budget\LiquidationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Liquidation des dépenses : du service fait à la dette.
 * Circuit : comptable matières (service fait) → ordonnateur (liquidation) → contrôleur financier (visa).
 */
class LiquidationResource extends Resource
{
    protected static ?string $model = Liquidation::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?string $navigationLabel = 'Liquidations';
    protected static ?string $modelLabel = 'liquidation';
    protected static ?string $pluralModelLabel = 'liquidations';
    protected static ?int $navigationSort = 30;

    // ── Droits ──────────────────────────────────────────────
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_liquidation') ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_liquidation') ?? false;
    }

    /** Pas de modification directe : la liquidation évolue par son circuit (actions dédiées). */
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return $record->statut === 'brouillon' && (auth()->user()?->can('create_liquidation') ?? false);
    }

    // ── Formulaire de création ──────────────────────────────
    public static function form(Form $form): Form
    {
        $service = app(LiquidationService::class);

        return $form->schema([
            Forms\Components\Section::make('Engagement à liquider')->schema([
                Forms\Components\Select::make('engagement_id')
                    ->label('Engagement')
                    ->options(fn() => Engagement::where('statut', 'definitif')
                        ->orderByDesc('date_engagement')
                        ->get()
                        ->filter(fn($e) => $service->resteALiquider($e) > 0)
                        ->mapWithKeys(fn($e) => [$e->id => "{$e->numero} — " . \Str::limit($e->objet, 50)
                            . ' (reste ' . number_format($service->resteALiquider($e), 0, ',', ' ') . ' FCFA)']))
                    ->searchable()->required()->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) use ($service) {
                        $e = $state ? Engagement::find($state) : null;
                        $set('montant_liquide', $e ? $service->resteALiquider($e) : null);
                        $set('reception_id', null);
                    })
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('recap')
                    ->label('')
                    ->visible(fn(Forms\Get $get) => filled($get('engagement_id')))
                    ->content(function (Forms\Get $get) use ($service) {
                        $e = Engagement::find($get('engagement_id'));
                        if (!$e) return '';
                        return new \Illuminate\Support\HtmlString(
                            '<div class="text-sm">Bénéficiaire : <strong>' . e($e->getNomBeneficiaire() ?? '—') . '</strong><br>'
                                . 'Engagé : <strong>' . number_format((float) $e->montant_engage, 0, ',', ' ') . ' FCFA</strong> · '
                                . 'Déjà liquidé : ' . number_format($service->montantLiquide($e), 0, ',', ' ') . ' FCFA</div>'
                        );
                    })
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Service fait')->schema([
                Forms\Components\Select::make('nature_service_fait_id')
                    ->label('Nature du service fait')
                    ->options(fn() => NatureServiceFait::actives()->pluck('libelle', 'id'))
                    ->required()
                    ->helperText('Détermine les preuves attendues (paramétrables).'),

                Forms\Components\Select::make('reception_id')
                    ->label('Réception conforme (comptabilité matières)')
                    ->options(fn(Forms\Get $get) => ($e = Engagement::find($get('engagement_id')))
                        ? $service->receptionsConformes($e)->mapWithKeys(fn($record) => [$record->id => "{$record->numero} du " . $record->date_reception?->format('d/m/Y')])
                        : [])
                    ->visible(fn(Forms\Get $get) => ($e = Engagement::find($get('engagement_id'))) && $service->receptionsConformes($e)->isNotEmpty())
                    ->helperText('Pré-renseigne les preuves (bon de livraison, PV de réception, facture).'),

                Forms\Components\DatePicker::make('date_service_fait')->label('Date du service fait')->default(now())->required(),

                Forms\Components\TextInput::make('montant_liquide')
                    ->label('Montant à liquider')->numeric()->required()->suffix('FCFA')
                    ->helperText('Cette version liquide la totalité du reste de l\'engagement.')
                    ->disabled()->dehydrated(),

                Forms\Components\Textarea::make('observations')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    // ── Liste ───────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn(\Illuminate\Database\Eloquent\Builder $query) => $query->with(['engagement', 'nature']))
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->weight('bold')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('engagement.numero')->label('Engagement')->searchable(),
                Tables\Columns\TextColumn::make('beneficiaire')->label('Bénéficiaire')
                    ->getStateUsing(fn($record) => $record->engagement?->getNomBeneficiaire())->limit(30),
                Tables\Columns\TextColumn::make('nature.libelle')->label('Nature')->limit(25)->toggleable(),
                Tables\Columns\TextColumn::make('montant_liquide')->label('Montant')->money('XAF')->sortable(),
                Tables\Columns\TextColumn::make('statut')->badge()
                    ->formatStateUsing(fn($state) => Liquidation::STATUTS[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'visee' => 'success',
                        'liquidee' => 'info',
                        'service_fait_certifie' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('date_liquidation')->label('Liquidée le')->date('d/m/Y')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('date_echeance_paiement')->label('Échéance paiement')->date('d/m/Y')->sortable()
                    ->description(fn($record) => ($j = $record->joursAvantEcheance()) === null ? null : ($j >= 0 ? "J-{$j}" : 'Dépassée de ' . abs($j) . ' j')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options(Liquidation::STATUTS)->multiple(),
                Tables\Filters\SelectFilter::make('nature_service_fait_id')->label('Nature')->relationship('nature', 'libelle'),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    // ── Fiche ───────────────────────────────────────────────
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Liquidation')->schema([
                Infolists\Components\TextEntry::make('numero')->label('N°')->weight('bold'),
                Infolists\Components\TextEntry::make('statut')->badge()->formatStateUsing(fn($state) => Liquidation::STATUTS[$state] ?? $state),
                Infolists\Components\TextEntry::make('engagement.numero')->label('Engagement'),
                Infolists\Components\TextEntry::make('beneficiaire')->label('Bénéficiaire')->getStateUsing(fn($record) => $record->engagement?->getNomBeneficiaire()),
                Infolists\Components\TextEntry::make('nature.libelle')->label('Nature du service fait'),
                Infolists\Components\TextEntry::make('reception.numero')->label('Réception')->placeholder('—'),
                Infolists\Components\TextEntry::make('montant_liquide')->label('Montant de la dette')->money('XAF')->weight('bold'),
                Infolists\Components\TextEntry::make('date_service_fait')->label('Service fait le')->date('d/m/Y'),
                Infolists\Components\TextEntry::make('motif_rejet')->label('Dernier rejet')->color('danger')->visible(fn($record) => filled($record->motif_rejet))->columnSpanFull(),
            ])->columns(4),

            Infolists\Components\Section::make('Circuit')->schema([
                Infolists\Components\TextEntry::make('certificateur.name')->label('Service fait certifié par')->placeholder('—')
                    ->helperText(fn($record) => $record->date_certification?->format('d/m/Y H:i')),
                Infolists\Components\TextEntry::make('liquidateur.name')->label('Liquidé par')->placeholder('—')
                    ->helperText(fn($record) => $record->date_liquidation?->format('d/m/Y')),
                Infolists\Components\TextEntry::make('viseur.name')->label('Visé par (CF)')->placeholder('—')
                    ->helperText(fn($record) => $record->date_visa?->format('d/m/Y H:i')),
                Infolists\Components\TextEntry::make('date_echeance_paiement')->label('Échéance de paiement')->date('d/m/Y')->placeholder('Fixée à la liquidation')
                    ->helperText(fn($record) => $record->delai_paiement_jours ? "Délai de {$record->delai_paiement_jours} jours (en vigueur à la liquidation)" : null),
            ])->columns(4),

            Infolists\Components\Section::make('Contrôles de liquidation')
                ->visible(fn($record) => !empty($record->controles))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('controles')->label('')->schema([
                        Infolists\Components\TextEntry::make('libelle')->label('Contrôle'),
                        Infolists\Components\TextEntry::make('ok')->label('Résultat')
                            ->formatStateUsing(fn($state) => $state ? '✅ Conforme' : '❌ Non conforme'),
                        Infolists\Components\TextEntry::make('detail')->label('Détail'),
                    ])->columns(3),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [PreuvesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLiquidations::route('/'),
            'create' => Pages\CreateLiquidation::route('/create'),
            'view'   => Pages\ViewLiquidation::route('/{record}'),
        ];
    }
}
