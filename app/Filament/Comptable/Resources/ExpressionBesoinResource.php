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
use Illuminate\Support\Facades\DB;

class ExpressionBesoinResource extends Resource
{
    protected static ?string $model           = ExpressionBesoin::class;
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Expressions de Besoins';
    protected static ?string $modelLabel      = 'Expression de Besoin';
    protected static ?string $pluralModelLabel = 'Expressions de Besoins';
    protected static ?string $navigationGroup = 'Acquisition des biens';
    protected static ?int    $navigationSort  = 20;


    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_expression_besoin') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_expression_besoin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_expression_besoin') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_expression_besoin')
            && $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_expression_besoin')
            && $record->estModifiable();
    }

    public static function canSoumettre($record): bool
    {
        return auth()->user()?->can('soumettre_expression_besoin') ?? false;
    }

    public static function canValider($record): bool
    {
        return auth()->user()?->can('valider_expression_besoin') ?? false;
    }

    public static function canSigner($record): bool
    {
        return auth()->user()?->can('signer_expression_besoin') ?? false;
    }

    public static function canConsolider(): bool
    {
        return auth()->user()?->can('consolider_expression_besoin') ?? false;
    }

    public static function canGenererBC($record): bool
    {
        return auth()->user()?->can('generer_bon_commande_expression_besoin') ?? false;
    }

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
                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice')
                            ->relationship('exercice', 'annee')
                            ->default(fn() => \App\Models\Exercice::getActif()?->id)
                            ->required(),

                        Forms\Components\Select::make('service_demandeur_id')
                            ->label('Service demandeur')
                            ->options(fn() => \App\Models\Service::where('actif', true)->pluck('nom', 'id'))
                            ->searchable()
                            ->preload()
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
                        ->label('Objet')->required()->rows(2)->columnSpanFull(),

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
                                    ->live(debounce: 600)
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

                                Forms\Components\Select::make('conditionnement_id')
                                    ->label('Conditionnement')
                                    ->options(fn() => \App\Models\Conditionnement::actif()->pluck('libelle', 'id'))
                                    ->searchable()
                                    ->visible(fn(Get $get) => static::estArticlePharmacie($get('article_id')))
                                    ->required(fn(Get $get) => static::estArticlePharmacie($get('article_id')))
                                    ->default(function (Get $get) {
                                        $article = static::getArticleCache($get('article_id'));
                                        return $article?->conditionnement_id;
                                    })
                                    ->helperText('Obligatoire pour les articles de catégorie Pharmacie')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('prix_unitaire_estime')
                                    ->label('P.U estimé')
                                    ->numeric()->suffix('FCFA')
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('justification')
                                    ->label('Justification')->rows(1)
                                    ->columnSpan(1),
                            ]),
                        ])
                        // ✅ Toutes les méthodes du Repeater regroupées ICI, une seule fois
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                            if (empty($data['conditionnement_id'])) {
                                $data['conditionnement_id'] = null;
                            }
                            return $data;
                        })
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter un article')
                        ->reorderable()
                        ->orderColumn('ordre')
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => Article::find($state['article_id'] ?? null)?->designation),
                ]),
        ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::syncLegacyServiceDemandeur($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::syncLegacyServiceDemandeur($data);
    }

    protected static function syncLegacyServiceDemandeur(array $data): array
    {
        if (!empty($data['service_demandeur_id'])) {
            $service = \App\Models\Service::find($data['service_demandeur_id']);
            $data['service_demandeur'] = $service?->nom;
        }
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N°')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('date_expression')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('serviceDemandeur.nom')
                    ->label('Service')->searchable()->limit(25),

                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Articles')->counts('lignes')->badge()->color('info'),

                Tables\Columns\TextColumn::make('bons_commande_count')
                    ->label('BC générés')
                    ->counts('bonsCommande')
                    ->badge()->color('success')->placeholder('—')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning'   => 'soumis',
                        'success'   => 'valide',
                        'primary'   => 'signe_dg',
                        'info'      => 'en_commande',
                        'success'   => 'satisfait',
                        'danger'    => fn($state) => in_array($state, ['rejete', 'annule']),
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'brouillon'   => 'Brouillon',
                        'soumis'      => 'Soumis',
                        'valide'      => 'Validé (comptable)',
                        'signe_dg'    => '✍️ Signé DG',
                        'en_commande' => 'En commande',
                        'satisfait'   => 'Satisfait',
                        'rejete'      => 'Rejeté',
                        'annule'      => 'Annulé',
                        default       => $state,
                    }),

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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn($record) => static::canEdit($record)),

                    Tables\Actions\Action::make('soumettre')
                        ->label('Soumettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn($record) => $record->statut === 'brouillon' && static::canSoumettre($record))
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update(['statut' => 'soumis']);
                            Notification::make()->title('✅ Expression soumise')->success()->send();
                        }),

                    // ✅ Validation comptable-matières + livraison automatique du disponible en stock
                    Tables\Actions\Action::make('valider')
                        ->label('Valider & Livrer le disponible')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn($record) => $record->statut === 'soumis' && static::canValider($record))
                        ->requiresConfirmation()
                        ->modalHeading('Valider l\'expression et livrer les quantités disponibles')
                        ->modalDescription(function ($record) {
                            $record->load('lignes.article');
                            $detail = $record->lignes->map(
                                fn($l) =>
                                "{$l->article?->designation} — Demandé: {$l->quantite_demandee} | "
                                    . "Dispo: {$l->quantite_en_stock} | À commander: {$l->quantite_a_commander}"
                            )->implode("\n");
                            return "Les quantités disponibles en stock seront immédiatement sorties du stock. Le reste sera marqué \"à commander\".\n\n{$detail}";
                        })
                        ->form([
                            Forms\Components\Select::make('ordonnateur_id')
                                ->label('Ordonnateur-matières')
                                ->relationship('ordonnateur', 'name')
                                ->searchable()->preload()->required(),
                        ])
                        ->action(function ($record, array $data) {
                            DB::transaction(function () use ($record, $data) {
                                $record->load('lignes.article.stock');

                                foreach ($record->lignes as $ligne) {
                                    $aLivrer = (int) $ligne->quantite_en_stock;
                                    if ($aLivrer > 0 && $ligne->article?->stock) {
                                        $ligne->article->stock->sortie($aLivrer);
                                    }
                                }

                                $record->update([
                                    'statut'          => 'valide',
                                    'ordonnateur_id'  => $data['ordonnateur_id'],
                                    'date_validation' => now()->toDateString(),
                                ]);
                            });

                            Notification::make()
                                ->title('✅ Expression validée — quantités disponibles livrées')
                                ->success()->send();
                        }),

                    // ✅ Signature DG — individuelle, par expression
                    Tables\Actions\Action::make('signer_dg')
                        ->label('Signer (DG)')
                        ->icon('heroicon-o-pencil')
                        ->color('primary')
                        ->visible(fn($record) => $record->statut === 'valide' && static::canSigner($record))
                        ->requiresConfirmation()
                        ->modalHeading('Signature du Directeur Général')
                        ->modalDescription(
                            fn($record) =>
                            "Confirmez la signature de l'expression {$record->numero} par le Directeur Général."
                        )
                        ->action(function ($record) {
                            $record->update([
                                'statut'            => 'signe_dg',
                                'signe_par_id'      => auth()->id(),
                                'date_signature_dg' => now(),
                            ]);
                            Notification::make()->title('✅ Expression signée par le DG')->success()->send();
                        }),

                    Tables\Actions\Action::make('generer_bon_commande')
                        ->label('Générer un Bon de Commande')
                        ->icon('heroicon-o-document-plus')
                        ->color('primary')
                        ->visible(
                            fn($record) =>
                            $record->statut === 'signe_dg'
                                && static::canGenererBC($record)
                                && $record->lignes()->whereNull('bon_commande_id')->where('quantite_a_commander', '>', 0)->exists()
                        )
                        ->form(function ($record) {
                            $lignesDisponibles = $record->lignes()
                                ->whereNull('bon_commande_id')
                                ->where('quantite_a_commander', '>', 0)
                                ->with('article.uniteMesure')
                                ->get();

                            return [
                                Forms\Components\Placeholder::make('info')
                                    ->label('')
                                    ->content('Sélectionnez le fournisseur, le budget, et les lignes à inclure dans ce bon de commande. Les lignes non sélectionnées resteront disponibles pour un autre BC (ex: fournisseur différent).')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('budget_id')
                                    ->label('Budget')
                                    ->options(fn() => \App\Models\Budget::where('actif', true)->pluck('libelle', 'id'))
                                    ->required()->searchable()->live(),

                                Forms\Components\Select::make('fournisseur_id')
                                    ->label('Fournisseur')
                                    ->options(fn() => \App\Models\Fournisseur::pluck('raison_sociale', 'id'))
                                    ->required()->searchable()->preload(),

                                Forms\Components\Select::make('nomenclature_commune_id')
                                    ->label('Nomenclature budgétaire')
                                    ->options(function (Get $get) {
                                        $budgetId = $get('budget_id');
                                        if (!$budgetId) return [];
                                        return \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                                            ->whereNotNull('nomenclature_id')->with('nomenclature')->get()
                                            ->filter(fn($lb) => $lb->nomenclature)
                                            ->mapWithKeys(fn($lb) => [
                                                $lb->nomenclature_id => "{$lb->nomenclature->code} - {$lb->nomenclature->libelle}"
                                            ]);
                                    })
                                    ->required()->searchable()
                                    ->disabled(fn(Get $get) => !$get('budget_id')),

                                Forms\Components\Textarea::make('objet')
                                    ->label('Objet')
                                    ->default($record->objet)
                                    ->required()->rows(2)->columnSpanFull(),

                                Forms\Components\CheckboxList::make('lignes_ids')
                                    ->label('Lignes à commander')
                                    ->options(
                                        $lignesDisponibles->mapWithKeys(fn($l) => [
                                            $l->id => "{$l->article?->designation} — "
                                                . "{$l->quantite_a_commander} "
                                                . ($l->article?->uniteMesure?->libelle ?? 'unité')
                                                . " — PU est.: " . number_format($l->prix_unitaire_estime, 0, ',', ' ') . " FCFA"
                                        ])
                                    )
                                    ->default($lignesDisponibles->pluck('id')->toArray())
                                    ->required()
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ];
                        })
                        ->modalHeading('Générer un Bon de Commande')
                        ->modalWidth('2xl')
                        ->action(function ($record, array $data) {
                            DB::transaction(function () use ($record, $data) {
                                $lignesEB = \App\Models\LigneExpressionBesoin::whereIn('id', $data['lignes_ids'])
                                    ->with('article.uniteMesure')->get();

                                $bc = \App\Models\BonCommande::create([
                                    'exercice_id'             => $record->exercice_id,
                                    'budget_id'               => $data['budget_id'],
                                    'fournisseur_id'          => $data['fournisseur_id'],
                                    'service_demandeur_id'    => $record->service_demandeur_id,
                                    'nomenclature_commune_id' => $data['nomenclature_commune_id'],
                                    'expression_besoin_id'    => $record->id,
                                    'date_emission'           => now(),
                                    'objet'                   => $data['objet'],
                                    'statut'                  => 'brouillon',
                                    'created_by'              => auth()->id(),
                                ]);

                                $mapUnites = [
                                    'boîte' => 'lot',
                                    'boite' => 'lot',
                                    'plaquette' => 'lot',
                                    'flacon' => 'pièce',
                                    'comprimé' => 'pièce',
                                    'kg' => 'kg',
                                    'litre' => 'litre',
                                    'l' => 'litre',
                                    'mètre' => 'mètre',
                                    'heure' => 'heure',
                                    'jour' => 'jour',
                                    'forfait' => 'forfait',
                                    'lot' => 'lot',
                                    'pièce' => 'pièce',
                                ];

                                foreach ($lignesEB as $ligneEB) {
                                    $uniteLibelle = strtolower(trim($ligneEB->article?->uniteMesure?->libelle ?? ''));
                                    $uniteMappee  = $mapUnites[$uniteLibelle] ?? 'pièce';

                                    \App\Models\LigneBonCommande::create([
                                        'bon_commande_id'  => $bc->id,
                                        'nomenclature_id'  => $data['nomenclature_commune_id'],
                                        'designation'      => $ligneEB->article?->designation ?? 'Article',
                                        'unite'            => $uniteMappee,
                                        'quantite'         => $ligneEB->quantite_a_commander,
                                        'prix_unitaire_ht' => $ligneEB->prix_unitaire_estime > 0
                                            ? $ligneEB->prix_unitaire_estime
                                            : ($ligneEB->article?->prix_unitaire_moyen ?? 0),
                                        'observations'     => "Généré depuis Expression de Besoin {$record->numero}",
                                    ]);

                                    $ligneEB->update(['bon_commande_id' => $bc->id]);
                                }

                                $bc->refresh()->load('lignes');
                                $bc->calculerMontants();
                                $bc->saveQuietly();

                                $record->update(['statut' => 'en_commande']);
                            });

                            Notification::make()
                                ->title('✅ Bon de commande généré')
                                ->success()
                                ->body('Le BC est en brouillon — complétez/validez-le depuis le module Budget.')
                                ->send();
                        }),

                    Tables\Actions\Action::make('apercu_pdf')
                        ->label('Aperçu (PDF)')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn($record) => $record->statut !== 'brouillon')
                        ->url(fn($record) => route('expressions-besoins.pdf.preview', $record->id))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('telecharger_pdf')
                        ->label('Télécharger (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn($record) => $record->statut !== 'brouillon')
                        ->url(fn($record) => route('expressions-besoins.pdf.download', $record->id))
                        ->openUrlInNewTab(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: \Filament\Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('consolider')
                        ->label('Consolider dans une fiche')
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('info')
                        ->visible(fn() => static::canConsolider())
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\Radio::make('mode')
                                ->label('Fiche de consolidation')
                                ->options([
                                    'nouvelle'  => '➕ Créer une nouvelle fiche',
                                    'existante' => '📂 Ajouter à une fiche ouverte existante',
                                ])
                                ->default('nouvelle')
                                ->live()
                                ->required(),

                            Forms\Components\Select::make('fiche_id')
                                ->label('Fiche existante')
                                ->options(fn() => \App\Models\FicheConsolidationBesoin::where('statut', 'ouverte')->pluck('numero', 'id'))
                                ->visible(fn(Get $get) => $get('mode') === 'existante')
                                ->required(fn(Get $get) => $get('mode') === 'existante'),

                            Forms\Components\Textarea::make('observations')
                                ->label('Observations')
                                ->visible(fn(Get $get) => $get('mode') === 'nouvelle')
                                ->rows(2),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data) {
                            $nonSoumis = $records->where('statut', '!=', 'soumis');
                            if ($nonSoumis->isNotEmpty()) {
                                Notification::make()
                                    ->title('❌ Seules les expressions "Soumises" peuvent être consolidées')
                                    ->danger()->persistent()->send();
                                return;
                            }

                            if ($data['mode'] === 'nouvelle') {
                                $fiche = \App\Models\FicheConsolidationBesoin::create([
                                    'comptable_matieres_id' => auth()->id(),
                                    'observations'          => $data['observations'] ?? null,
                                ]);
                            } else {
                                $fiche = \App\Models\FicheConsolidationBesoin::findOrFail($data['fiche_id']);
                            }

                            $records->each(fn($r) => $r->update(['fiche_consolidation_id' => $fiche->id]));

                            Notification::make()
                                ->title("✅ {$records->count()} expression(s) consolidée(s) dans {$fiche->numero}")
                                ->success()->send();
                        }),
                ]),
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

    /**
     * ✅ Cache léger pour éviter de requêter l'Article en base
     * à chaque évaluation de visible()/default() dans le Repeater
     */
    protected static function getArticleCache(?int $articleId): ?\App\Models\Article
    {
        if (!$articleId) return null;

        return \Cache::remember(
            "article_pharma_check_{$articleId}",
            now()->addMinutes(5),
            fn() => Article::find($articleId)
        );
    }

    protected static function estArticlePharmacie(?int $articleId): bool
    {
        return (bool) static::getArticleCache($articleId)?->est_pharmacie;
    }
}
