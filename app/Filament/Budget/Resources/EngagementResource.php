<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\EngagementResource\Pages;
use App\Models\Engagement;
use App\Models\Budget;
use App\Models\Fournisseur;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use Illuminate\Database\Eloquent\Model;

class EngagementResource extends Resource
{
    protected static ?string $model            = Engagement::class;
    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Engagements';
    protected static ?string $modelLabel       = 'Engagement';
    protected static ?string $pluralModelLabel = 'Engagements';
    protected static ?string $navigationGroup  = 'Commandes & Engagement';
    protected static ?int    $navigationSort   = 1;
    protected static ?string $recordTitleAttribute = 'numero';
    protected static int     $globalSearchResultsLimit = 20;

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'objet', 'montant_engage'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Objet'          => $record->objet,
            'Montant engagé' => $record->montant_engage,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\Transmission::query()
            ->where('document_type', 'App\Models\Engagement')
            ->pourDestinataire(auth()->id())
            ->enAttente()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ── Permissions ───────────────────────────────────────────
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_engagement');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_engagement');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('create_engagement');
    }

    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('update_engagement')) return false;

        // ✅ Seuls les engagements manuels provisoires sont éditables
        if (!$record->estModifiable()) {
            if ($record->estLectureSeule()) {
                Notification::make()
                    ->title('Modification impossible')->warning()
                    ->body("L'exercice {$record->exercice->annee} est {$record->exercice->statut}.")
                    ->send();
            }
            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->check()) return false;
        if (!auth()->user()->can('delete_engagement')) return false;
        return $record->estModifiable();
    }

    public static function canValider($record): bool
    {
        return auth()->check() && auth()->user()->can('valider_engagement');
    }

    public static function canAnnuler($record): bool
    {
        return auth()->check() && auth()->user()->can('annuler_engagement');
    }

    // ── Form (utilisé uniquement pour EditEngagement) ─────────
    // La création est gérée par CreateEngagement.php (wizard BC/DA)
    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Info source — lecture seule ───────────────────
            Forms\Components\Section::make('Document source')
                ->schema([
                    Forms\Components\Placeholder::make('source_info')
                        ->label('Document lié')
                        ->content(function ($record) {
                            if (!$record) return '—';

                            if ($record->estBonCommande() && $record->engageable) {
                                $bc = $record->engageable;
                                return new \Illuminate\Support\HtmlString(
                                    "<span style='background:#dbeafe;color:#1d4ed8;padding:.2rem .6rem;border-radius:9999px;font-size:.8rem;font-weight:700;'>BC</span> " .
                                        "<strong>{$bc->numero}</strong> — {$bc->fournisseur?->raison_sociale}"
                                );
                            }

                            if ($record->estDecision() && $record->engageable) {
                                $da = $record->engageable;
                                $beneficiaire = $da->personnel?->nom_complet
                                    ?? $da->fournisseur?->raison_sociale
                                    ?? '—';
                                return new \Illuminate\Support\HtmlString(
                                    "<span style='background:#fef9c3;color:#a16207;padding:.2rem .6rem;border-radius:9999px;font-size:.8rem;font-weight:700;'>DA</span> " .
                                        "<strong>{$da->numero}</strong> — {$beneficiaire}"
                                );
                            }

                            return new \Illuminate\Support\HtmlString(
                                "<span style='background:#f1f5f9;color:#475569;padding:.2rem .6rem;border-radius:9999px;font-size:.8rem;font-weight:700;'>Manuel</span>"
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn($record) => $record !== null)
                ->collapsible()->collapsed(false),

            // ── Exercice ──────────────────────────────────────
            Forms\Components\Section::make('Exercice')
                ->schema([ExerciceSelect::make()])
                ->collapsible()->collapsed(fn($record) => $record !== null),

            // ── Informations principales ──────────────────────
            Forms\Components\Section::make('Informations principales')
                ->schema([
                    Forms\Components\Select::make('budget_id')
                        ->label('Budget')
                        ->options(Budget::where('actif', true)->whereNotNull('libelle')->pluck('libelle', 'id'))
                        ->required()->searchable()->preload()->live()
                        ->afterStateUpdated(fn(callable $set) => $set('nomenclature_principale_id', null))
                        ->disabled(fn($record) => $record?->engageable_id !== null),

                    Forms\Components\TextInput::make('type_engagement')
                        ->label('Type d\'engagement')
                        ->disabled(fn($record) => $record?->engageable_id !== null)
                        ->dehydrated(),

                    Forms\Components\DatePicker::make('date_engagement')
                        ->label('Date d\'engagement')->required()->default(now()),
                ])
                ->columns(3),

            // ── Nomenclature et montant ───────────────────────
            Forms\Components\Section::make('Nomenclature et montant')
                ->schema([
                    Forms\Components\Select::make('nomenclature_principale_id')
                        ->label('Nomenclature budgétaire principale')
                        ->options(function (callable $get) {
                            $budgetId = $get('budget_id');
                            if (!$budgetId) return [];
                            return \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                                ->with('nomenclature')->get()
                                ->filter(fn($lb) => $lb->nomenclature)
                                ->mapWithKeys(fn($lb) => [
                                    $lb->nomenclature_id =>
                                    "{$lb->nomenclature->code} - {$lb->nomenclature->libelle} " .
                                        "(Dispo: " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA)"
                                ])->toArray();
                        })
                        ->required()->searchable()->preload()->live()
                        ->helperText(function (callable $get) {
                            $nomenclatureId = $get('nomenclature_principale_id');
                            $budgetId       = $get('budget_id');
                            $montant        = $get('montant_engage');
                            if (!$nomenclatureId || !$budgetId || !$montant) return '';
                            $lb = \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                                ->where('nomenclature_id', $nomenclatureId)->first();
                            if (!$lb) return '';
                            return $montant > $lb->disponible_engagement
                                ? '⚠️ Crédit insuffisant ! Disponible: ' . number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA'
                                : '✅ Disponible: ' . number_format($lb->disponible_engagement, 0, ',', ' ') . ' FCFA';
                        })
                        ->disabled(fn(callable $get) => !$get('budget_id')),

                    Forms\Components\TextInput::make('montant_engage')
                        ->label('Montant à engager')->required()->numeric()->prefix('FCFA')
                        ->live(onBlur: true)
                        // ✅ Readonly si lié à un BC/DA — montant imposé par le document
                        ->readOnly(fn($record) => $record?->engageable_id !== null),
                ])
                ->columns(2),

            // ── Bénéficiaire ──────────────────────────────────
            Forms\Components\Section::make('Bénéficiaire')
                ->schema([
                    Forms\Components\Radio::make('type_beneficiaire')
                        ->label('Type de bénéficiaire')
                        ->options(['fournisseur' => 'Fournisseur', 'personnel' => 'Personnel (Agent)'])
                        ->required()->live()->default('fournisseur')->inline()
                        ->disabled(fn($record) => $record?->engageable_id !== null),

                    Forms\Components\Select::make('beneficiaire_fournisseur_id')
                        ->label('Fournisseur')
                        ->options(Fournisseur::whereNotNull('raison_sociale')->pluck('raison_sociale', 'id'))
                        ->searchable()->preload()
                        ->required(fn(callable $get) => $get('type_beneficiaire') === 'fournisseur')
                        ->visible(fn(callable $get) => $get('type_beneficiaire') === 'fournisseur')
                        ->disabled(fn($record) => $record?->engageable_id !== null),

                    Forms\Components\Select::make('beneficiaire_personnel_id')
                        ->label('Personnel')
                        ->options(User::whereNotNull('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->required(fn(callable $get) => $get('type_beneficiaire') === 'personnel')
                        ->visible(fn(callable $get) => $get('type_beneficiaire') === 'personnel')
                        ->disabled(fn($record) => $record?->engageable_id !== null),
                ])
                ->columns(2),

            // ── Objet et référence ────────────────────────────
            Forms\Components\Section::make('Objet et référence')
                ->schema([
                    Forms\Components\Textarea::make('objet')
                        ->label('Objet de l\'engagement')->required()->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('reference_document')
                        ->label('Référence du document')->maxLength(255)
                        ->disabled(fn($record) => $record?->engageable_id !== null),
                ]),

            // ── Observations ──────────────────────────────────
            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->collapsible()->collapsed(),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('exercice')
            ->with('exercice');
    }
    // ── Table ─────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° Engagement')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('engageable_type')
                    ->label('Source')->sortable()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'App\Models\BonCommande'            => 'BC',
                        'App\Models\DecisionAdministrative' => 'DA',
                        null                                => 'Manuel',
                        default                             => 'Autre',
                    })
                    ->badge()->color(fn($state) => match ($state) {
                        'App\Models\BonCommande'            => 'info',
                        'App\Models\DecisionAdministrative' => 'warning',
                        default                             => 'gray',
                    }),

                Tables\Columns\TextColumn::make('document_source')
                    ->label('N° Document')
                    ->getStateUsing(
                        fn($record) =>
                        $record->reference_document ?? $record->engageable?->numero ?? null
                    )
                    ->searchable(['reference_document'])
                    ->copyable()->placeholder('Manuel')
                    ->badge()->color('gray'),

                Tables\Columns\TextColumn::make('nomenclaturePrincipale.code')
                    ->label('Nomenclature')->searchable()->badge()->color('warning'),

                Tables\Columns\TextColumn::make('beneficiaire')
                    ->label('Bénéficiaire')
                    ->getStateUsing(fn($record) => $record->getNomBeneficiaire() ?? 'Non défini')
                    ->searchable(query: function ($query, $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHasMorph('beneficiaire', [\App\Models\Fournisseur::class], fn($sq) =>
                            $sq->where('raison_sociale', 'like', "%{$search}%"))
                                ->orWhereHasMorph('beneficiaire', [\App\Models\Personnel::class], fn($sq) =>
                                $sq->where('nom', 'like', "%{$search}%")
                                    ->orWhere('prenoms', 'like', "%{$search}%"));
                        });
                    })
                    ->limit(30),

                Tables\Columns\TextColumn::make('date_engagement')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('montant_engage')
                    ->label('Montant')->money('XAF')->sortable()->weight('bold')->color('success'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'provisoire',
                        'success'   => 'definitif',
                        'danger'    => 'annule',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'provisoire' => 'Provisoire',
                        'definitif'  => 'Définitif',
                        'annule'     => 'Annulé',
                        default      => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('engageable_type')
                    ->label('Source')
                    ->options([
                        'all'                               => 'Tout',
                        'App\Models\BonCommande'            => 'Bon de Commande',
                        'App\Models\DecisionAdministrative' => 'Décision Administrative',
                        'manuel'                            => 'Engagement Manuel',
                    ])
                    ->default('all')
                    ->query(function ($query, $state) {
                        if (($state['value'] ?? 'all') === 'all') return $query;
                        if ($state['value'] === 'manuel') return $query->whereNull('engageable_type');
                        return $query->where('engageable_type', $state['value']);
                    }),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(['provisoire' => 'Provisoire', 'definitif' => 'Définitif', 'annule' => 'Annulé']),

                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')->relationship('exercice', 'annee')
                    ->searchable()->preload()->placeholder('Tous les exercices')
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')->relationship('budget', 'libelle')
                    ->searchable()->preload(),

                Tables\Filters\Filter::make('date_engagement')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['du'], fn($q, $v) => $q->whereDate('date_engagement', '>=', $v))
                            ->when($data['au'], fn($q, $v) => $q->whereDate('date_engagement', '<=', $v))
                    ),
            ])
            ->actions([

                // ── Passer définitif ──────────────────────────
                Tables\Actions\Action::make('valider')
                    ->label('Passer définitif')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'provisoire'
                            && auth()->user()?->can('valider_engagement')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirmer le passage en définitif')
                    ->modalDescription(
                        fn($record) =>
                        "L'engagement {$record->numero} sera définitif et pourra recevoir des ordonnances."
                    )
                    ->action(function ($record) {
                        try {
                            $record->passerDefinitif(auth()->user());
                            Notification::make()->title('✅ Engagement définitif')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                        }
                    }),

                // ── Créer ordonnances ─────────────────────────
                Tables\Actions\Action::make('creer_ordonnances')
                    ->label('Créer OP')
                    ->icon('heroicon-o-document-currency-dollar')->color('success')
                    ->visible(
                        fn($record) =>
                        $record->statut === 'definitif'
                            && !$record->hasOrdonnancesPaiement()
                            && auth()->user()?->can('creer_ordonnance_paiement')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Créer les ordonnances de paiement')
                    ->modalContent(function ($record) {
                        $montantTotal = $record->montant_engage;
                        $montantIR    = 0;
                        if ($record->estBonCommande() && $record->engageable)
                            $montantIR = $record->engageable->montant_ir ?? 0;
                        elseif ($record->estDecision() && $record->engageable)
                            $montantIR = $record->engageable->montant_ir ?? 0;

                        return view('filament.modals.recap-ordonnances', [
                            'engagement'    => $record,
                            'montant_total' => $montantTotal,
                            'montant_ir'    => $montantIR,
                            'montant_net'   => $montantTotal - $montantIR,
                        ]);
                    })
                    ->action(function ($record) {
                        try {
                            $ordonnances = $record->creerOrdonnancesPaiement();
                            $msg = '';
                            if (isset($ordonnances['standard'])) $msg .= "• OP Standard : {$ordonnances['standard']->numero}\n";
                            if (isset($ordonnances['impot']))    $msg .= "• OP Impôt : {$ordonnances['impot']->numero}";
                            Notification::make()->title('✅ Ordonnances créées')->success()->body($msg)->duration(8000)->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->persistent()->send();
                        }
                    }),

                // ── Voir ordonnances ──────────────────────────
                Tables\Actions\Action::make('voir_ordonnances')
                    ->label('Voir OP')
                    ->icon('heroicon-o-eye')->color('info')
                    ->visible(fn($record) => $record->ordonnancesPaiement()->exists())
                    ->badge(fn($record) => $record->ordonnancesPaiement()->count())->badgeColor('success')
                    ->modalHeading(fn($record) => "Ordonnances — {$record->numero}")
                    ->modalContent(fn($record) => view('filament.modals.ordonnances-list', [
                        'ordonnances' => $record->ordonnancesPaiement()->with('beneficiaire')->get(),
                        'engagement'  => $record,
                    ]))
                    ->modalWidth('5xl')->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),

                // ── Annuler ───────────────────────────────────
                Tables\Actions\Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(
                        fn($record) =>
                        in_array($record->statut, ['provisoire', 'definitif'])
                            && $record->peutEtreAnnule()
                            && auth()->user()?->can('annuler_engagement')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Annuler l\'engagement')
                    ->modalDescription(fn($record) => new \Illuminate\Support\HtmlString(
                        "<div style='color:#dc2626;font-weight:600;'>
                        L'engagement <strong>{$record->numero}</strong> sera supprimé définitivement.<br>
                        Les crédits seront libérés sur la ligne budgétaire.
                        </div>"
                    ))
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif')->required()->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->ordonnancesPaiement()->exists()) {
                            Notification::make()->title('❌ Impossible — des OP existent')
                                ->danger()->persistent()->send();
                            return;
                        }
                        try {
                            $record->annuler(force: true);
                            Notification::make()->title('✅ Engagement annulé et crédits libérés')
                                ->warning()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('❌ Erreur')->danger()->body($e->getMessage())->send();
                        }
                    }),

                // ── Standard ──────────────────────────────────
                Tables\Actions\ViewAction::make()->label('Voir'),

                Tables\Actions\EditAction::make()
                    ->label('Modifier')
                    // ✅ Editable seulement si engagement manuel provisoire
                    ->visible(fn($record) => $record->statut === 'provisoire' && !$record->engageable_id),

                // ── PDF ───────────────────────────────────────
                Tables\Actions\ActionGroup::make([

                    // ── Certificat ────────────────────────────
                    Tables\Actions\Action::make('telecharger_certificat')
                        ->label('Certificat (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')->color('success')
                        ->visible(fn($record) => $record->statut === 'definitif')
                        ->form([
                            \Filament\Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => \App\Models\EtatConfig::variantesPour('certificat_engagement'))
                                ->default(fn() => \App\Models\EtatConfig::defautPour('certificat_engagement')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            $url = route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    Tables\Actions\Action::make('afficher_certificat')
                        ->label('Certificat (Aperçu)')
                        ->icon('heroicon-o-eye')->color('info')
                        ->visible(fn($record) => $record->statut === 'definitif')
                        ->form([
                            \Filament\Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => \App\Models\EtatConfig::variantesPour('certificat_engagement'))
                                ->default(fn() => \App\Models\EtatConfig::defautPour('certificat_engagement')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            $url = route('pdf.afficher', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    // ── Autorisation ──────────────────────────
                    Tables\Actions\Action::make('telecharger_autorisation')
                        ->label('Autorisation (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')->color('primary')
                        ->visible(fn($record) => $record->statut === 'definitif')
                        ->form([
                            \Filament\Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => \App\Models\EtatConfig::variantesPour('autorisation_engagement'))
                                ->default(fn() => \App\Models\EtatConfig::defautPour('autorisation_engagement')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            $url = route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    Tables\Actions\Action::make('afficher_autorisation')
                        ->label('Autorisation (Aperçu)')
                        ->icon('heroicon-o-eye')->color('gray')
                        ->visible(fn($record) => $record->statut === 'definitif')
                        ->form([
                            \Filament\Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => \App\Models\EtatConfig::variantesPour('autorisation_engagement'))
                                ->default(fn() => \App\Models\EtatConfig::defautPour('autorisation_engagement')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            $url = route('pdf.afficher', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    // ── Fiche de Performance ──────────────────────
                    Tables\Actions\Action::make('telecharger_fiche')
                        ->label('Fiche Perf. (PDF)')
                        ->icon('heroicon-o-arrow-down-tray')->color('warning')
                        ->visible(fn($record) => $record->statut === 'definitif')
                        ->form([
                            \Filament\Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => \App\Models\EtatConfig::variantesPour('fiche_performance'))
                                ->default(fn() => \App\Models\EtatConfig::defautPour('fiche_performance')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            $url = route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    Tables\Actions\Action::make('afficher_fiche')
                        ->label('Fiche Perf. (Aperçu)')
                        ->icon('heroicon-o-eye')->color('secondary')
                        ->visible(fn($record) => $record->statut === 'definitif')
                        ->form([
                            \Filament\Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => \App\Models\EtatConfig::variantesPour('fiche_performance'))
                                ->default(fn() => \App\Models\EtatConfig::defautPour('fiche_performance')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            $url = route('pdf.afficher', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                ])
                    ->label('PDF')->icon('heroicon-m-document-arrow-down')
                    ->size('sm')->color('success')->button()
                    ->visible(fn($record) => $record->statut === 'definitif'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEngagements::route('/'),
            'create' => Pages\CreateEngagement::route('/create'),
            'edit'   => Pages\EditEngagement::route('/{record}/edit'),
            'view'   => Pages\ViewEngagement::route('/{record}'),
        ];
    }
}
