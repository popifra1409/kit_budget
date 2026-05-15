<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\OrdonnancePaiementResource\Pages;
use App\Models\OrdonnancePaiement;
use App\Models\Engagement;
use App\Models\Fournisseur;
use App\Exports\OrdonnancesPaiementExport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\EtatConfig;

class OrdonnancePaiementResource extends Resource
{
    protected static ?string $model = OrdonnancePaiement::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Ordonnances de Paiement';
    protected static ?string $modelLabel = 'Ordonnance de Paiement';
    protected static ?string $pluralModelLabel = 'Ordonnances de Paiement';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int $navigationSort = 5;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_ordonnance_paiement') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_ordonnance_paiement') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_ordonnance_paiement') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user?->can('update_ordonnance_paiement')) {
            return false;
        }

        // Règle métier : Seules les OP en brouillon sont modifiables
        if ($record->statut !== 'brouillon') {
            return false;
        }

        // Override pour super admin / DAAF
        if ($user->can('override_ordonnance_paiement')) {
            return true;
        }

        // Sinon, seulement ses propres OP
        return $record->created_by === $user->id;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        if (!$user?->can('delete_ordonnance_paiement')) {
            return false;
        }

        // Seulement les brouillons
        if ($record->statut !== 'brouillon') {
            return false;
        }

        // Override ou créateur
        return $user->can('override_ordonnance_paiement') || $record->created_by === $user->id;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type_ordonnance', ['standard', 'impot']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Création automatique d\'ordonnances')
                    ->description('⚡ Les ordonnances de paiement seront créées automatiquement à partir de l\'engagement.')
                    ->schema([
                        Forms\Components\Select::make('engagement_id')
                            ->label('Engagement')
                            ->options(
                                Engagement::with('engageable', 'beneficiaire')
                                    ->where('statut', 'definitif')
                                    ->whereDoesntHave('ordonnancesPaiement')
                                    ->orderBy('date_engagement', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn($eng) => [
                                        $eng->id => sprintf(
                                            '%s - %s (%s FCFA)',
                                            $eng->numero,
                                            \Str::limit($eng->objet, 50),
                                            number_format($eng->montant_engage, 0, ',', ' ')
                                        )
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('💡 Seuls les engagements définitifs sans ordonnances sont listés')
                            ->columnSpanFull(),

                        // ✅ Aperçu des montants
                        Forms\Components\Placeholder::make('apercu')
                            ->label('📊 Aperçu')
                            ->content(fn(Forms\Get $get) => static::getApercu($get('engagement_id')))
                            ->columnSpanFull()
                            ->visible(fn(Forms\Get $get) => $get('engagement_id')),
                    ]),

                Forms\Components\Section::make('ℹ️ Information')
                    ->schema([
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content(
                                "**Ce qui sera créé automatiquement :**\n\n" .
                                    "1️⃣ **OP Standard** : Pour payer le bénéficiaire (fournisseur ou personnel)\n" .
                                    "2️⃣ **OP Impôt** : Pour reverser les taxes au Trésor Public (si applicable)\n\n" .
                                    "✅ Tous les montants et bénéficiaires sont calculés automatiquement."
                            )
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    /**
     * ✅ Aperçu des montants avant création
     */
    protected static function getApercu(?int $engagementId): string
    {
        if (!$engagementId)
            return '';

        try {
            $engagement = Engagement::with('engageable', 'beneficiaire')->find($engagementId);
            if (!$engagement)
                return '';

            $donnees = $engagement->extraireDonneesDocument();

            // Calculer les retenues
            if ($engagement->estBonCommande()) {
                $retenues = ($donnees['montant_ir'] ?? 0) +
                    ($donnees['montant_tva'] ?? 0) +
                    ($donnees['montant_tsr'] ?? 0);
            } else {
                $retenues = ($donnees['montant_ir'] ?? 0) +
                    ($donnees['montant_cnps'] ?? 0) +
                    ($donnees['montant_irnc'] ?? 0) +
                    ($donnees['autres_retenues'] ?? 0);
            }

            $beneficiaire = $donnees['beneficiaire'];
            $nomBenef = $beneficiaire->raison_sociale ??
                $beneficiaire->nom_complet ??
                $beneficiaire->name ??
                'N/A';

            $html = "**💰 Montants :**\n\n";
            $html .= "• Montant TTC : **" . number_format($donnees['montant_ttc'], 0, ',', ' ') . " FCFA**\n";
            $html .= "• Montant Net (bénéficiaire) : **" . number_format($donnees['montant_net'], 0, ',', ' ') . " FCFA**\n";

            if ($retenues > 0) {
                $html .= "• Retenues/Impôts (Trésor) : **" . number_format($retenues, 0, ',', ' ') . " FCFA**\n\n";
                $html .= "**🎯 Résultat :**\n\n";
                $html .= "→ **2 ordonnances** seront créées :\n";
                $html .= "   • OP Standard pour **{$nomBenef}**\n";
                $html .= "   • OP Impôt pour **Trésor Public**";
            } else {
                $html .= "\n**🎯 Résultat :**\n\n";
                $html .= "→ **1 ordonnance** sera créée :\n";
                $html .= "   • OP Standard pour **{$nomBenef}**\n";
                $html .= "   • Aucune retenue à reverser";
            }

            return $html;
        } catch (\Exception $e) {
            return "⚠️ Erreur : " . $e->getMessage();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° OP')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('type_ordonnance')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'standard' => 'Standard',
                        'impot' => 'Impôt',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'standard',
                        'warning' => 'impot',
                    ]),

                Tables\Columns\TextColumn::make('engagement.numero')
                    ->label('Engagement')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($record) => $record->statut_label)
                    ->color(fn($record) => $record->statut_color),

                // ✅ Colonnes de montants correctement placées dans la table
                Tables\Columns\TextColumn::make('montant_brut')
                    ->label('Montant brut')
                    ->money('XAF')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_impot')
                    ->label('À précompter')
                    ->money('XAF')
                    ->sortable()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant Net')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date émission')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_paiement')
                    ->label('Date paiement')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type_ordonnance')
                    ->label('Type')
                    ->options([
                        'standard' => 'Standard',
                        'impot' => 'Impôt',
                    ]),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'emise' => 'Émise',
                        'visee' => 'Visée',
                        'payee' => 'Payée',
                        'annulee' => 'Annulée',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('date_emission')
                    ->form([
                        Forms\Components\DatePicker::make('date_emission_from')
                            ->label('Date d\'émission du'),
                        Forms\Components\DatePicker::make('date_emission_until')
                            ->label('Date d\'émission au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['date_emission_from'],
                                fn($q, $date) => $q->whereDate('date_emission', '>=', $date)
                            )
                            ->when(
                                $data['date_emission_until'],
                                fn($q, $date) => $q->whereDate('date_emission', '<=', $date)
                            );
                    }),

                // FILTRE PAR PÉRIODE PRÉDÉFINIE
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période prédéfinie')
                            ->options([
                                'today' => 'Aujourd\'hui',
                                'yesterday' => 'Hier',
                                'this_week' => 'Cette semaine',
                                'last_week' => 'Semaine dernière',
                                'this_month' => 'Ce mois',
                                'last_month' => 'Mois dernier',
                                'this_quarter' => 'Ce trimestre',
                                'last_quarter' => 'Trimestre dernier',
                                'this_year' => 'Cette année',
                                'last_year' => 'Année dernière',
                            ])
                            ->default('today')
                            ->placeholder('Sélectionner une période'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? 'today';

                        return match ($periode) {
                            'today' => $query->whereDate('date_emission', today()),
                            'yesterday' => $query->whereDate('date_emission', today()->subDay()),
                            'this_week' => $query->whereBetween('date_emission', [
                                now()->startOfWeek(),
                                now()->endOfWeek()
                            ]),
                            'last_week' => $query->whereBetween('date_emission', [
                                now()->subWeek()->startOfWeek(),
                                now()->subWeek()->endOfWeek()
                            ]),
                            'this_month' => $query->whereMonth('date_emission', now()->month)
                                ->whereYear('date_emission', now()->year),
                            'last_month' => $query->whereMonth('date_emission', now()->subMonth()->month)
                                ->whereYear('date_emission', now()->subMonth()->year),
                            'this_quarter' => $query->whereBetween('date_emission', [
                                now()->startOfQuarter(),
                                now()->endOfQuarter()
                            ]),
                            'last_quarter' => $query->whereBetween('date_emission', [
                                now()->subQuarter()->startOfQuarter(),
                                now()->subQuarter()->endOfQuarter()
                            ]),
                            'this_year' => $query->whereYear('date_emission', now()->year),
                            'last_year' => $query->whereYear('date_emission', now()->subYear()->year),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) {
                            return 'Période : Aujourd\'hui';
                        }

                        $labels = [
                            'today' => 'Aujourd\'hui',
                            'yesterday' => 'Hier',
                            'this_week' => 'Cette semaine',
                            'last_week' => 'Semaine dernière',
                            'this_month' => 'Ce mois',
                            'last_month' => 'Mois dernier',
                            'this_quarter' => 'Ce trimestre',
                            'last_quarter' => 'Trimestre dernier',
                            'this_year' => 'Cette année',
                            'last_year' => 'Année dernière',
                        ];

                        return 'Période : ' . ($labels[$data['periode']] ?? $data['periode']);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('emettre')
                    ->label('Émettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn($record) => $record->statut === 'brouillon')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->emettre();
                        Notification::make()
                            ->title('Ordonnance émise')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('marquer_payee')
                    ->label('Marquer payée')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => in_array($record->statut, ['emise', 'visee']))
                    ->form([
                        Forms\Components\DatePicker::make('date_paiement')
                            ->label('Date de paiement')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('reference_paiement')
                            ->label('Référence de paiement')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->marquerPayee($data['reference_paiement']);
                        $record->date_paiement = $data['date_paiement'];
                        $record->save();
                        Notification::make()
                            ->title('Paiement enregistré')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([

                    // ── OP Standard ──────────────────────────────────────
                    Tables\Actions\Action::make('telecharger_op')
                        ->label('Télécharger OP')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn($record) => $record->type_ordonnance === 'standard')
                        ->form([
                            Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => EtatConfig::variantesPour('ordonnance_paiement'))
                                // ✅ Défaut dynamique depuis la base
                                ->default(fn() => EtatConfig::defautPour('ordonnance_paiement')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function ($record, array $data, $livewire) {
                            $url = route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    Tables\Actions\Action::make('afficher_op')
                        ->label('Aperçu OP')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn($record) => $record->type_ordonnance === 'standard')
                        ->form([
                            Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => EtatConfig::variantesPour('ordonnance_paiement'))
                                ->default(fn() => EtatConfig::defautPour('ordonnance_paiement')?->code)
                                ->required()
                                ->helperText('⭐ = modèle par défaut'),
                        ])
                        ->action(function ($record, array $data, $livewire) {
                            $url = route('pdf.afficher', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    // ── OPT Impôt ────────────────────────────────────────
                    Tables\Actions\Action::make('telecharger_op_impot')
                        ->label('Télécharger OP Impôt')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->visible(fn($record) => $record->type_ordonnance === 'impot')
                        ->form([
                            Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => EtatConfig::variantesPour('ordonnance_paiement_impot'))
                                ->default(fn() => EtatConfig::defautPour('ordonnance_paiement_impot')?->code)
                                ->required(),
                        ])
                        ->action(function ($record, array $data, $livewire) {
                            $url = route('pdf.telecharger', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),

                    Tables\Actions\Action::make('afficher_op_impot')
                        ->label('Aperçu OP Impôt')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn($record) => $record->type_ordonnance === 'impot')
                        ->form([
                            Forms\Components\Select::make('variante')
                                ->label('Modèle d\'état')
                                ->options(fn() => EtatConfig::variantesPour('ordonnance_paiement_impot'))
                                ->default(fn() => EtatConfig::defautPour('ordonnance_paiement_impot')?->code)
                                ->required(),
                        ])
                        ->action(function ($record, array $data, $livewire) {
                            $url = route('pdf.afficher', ['etat' => $data['variante'], 'id' => $record->id]);
                            $livewire->dispatch('open-url-new-tab', url: $url);
                        }),
                ])
                    ->label('Télécharger / Aperçu')
                    ->icon('heroicon-m-document-arrow-down')
                    ->size('sm')
                    ->button(),
            ])
            ->headerActions([
                // ========================================
                // 📊 ACTIONS D'EXPORT
                // ========================================

                Tables\Actions\ActionGroup::make([

                    // ── OP Standard Salaires ─────────────────────────────
                    Tables\Actions\Action::make('export_excel_salaires_standard')
                        ->label('Excel OP Standard - Salaires')
                        ->icon('heroicon-o-table-cells')
                        ->color('success')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            $nomenclatureIds = $data['nomenclature_ids'] ?? [];
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);

                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\OrdonnancesSalairesExport(
                                    'standard',
                                    $nomenclatureIds,
                                    $dateDebut,
                                    $dateFin
                                ),
                                'OP_Standard_Salaires_' . now()->format('Y-m-d') . '.xlsx'
                            );
                        }),

                    Tables\Actions\Action::make('export_pdf_salaires_standard')
                        ->label('PDF OP Standard - Salaires')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            $nomenclatureIds = $data['nomenclature_ids'] ?? [];
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);

                            return static::exportPdfSalaires(
                                'standard',
                                $nomenclatureIds,
                                $dateDebut,
                                $dateFin
                            );
                        }),

                    // ── OPT Impôt Salaires ───────────────────────────────
                    Tables\Actions\Action::make('export_excel_salaires_impot')
                        ->label('Excel OPT Impôt - Salaires')
                        ->icon('heroicon-o-table-cells')
                        ->color('warning')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            $nomenclatureIds = $data['nomenclature_ids'] ?? [];
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);

                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\OrdonnancesSalairesExport(
                                    'impot',
                                    $nomenclatureIds,
                                    $dateDebut,
                                    $dateFin
                                ),
                                'OPT_Impot_Salaires_' . now()->format('Y-m-d') . '.xlsx'
                            );
                        }),

                    Tables\Actions\Action::make('export_pdf_salaires_impot')
                        ->label('PDF OPT Impôt - Salaires')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            $nomenclatureIds = $data['nomenclature_ids'] ?? [];
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);

                            return static::exportPdfSalaires(
                                'impot',
                                $nomenclatureIds,
                                $dateDebut,
                                $dateFin
                            );
                        }),
                ])
                    ->label('💼 Rapports Salaires')
                    ->icon('heroicon-o-banknotes')
                    ->button()
                    ->color('info'),

                Tables\Actions\ActionGroup::make([
                    // Export Excel OP Standard
                    Tables\Actions\Action::make('export_excel_standard')
                        ->label('Export Excel OP Standard')
                        ->icon('heroicon-o-table-cells')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('mois')
                                ->label('Mois')
                                ->options([
                                    '01' => 'Janvier',
                                    '02' => 'Février',
                                    '03' => 'Mars',
                                    '04' => 'Avril',
                                    '05' => 'Mai',
                                    '06' => 'Juin',
                                    '07' => 'Juillet',
                                    '08' => 'Août',
                                    '09' => 'Septembre',
                                    '10' => 'Octobre',
                                    '11' => 'Novembre',
                                    '12' => 'Décembre',
                                ])
                                ->required()
                                ->default(date('m')),
                            Forms\Components\Select::make('annee')
                                ->label('Année')
                                ->options(function () {
                                    $years = [];
                                    for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
                                        $years[$i] = $i;
                                    }
                                    return $years;
                                })
                                ->required()
                                ->default(date('Y')),
                        ])
                        ->action(function (array $data) {
                            return Excel::download(
                                new OrdonnancesPaiementExport('standard', null, null, $data['mois'], $data['annee']),
                                'OP_Standard_' . $data['mois'] . '_' . $data['annee'] . '.xlsx'
                            );
                        }),

                    // Export Excel OP Impôt
                    Tables\Actions\Action::make('export_excel_impot')
                        ->label('Export Excel OP Impôt')
                        ->icon('heroicon-o-table-cells')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('mois')
                                ->label('Mois')
                                ->options([
                                    '01' => 'Janvier',
                                    '02' => 'Février',
                                    '03' => 'Mars',
                                    '04' => 'Avril',
                                    '05' => 'Mai',
                                    '06' => 'Juin',
                                    '07' => 'Juillet',
                                    '08' => 'Août',
                                    '09' => 'Septembre',
                                    '10' => 'Octobre',
                                    '11' => 'Novembre',
                                    '12' => 'Décembre',
                                ])
                                ->required()
                                ->default(date('m')),
                            Forms\Components\Select::make('annee')
                                ->label('Année')
                                ->options(function () {
                                    $years = [];
                                    for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
                                        $years[$i] = $i;
                                    }
                                    return $years;
                                })
                                ->required()
                                ->default(date('Y')),
                        ])
                        ->action(function (array $data) {
                            return Excel::download(
                                new OrdonnancesPaiementExport('impot', null, null, $data['mois'], $data['annee']),
                                'OP_Impot_' . $data['mois'] . '_' . $data['annee'] . '.xlsx'
                            );
                        }),

                    // Export PDF OP Standard
                    Tables\Actions\Action::make('export_pdf_standard')
                        ->label('Export PDF OP Standard')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->form([
                            Forms\Components\Select::make('mois')
                                ->label('Mois')
                                ->options([
                                    '01' => 'Janvier',
                                    '02' => 'Février',
                                    '03' => 'Mars',
                                    '04' => 'Avril',
                                    '05' => 'Mai',
                                    '06' => 'Juin',
                                    '07' => 'Juillet',
                                    '08' => 'Août',
                                    '09' => 'Septembre',
                                    '10' => 'Octobre',
                                    '11' => 'Novembre',
                                    '12' => 'Décembre',
                                ])
                                ->required()
                                ->default(date('m')),
                            Forms\Components\Select::make('annee')
                                ->label('Année')
                                ->options(function () {
                                    $years = [];
                                    for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
                                        $years[$i] = $i;
                                    }
                                    return $years;
                                })
                                ->required()
                                ->default(date('Y')),
                        ])
                        ->action(function (array $data) {
                            return static::exportPdf('standard', $data['mois'], $data['annee']);
                        }),

                    // Export PDF OP Impôt
                    Tables\Actions\Action::make('export_pdf_impot')
                        ->label('Export PDF OP Impôt')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->form([
                            Forms\Components\Select::make('mois')
                                ->label('Mois')
                                ->options([
                                    '01' => 'Janvier',
                                    '02' => 'Février',
                                    '03' => 'Mars',
                                    '04' => 'Avril',
                                    '05' => 'Mai',
                                    '06' => 'Juin',
                                    '07' => 'Juillet',
                                    '08' => 'Août',
                                    '09' => 'Septembre',
                                    '10' => 'Octobre',
                                    '11' => 'Novembre',
                                    '12' => 'Décembre',
                                ])
                                ->required()
                                ->default(date('m')),
                            Forms\Components\Select::make('annee')
                                ->label('Année')
                                ->options(function () {
                                    $years = [];
                                    for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
                                        $years[$i] = $i;
                                    }
                                    return $years;
                                })
                                ->required()
                                ->default(date('Y')),
                        ])
                        ->action(function (array $data) {
                            return static::exportPdf('impot', $data['mois'], $data['annee']);
                        }),
                ])
                    ->label('📥 Exports')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->button()
                    ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * ✅ Méthode pour exporter en PDF
     */
    protected static function exportPdf(string $type, string $mois, string $annee)
    {
        // Récupérer les ordonnances
        $ordonnances = OrdonnancePaiement::with(['engagement', 'beneficiaire', 'exercice'])
            ->where('type_ordonnance', $type)
            ->whereMonth('date_emission', $mois)
            ->whereYear('date_emission', $annee)
            ->orderBy('date_emission', 'desc')
            ->orderBy('numero', 'asc')
            ->get();

        // Calculer les statistiques
        $statistiques = [
            'nombre_total' => $ordonnances->count(),
            'montant_brut' => $ordonnances->sum('montant_brut'),
            'montant_impot' => $ordonnances->sum('montant_impot'),
            'montant_net' => $ordonnances->sum('montant_net'),
        ];

        // Préparer les filtres
        $moisNom = [
            '01' => 'Janvier',
            '02' => 'Février',
            '03' => 'Mars',
            '04' => 'Avril',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juillet',
            '08' => 'Août',
            '09' => 'Septembre',
            '10' => 'Octobre',
            '11' => 'Novembre',
            '12' => 'Décembre',
        ][$mois];

        $periode = $moisNom . ' ' . $annee;
        $filtres = [
            'Type : ' . ($type === 'standard' ? 'OP Standard' : 'OP Impôt'),
            'Période : ' . $periode,
        ];

        // Générer le PDF
        $pdf = Pdf::loadView('pdf.ordonnances-liste', [
            'ordonnances' => $ordonnances,
            'statistiques' => $statistiques,
            'periode' => $periode,
            'filtres' => $filtres,
            'utilisateur' => auth()->user()->name,
        ])
            ->setPaper('a4', 'landscape') // ← FORMAT PAYSAGE
            ->setOption('margin-top', 10)
            ->setOption('margin-right', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10);

        $filename = 'Liste_OP_' . ($type === 'standard' ? 'Standard' : 'Impot') . '_' . $mois . '_' . $annee . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    /**
     * ✅ Export PDF — Ordonnances Salaires
     */
    protected static function exportPdfSalaires(
        string  $typeOrdonnance,
        array   $nomenclatureIds,
        ?string $dateDebut,
        ?string $dateFin
    ) {
        $ordonnances = OrdonnancePaiement::with([
            'engagement.nomenclaturePrincipale',
            'engagement.engageable',
        ])
            ->where('type_ordonnance', $typeOrdonnance)
            ->whereHas('engagement', function ($q) use ($nomenclatureIds) {
                $q->whereIn('nomenclature_principale_id', $nomenclatureIds);
            })
            ->when($dateDebut, fn($q) => $q->whereDate('date_emission', '>=', $dateDebut))
            ->when($dateFin,   fn($q) => $q->whereDate('date_emission', '<=', $dateFin))
            ->orderBy('date_emission')
            ->orderBy('numero')
            ->get();

        $total = $ordonnances->sum(
            fn($op) =>
            (float) ($op->engagement?->montant_engage ?? 0)
        );

        $periode = '';
        if ($dateDebut && $dateFin) {
            $periode = \Carbon\Carbon::parse($dateDebut)->format('d/m/Y')
                . ' — '
                . \Carbon\Carbon::parse($dateFin)->format('d/m/Y');
        } elseif ($dateDebut) {
            $periode = 'À partir du ' . \Carbon\Carbon::parse($dateDebut)->format('d/m/Y');
        } elseif ($dateFin) {
            $periode = "Jusqu'au " . \Carbon\Carbon::parse($dateFin)->format('d/m/Y');
        }

        $titre = $typeOrdonnance === 'standard'
            ? 'RAPPORT DES ORDONNANCES DE PAIEMENT - SALAIRES'
            : 'RAPPORT DES ORDONNANCES DE PAIEMENT IMPÔT - SALAIRES';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ordonnances-salaires', [
            'ordonnances'    => $ordonnances,
            'total'          => $total,
            'periode'        => $periode,
            'titre'          => $titre,
            'typeOrdonnance' => $typeOrdonnance,
            'utilisateur'    => auth()->user()->name,
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 10)
            ->setOption('margin-right', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10);

        $suffix   = $typeOrdonnance === 'standard' ? 'Standard' : 'Impot';
        $filename = "Rapport_OP_{$suffix}_Salaires_" . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }


    /**
     * ✅ Formulaire commun pour les exports salaires
     */
    protected static function formulaireExportSalaires(): array
    {
        return [
            // ── Sélection des nomenclatures ───────────────────────────
            Forms\Components\Select::make('nomenclature_ids')
                ->label('Lignes de nomenclature (Salaires)')
                ->options(function () {

                    // ✅ Récupérer les nomenclatures utilisées dans des engagements
                    $nomenclatureIdsUtilisees = \App\Models\Engagement::withoutGlobalScope('exercice')
                        ->whereIn('statut', ['provisoire', 'definitif'])
                        ->whereNotNull('nomenclature_principale_id')
                        ->pluck('nomenclature_principale_id')
                        ->unique()
                        ->values();

                    // ✅ Charger les nomenclatures correspondantes
                    // filtrées par mots-clés salaires (avec ou sans le whereHas)
                    $query = \App\Models\NomenclatureBudgetaire::query();

                    // ✅ Si on veut restreindre aux nomenclatures ayant des engagements
                    if ($nomenclatureIdsUtilisees->isNotEmpty()) {
                        $query->whereIn('id', $nomenclatureIdsUtilisees);
                    }

                    // ✅ Filtre salaires — mots-clés + codes budgétaires
                    $query->where(function ($q) {
                        $q->where('libelle', 'ilike', '%salaire%')
                            ->orWhere('libelle', 'ilike', '%personnel%')
                            ->orWhere('libelle', 'ilike', '%rémunération%')
                            ->orWhere('libelle', 'ilike', '%remuneration%')
                            ->orWhere('libelle', 'ilike', '%indemnité%')
                            ->orWhere('libelle', 'ilike', '%indemnite%')
                            ->orWhere('libelle', 'ilike', '%traitement%')
                            ->orWhere('libelle', 'ilike', '%prime%')
                            ->orWhere('code',    'ilike', '611%')
                            ->orWhere('code',    'ilike', '612%')
                            ->orWhere('code',    'ilike', '613%')
                            ->orWhere('code',    'ilike', '621%')
                            ->orWhere('code',    'ilike', '6611%');
                    });

                    return $query->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn($n) => [
                            $n->id => "{$n->code} — {$n->libelle}"
                        ]);
                })
                ->multiple()
                ->required()
                ->searchable()
                ->helperText('Sélectionnez une ou plusieurs lignes de nomenclature')
                ->columnSpanFull(),

            // ── Mode de période ───────────────────────────────────────
            Forms\Components\Radio::make('mode_periode')
                ->label('Mode de sélection de la période')
                ->options([
                    'mois'   => '📅 Par mois',
                    'plage'  => '📆 Par plage de dates',
                ])
                ->default('mois')
                ->live()
                ->columnSpanFull(),

            // ── Mode Mois ─────────────────────────────────────────────
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('mois')
                    ->label('Mois')
                    ->options([
                        '01' => 'Janvier',
                        '02' => 'Février',
                        '03' => 'Mars',
                        '04' => 'Avril',
                        '05' => 'Mai',
                        '06' => 'Juin',
                        '07' => 'Juillet',
                        '08' => 'Août',
                        '09' => 'Septembre',
                        '10' => 'Octobre',
                        '11' => 'Novembre',
                        '12' => 'Décembre',
                    ])
                    ->default(date('m'))
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'mois')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'mois'),

                Forms\Components\Select::make('annee')
                    ->label('Année')
                    ->options(function () {
                        $years = [];
                        for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
                            $years[$i] = $i;
                        }
                        return $years;
                    })
                    ->default(date('Y'))
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'mois')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'mois'),
            ]),

            // ── Mode Plage de dates ───────────────────────────────────
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('date_debut')
                    ->label('Date début')
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'plage')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'plage'),

                Forms\Components\DatePicker::make('date_fin')
                    ->label('Date fin')
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'plage')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'plage'),
            ]),
        ];
    }

    /**
     * ✅ Résoudre la période selon le mode choisi
     * Retourne [dateDebut, dateFin]
     */
    protected static function resoudrePeriode(array $data): array
    {
        if (($data['mode_periode'] ?? 'mois') === 'mois') {
            $mois  = $data['mois']  ?? date('m');
            $annee = $data['annee'] ?? date('Y');

            $dateDebut = \Carbon\Carbon::createFromDate($annee, $mois, 1)
                ->startOfMonth()->toDateString();
            $dateFin   = \Carbon\Carbon::createFromDate($annee, $mois, 1)
                ->endOfMonth()->toDateString();
        } else {
            $dateDebut = $data['date_debut'] ?? null;
            $dateFin   = $data['date_fin']   ?? null;
        }

        return [$dateDebut, $dateFin];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdonnancePaiements::route('/'),
            'create' => Pages\CreateOrdonnancePaiement::route('/create'),
            'view' => Pages\ViewOrdonnancePaiement::route('/{record}'),
            'edit' => Pages\EditOrdonnancePaiement::route('/{record}/edit'),
        ];
    }
}
