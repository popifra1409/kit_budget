<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\PaiementExceptionnelResource\Pages;
use App\Models\Fournisseur;
use App\Models\LigneBudgetaire;
use App\Models\PaiementExceptionnel;
use App\Models\Personnel;
use App\Services\Budget\PaiementExceptionnelService;
use App\Services\StatistiquesBudgetaires;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Procédure exceptionnelle : paiement sans ordonnancement préalable,
 * avec autorisation, traçabilité et régularisation obligatoire.
 */
class PaiementExceptionnelResource extends Resource
{
    protected static ?string $model = PaiementExceptionnel::class;
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?string $navigationLabel = 'Paiements exceptionnels';
    protected static ?string $modelLabel = 'paiement exceptionnel';
    protected static ?string $pluralModelLabel = 'paiements exceptionnels';
    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_paiement_exceptionnel') ?? false;
    }
    public static function canView($record): bool
    {
        return static::canViewAny();
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_paiement_exceptionnel') ?? false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }   // le dossier évolue par son circuit
    public static function canDelete($record): bool
    {
        return $record->statut === 'brouillon' && static::canCreate();
    }

    public static function form(Form $form): Form
    {
        $service = app(PaiementExceptionnelService::class);

        return $form->schema([
            Forms\Components\Section::make('Bénéficiaire et imputation')->schema([
                Forms\Components\Select::make('beneficiaire_type')
                    ->label('Type de bénéficiaire')
                    ->options([Fournisseur::class => 'Fournisseur', Personnel::class => 'Agent (personnel)'])
                    ->required()->live()
                    ->afterStateUpdated(fn(Forms\Set $set) => $set('beneficiaire_id', null)),

                Forms\Components\Select::make('beneficiaire_id')
                    ->label('Bénéficiaire')
                    ->options(fn(Forms\Get $get) => match ($get('beneficiaire_type')) {
                        Fournisseur::class => Fournisseur::actifs()->orderBy('raison_sociale')->pluck('raison_sociale', 'id'),
                        Personnel::class   => Personnel::where('actif', true)->orderBy('nom')->get()->mapWithKeys(fn($p) => [$p->id => $p->nom_complet]),
                        default            => [],
                    })
                    ->searchable()->required(),

                Forms\Components\Select::make('ligne_budgetaire_id')
                    ->label('Ligne d\'imputation (budget actif)')
                    ->options(function () use ($service) {
                        $budget = StatistiquesBudgetaires::getVueEnsemble()['budget'] ?? null;
                        if (!$budget) return [];
                        return LigneBudgetaire::withoutGlobalScope('exercice')->where('budget_id', $budget->id)->with('nomenclature')->get()
                            ->filter(fn($l) => $l->nomenclature)
                            ->mapWithKeys(fn($l) => [$l->id => $l->nomenclature->code . ' — ' . \Str::limit($l->nomenclature->libelle, 45)
                                . ' (disponible net ' . number_format($service->disponibleNet($l), 0, ',', ' ') . ')']);
                    })
                    ->searchable()->required()
                    ->helperText('Le disponible net déduit les paiements exceptionnels non encore régularisés.'),

                Forms\Components\TextInput::make('montant')->numeric()->minValue(1)->required()->suffix('FCFA'),
            ])->columns(2),

            Forms\Components\Section::make('Objet et justification de la procédure exceptionnelle')->schema([
                Forms\Components\TextInput::make('objet')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\Select::make('motif_urgence')
                    ->label('Motif')->options(config('execution.motifs_paiement_exceptionnel', []))->required()->native(false),
                Forms\Components\TextInput::make('fondement')->label('Fondement (texte invoqué)')->maxLength(255),
                Forms\Components\Textarea::make('justification')
                    ->label('Justification de l\'impossibilité de suivre la procédure normale')->required()->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('nom_beneficiaire')->label('Bénéficiaire')->limit(30),
                Tables\Columns\TextColumn::make('objet')->limit(35)->searchable(),
                Tables\Columns\TextColumn::make('montant')->money('XAF')->sortable(),
                Tables\Columns\TextColumn::make('statut')->badge()
                    ->formatStateUsing(fn($state) => PaiementExceptionnel::STATUTS[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'regularise' => 'success',
                        'paye' => 'warning',
                        'autorise' => 'info',
                        'annule' => 'gray',
                        default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('date_limite_regularisation')->label('Régularisation avant le')->date('d/m/Y')->sortable()
                    ->badge()
                    ->color(fn($record) => $record->estEnRetard() ? 'danger' : (($j = $record->joursAvantRegularisation()) !== null && $j <= 7 ? 'warning' : 'gray'))
                    ->description(fn($record) => ($j = $record->joursAvantRegularisation()) === null ? null
                        : ($j >= 0 ? "J-{$j}" : 'En retard de ' . abs($j) . ' j')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options(PaiementExceptionnel::STATUTS)->multiple(),
                Tables\Filters\Filter::make('en_retard')->label('Régularisation en retard')
                    ->query(fn($query) => $query->where('statut', 'paye')->whereDate('date_limite_regularisation', '<', today())),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Paiement exceptionnel')->schema([
                Infolists\Components\TextEntry::make('numero')->weight('bold'),
                Infolists\Components\TextEntry::make('statut')->badge()->formatStateUsing(fn($state) => PaiementExceptionnel::STATUTS[$state] ?? $state),
                Infolists\Components\TextEntry::make('nom_beneficiaire')->label('Bénéficiaire'),
                Infolists\Components\TextEntry::make('montant')->money('XAF')->weight('bold'),
                Infolists\Components\TextEntry::make('ligneBudgetaire.nomenclature.code')->label('Imputation'),
                Infolists\Components\TextEntry::make('motif_urgence')->label('Motif')
                    ->formatStateUsing(fn($state) => config("execution.motifs_paiement_exceptionnel.{$state}", $state)),
                Infolists\Components\TextEntry::make('objet')->columnSpan(2),
                Infolists\Components\TextEntry::make('justification')->columnSpanFull(),
                Infolists\Components\TextEntry::make('fondement')->placeholder('—')->columnSpanFull(),
            ])->columns(4),

            Infolists\Components\Section::make('Circuit')->schema([
                Infolists\Components\TextEntry::make('reference_autorisation')->label('Autorisation')->placeholder('—')
                    ->helperText(fn($record) => $record->date_autorisation ? 'du ' . $record->date_autorisation->format('d/m/Y') . ' par ' . ($record->autorisateur?->name ?? '—') : null),
                Infolists\Components\TextEntry::make('reference_paiement')->label('Paiement')->placeholder('—')
                    ->helperText(fn($record) => $record->date_paiement ? 'le ' . $record->date_paiement->format('d/m/Y') . ' (' . ($record->mode_paiement ?? '—') . ')' : null),
                Infolists\Components\TextEntry::make('date_limite_regularisation')->label('Régulariser avant le')->date('d/m/Y')->placeholder('—')
                    ->helperText(fn($record) => $record->delai_regularisation_jours ? "Délai de {$record->delai_regularisation_jours} jours (en vigueur au paiement)" : null),
                Infolists\Components\TextEntry::make('ordonnancePaiement.numero')->label('OP de régularisation')->placeholder('—')
                    ->helperText(fn($record) => $record->date_regularisation ? 'le ' . $record->date_regularisation->format('d/m/Y') : null),
                Infolists\Components\TextEntry::make('observations')->placeholder('—')->columnSpanFull(),
            ])->columns(4),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPaiementsExceptionnels::route('/'),
            'create' => Pages\CreatePaiementExceptionnel::route('/create'),
            'view'   => Pages\ViewPaiementExceptionnel::route('/{record}'),
        ];
    }
}
