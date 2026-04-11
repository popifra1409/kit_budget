<?php

namespace App\Filament\Comptable\Resources;

use App\Filament\Comptable\Resources\ExpressionBesoinResource\Pages;
use App\Models\ExpressionBesoin;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ExpressionBesoinResource extends Resource
{
    protected static ?string $model           = ExpressionBesoin::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Expressions de Besoins';
    protected static ?string $modelLabel      = 'Expression de Besoin';
    protected static ?string $pluralModelLabel = 'Expressions de Besoins';
    protected static ?string $navigationGroup = 'Comptabilité Matières';
    protected static ?int    $navigationSort  = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Informations générales')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('N° Expression')
                            ->default(fn() => ExpressionBesoin::genererNumero())
                            ->disabled()->dehydrated(),

                        Forms\Components\DatePicker::make('date_expression')
                            ->label('Date')->default(now())->required(),

                        Forms\Components\DatePicker::make('date_besoin')
                            ->label('Date besoin souhaitée'),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('service_demandeur')
                            ->label('Service demandeur')->required(),

                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->relationship('exercice', 'annee')
                            ->default(fn() => \App\Models\Exercice::getActif()?->id)
                            ->required(),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('responsable_service_id')
                            ->label('Responsable service')
                            ->relationship('responsableService', 'name')
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('comptable_matieres_id')
                            ->label('Comptable-matières')
                            ->relationship('comptableMatieres', 'name')
                            ->searchable()->preload()->required(),
                    ]),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet')->rows(2)->columnSpanFull(),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Articles demandés')
                ->schema([
                    Forms\Components\Repeater::make('lignes')
                        ->relationship('lignes')
                        ->schema([
                            Forms\Components\Grid::make(12)->schema([
                                Forms\Components\Select::make('article_id')
                                    ->label('Article')
                                    ->options(
                                        fn() => Article::actif()
                                            ->orderBy('designation')
                                            ->get()
                                            ->mapWithKeys(fn($a) => [
                                                $a->id => "[{$a->code}] {$a->designation}"
                                            ])
                                    )
                                    ->searchable()->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $article = Article::find($state);
                                            $set('prix_unitaire_estime', $article?->prix_unitaire_moyen ?? 0);
                                        }
                                    })
                                    ->columnSpan(5),

                                Forms\Components\TextInput::make('quantite_demandee')
                                    ->label('Qté demandée')
                                    ->numeric()->required()->default(1)
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                Forms\Components\Placeholder::make('stock_dispo')
                                    ->label('En stock')
                                    ->content(function (Get $get) {
                                        $articleId = $get('article_id');
                                        if (!$articleId) return '—';
                                        $stock = \App\Models\Stock::where('article_id', $articleId)->first();
                                        $qte = $stock?->quantite_disponible ?? 0;
                                        return new \Illuminate\Support\HtmlString(
                                            "<span style='color:" . ($qte > 0 ? '#166534' : '#991b1b') . ";font-weight:600;'>{$qte}</span>"
                                        );
                                    })
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('a_commander')
                                    ->label('À commander')
                                    ->content(function (Get $get) {
                                        $articleId = $get('article_id');
                                        $demande   = intval($get('quantite_demandee') ?? 0);
                                        if (!$articleId || !$demande) return '—';
                                        $stock = \App\Models\Stock::where('article_id', $articleId)->first();
                                        $dispo = $stock?->quantite_disponible ?? 0;
                                        $aCommander = max(0, $demande - $dispo);
                                        return new \Illuminate\Support\HtmlString(
                                            "<span style='color:" . ($aCommander > 0 ? '#854d0e' : '#166534') . ";font-weight:600;'>{$aCommander}</span>"
                                        );
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('prix_unitaire_estime')
                                    ->label('P.U estimé')
                                    ->numeric()->suffix('FCFA')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('justification')
                                    ->label('Justification')->rows(1)
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter un article')
                        ->reorderable()
                        ->orderColumn('ordre')
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => Article::find($state['article_id'] ?? null)?->designation),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('date_expression')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('service_demandeur')
                    ->label('Service')->searchable()->limit(25),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Articles')->counts('lignes')->badge()->color('info'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning'   => 'soumis',
                        'success'   => 'valide',
                        'info'      => 'en_commande',
                        'primary'   => 'satisfait',
                        'danger'    => fn($state) => in_array($state, ['rejete', 'annule']),
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon'   => 'Brouillon',
                        'soumis'      => 'Soumis',
                        'valide'      => 'Validé',
                        'en_commande' => 'En commande',
                        'satisfait'   => 'Satisfait',
                        'annule'      => 'Annulé',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->estModifiable()),

                Tables\Actions\Action::make('soumettre')
                    ->label('Soumettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['statut' => 'soumis']);
                        Notification::make()->title('✅ Expression soumise')->success()->send();
                    }),

                Tables\Actions\Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->statut === 'soumis')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('ordonnateur_id')
                            ->label('Ordonnateur-matières')
                            ->relationship('ordonnateur', 'name')
                            ->searchable()->preload()->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'statut'         => 'valide',
                            'ordonnateur_id' => $data['ordonnateur_id'],
                            'date_validation' => now()->toDateString(),
                        ]);
                        Notification::make()->title('✅ Expression validée')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpressionBesoins::route('/'),
            'create' => Pages\CreateExpressionBesoin::route('/create'),
            'view'   => Pages\ViewExpressionBesoin::route('/{record}'),
            'edit'   => Pages\EditExpressionBesoin::route('/{record}/edit'),
        ];
    }
}
