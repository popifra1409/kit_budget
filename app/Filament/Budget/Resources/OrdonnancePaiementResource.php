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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\EtatConfig;
use Illuminate\Support\Facades\DB;

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

        if (!$user?->can('update_ordonnance_paiement')) return false;
        if ($record->statut !== 'brouillon') return false;
        if ($user->can('override_ordonnance_paiement')) return true;

        return $record->created_by === $user->id;
    }

    /**
     * ✅ CORRIGÉ — suppression autorisée tant que l'OP n'est pas payée
     * Statuts autorisés : brouillon, emise, visee
     */
    public static function canDelete($record): bool
    {
        $user = auth()->user();

        if (!$user?->can('delete_ordonnance_paiement')) return false;
        if ($record->statut === 'payee') return false;

        return $user->can('override_ordonnance_paiement') || $record->created_by === $user->id;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type_ordonnance', ['standard', 'impot']);
    }

    protected static ?string $recordTitleAttribute = 'numero';

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'objet'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Type'    => $record->type_ordonnance === 'impot' ? 'OPT' : 'OP Standard',
            'Montant' => number_format($record->montant_net, 0, ',', ' ') . ' FCFA',
            'Statut'  => $record->statut_label,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
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

    protected static function getApercu(?int $engagementId): string
    {
        if (!$engagementId) return '';

        try {
            $engagement = Engagement::with('engageable', 'beneficiaire')->find($engagementId);
            if (!$engagement) return '';

            $donnees = $engagement->extraireDonneesDocument();

            if ($engagement->estBonCommande()) {
                $retenues = ($donnees['montant_ir'] ?? 0) + ($donnees['montant_tva'] ?? 0) + ($donnees['montant_tsr'] ?? 0);
            } else {
                $retenues = ($donnees['montant_ir'] ?? 0) + ($donnees['montant_cnps'] ?? 0) + ($donnees['montant_irnc'] ?? 0) + ($donnees['autres_retenues'] ?? 0);
            }

            $beneficiaire = $donnees['beneficiaire'];
            $nomBenef = $beneficiaire->raison_sociale ?? $beneficiaire->nom_complet ?? $beneficiaire->name ?? 'N/A';

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
                    ->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\BadgeColumn::make('type_ordonnance')
                    ->label('Type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'standard' => 'Standard',
                        'impot'    => 'Impôt',
                        default    => $state,
                    })
                    ->colors(['primary' => 'standard', 'warning' => 'impot']),

                Tables\Columns\TextColumn::make('engagement.numero')
                    ->label('Engagement')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('objet')
                    ->label('Objet')->limit(40)->searchable(),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->formatStateUsing(fn($record) => $record->statut_label)
                    ->color(fn($record) => $record->statut_color),

                Tables\Columns\TextColumn::make('montant_brut')
                    ->label('Montant brut')->money('XAF')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_impot')
                    ->label('À précompter')->money('XAF')->sortable()->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant Net')->money('XAF')->sortable()->weight('bold')->color('success'),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Date émission')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('date_paiement')
                    ->label('Date paiement')->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                            ->default('today')->placeholder('Sélectionner une période'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? 'today';
                        return match ($periode) {
                            'today'         => $query->whereDate('date_emission', today()),
                            'yesterday'     => $query->whereDate('date_emission', today()->subDay()),
                            'this_week'     => $query->whereBetween('date_emission', [now()->startOfWeek(), now()->endOfWeek()]),
                            'last_week'     => $query->whereBetween('date_emission', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                            'this_month'    => $query->whereMonth('date_emission', now()->month)->whereYear('date_emission', now()->year),
                            'last_month'    => $query->whereMonth('date_emission', now()->subMonth()->month)->whereYear('date_emission', now()->subMonth()->year),
                            'this_quarter'  => $query->whereBetween('date_emission', [now()->startOfQuarter(), now()->endOfQuarter()]),
                            'last_quarter'  => $query->whereBetween('date_emission', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]),
                            'this_year'     => $query->whereYear('date_emission', now()->year),
                            'last_year'     => $query->whereYear('date_emission', now()->subYear()->year),
                            default         => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
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
                        return 'Période : ' . ($labels[$data['periode'] ?? 'today'] ?? 'Aujourd\'hui');
                    }),

                Tables\Filters\SelectFilter::make('type_ordonnance')
                    ->label('Type')
                    ->options(['standard' => 'Standard', 'impot' => 'Impôt']),

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
                        Forms\Components\DatePicker::make('date_emission_from')->label('Date d\'émission du'),
                        Forms\Components\DatePicker::make('date_emission_until')->label('Date d\'émission au'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['date_emission_from'],  fn($q, $d) => $q->whereDate('date_emission', '>=', $d))
                            ->when($data['date_emission_until'], fn($q, $d) => $q->whereDate('date_emission', '<=', $d))
                    ),
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([

                    // ── Navigation ────────────────────────────────
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn($record) => static::canEdit($record)),

                    // ── Workflow ──────────────────────────────────
                    Tables\Actions\Action::make('emettre')
                        ->label('Émettre')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn($record) => $record->statut === 'brouillon')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->emettre();
                            Notification::make()->title('Ordonnance émise')->success()->send();
                        }),

                    Tables\Actions\Action::make('marquer_payee')
                        ->label('Marquer payée')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn($record) => in_array($record->statut, ['emise', 'visee']))
                        ->form([
                            Forms\Components\DatePicker::make('date_paiement')
                                ->label('Date de paiement')->required()->default(now()),
                            Forms\Components\TextInput::make('reference_paiement')
                                ->label('Référence de paiement')->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->marquerPayee($data['reference_paiement']);
                            $record->date_paiement = $data['date_paiement'];
                            $record->save();
                            Notification::make()->title('Paiement enregistré')->success()->send();
                        }),

                    // ════════════════════════════════════════════════
                    // ✅ SUPPRESSION OP STANDARD
                    //    → supprime l'OPT liée automatiquement
                    //    → remet l'engagement à 'valide'
                    // ════════════════════════════════════════════════
                    Tables\Actions\Action::make('supprimer_op')
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(
                            fn($record) =>
                            $record->type_ordonnance === 'standard'
                                && $record->statut !== 'payee'
                                && static::canDelete($record)
                        )
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-exclamation-triangle')
                        ->modalHeading(fn($record) => 'Supprimer ' . $record->numero . ' ?')
                        ->modalDescription(function ($record) {
                            $opt = OrdonnancePaiement::where('engagement_id', $record->engagement_id)
                                ->where('type_ordonnance', 'impot')
                                ->first();

                            $msg = 'Cette action est <strong>irréversible</strong>.'
                                . ' L\'engagement associé sera remis à l\'état <strong>Validé</strong>.';

                            if ($opt) {
                                $msg .= '<br><br>⚠️ L\'ordonnance impôt <strong>'
                                    . $opt->numero
                                    . '</strong> sera également supprimée automatiquement.';
                            }

                            return new \Illuminate\Support\HtmlString($msg);
                        })
                        ->action(function ($record) {
                            DB::transaction(function () use ($record) {
                                // 1. Supprimer l'OPT liée (même engagement)
                                OrdonnancePaiement::where('engagement_id', $record->engagement_id)
                                    ->where('type_ordonnance', 'impot')
                                    ->each(fn($opt) => $opt->delete());

                                // 2. Remettre l'engagement à 'valide'
                                if ($record->engagement_id) {
                                    Engagement::where('id', $record->engagement_id)
                                        ->update(['statut' => 'provisoire']);
                                }

                                // 3. Supprimer l'OP
                                $numero = $record->numero;
                                $record->delete();

                                Notification::make()
                                    ->title('✅ Ordonnance supprimée')
                                    ->body($numero . ' supprimée. Engagement remis à l\'état Validé.')
                                    ->success()
                                    ->send();
                            });
                        }),

                    // ════════════════════════════════════════════════
                    // ✅ SUPPRESSION OPT (Impôt)
                    //    → supprime l'OPT
                    //    → confirmation optionnelle pour supprimer l'OP
                    //      génératrice + remettre l'engagement à 'valide'
                    // ════════════════════════════════════════════════
                    Tables\Actions\Action::make('supprimer_opt')
                        ->label('Supprimer')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(
                            fn($record) =>
                            $record->type_ordonnance === 'impot'
                                && $record->statut !== 'payee'
                                && static::canDelete($record)
                        )
                        ->modalIcon('heroicon-o-exclamation-triangle')
                        ->modalHeading(fn($record) => 'Supprimer ' . $record->numero . ' ?')
                        ->modalDescription(function ($record) {
                            $op = OrdonnancePaiement::where('engagement_id', $record->engagement_id)
                                ->where('type_ordonnance', 'standard')
                                ->first();

                            if ($op) {
                                return new \Illuminate\Support\HtmlString(
                                    'Vous supprimez uniquement l\'OPT <strong>' . $record->numero . '</strong>.'
                                        . '<br>L\'OP génératrice <strong>' . $op->numero . '</strong> sera <u>conservée</u>.'
                                        . '<br><br>Cochez l\'option ci-dessous pour la supprimer aussi '
                                        . '(l\'engagement sera alors remis à <strong>Validé</strong>).'
                                );
                            }

                            return new \Illuminate\Support\HtmlString(
                                'Suppression de l\'OPT <strong>' . $record->numero . '</strong>. Action irréversible.'
                            );
                        })
                        ->form([
                            Forms\Components\Checkbox::make('supprimer_op_aussi')
                                ->label(function ($record) {
                                    $op = OrdonnancePaiement::where('engagement_id', $record->engagement_id)
                                        ->where('type_ordonnance', 'standard')
                                        ->first();
                                    return $op
                                        ? '⚠️ Supprimer aussi l\'OP génératrice ' . $op->numero . ' (remet l\'engagement à Validé)'
                                        : 'Supprimer aussi l\'OP génératrice (remet l\'engagement à Validé)';
                                })
                                ->default(false),
                        ])
                        ->action(function ($record, array $data) {
                            DB::transaction(function () use ($record, $data) {
                                $numeroOpt = $record->numero;

                                if ($data['supprimer_op_aussi'] ?? false) {
                                    // Trouver et supprimer l'OP génératrice
                                    $op = OrdonnancePaiement::where('engagement_id', $record->engagement_id)
                                        ->where('type_ordonnance', 'standard')
                                        ->first();

                                    if ($op) {
                                        $numeroOp = $op->numero;
                                        $op->delete();

                                        // Remettre l'engagement à 'valide'
                                        if ($record->engagement_id) {
                                            Engagement::where('id', $record->engagement_id)
                                                ->update(['statut' => 'provisoire']);
                                        }

                                        // Supprimer l'OPT
                                        $record->delete();

                                        Notification::make()
                                            ->title('✅ OPT et OP supprimées')
                                            ->body($numeroOpt . ' + ' . $numeroOp . ' supprimées. Engagement remis à Validé.')
                                            ->success()
                                            ->send();

                                        return;
                                    }
                                }

                                // Supprimer uniquement l'OPT
                                $record->delete();

                                Notification::make()
                                    ->title('✅ OPT supprimée')
                                    ->body($numeroOpt . ' supprimée. L\'OP génératrice est conservée.')
                                    ->success()
                                    ->send();
                            });
                        }),

                    // ── PDF OP Standard ───────────────────────────
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
                            $livewire->dispatch('open-url-new-tab', url: route('pdf.afficher', [
                                'etat' => $data['variante'],
                                'id'   => $record->id,
                            ]));
                        }),

                    Tables\Actions\Action::make('telecharger_op')
                        ->label('Télécharger OP')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
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
                            $livewire->dispatch('open-url-new-tab', url: route('pdf.telecharger', [
                                'etat' => $data['variante'],
                                'id'   => $record->id,
                            ]));
                        }),

                    // ── PDF OPT Impôt ─────────────────────────────
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
                            $livewire->dispatch('open-url-new-tab', url: route('pdf.afficher', [
                                'etat' => $data['variante'],
                                'id'   => $record->id,
                            ]));
                        }),

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
                            $livewire->dispatch('open-url-new-tab', url: route('pdf.telecharger', [
                                'etat' => $data['variante'],
                                'id'   => $record->id,
                            ]));
                        }),

                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),

            ], position: ActionsPosition::BeforeColumns)

            ->headerActions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_excel_salaires_standard')
                        ->label('Excel OP Standard - Salaires')
                        ->icon('heroicon-o-table-cells')->color('success')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            $nomenclatureIds = $data['nomenclature_ids'] ?? [];
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);
                            return Excel::download(
                                new \App\Exports\OrdonnancesSalairesExport('standard', $nomenclatureIds, $dateDebut, $dateFin),
                                'OP_Standard_Salaires_' . now()->format('Y-m-d') . '.xlsx'
                            );
                        }),

                    Tables\Actions\Action::make('export_pdf_salaires_standard')
                        ->label('PDF OP Standard - Salaires')
                        ->icon('heroicon-o-document-text')->color('danger')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);
                            return static::exportPdfSalaires('standard', $data['nomenclature_ids'] ?? [], $dateDebut, $dateFin);
                        }),

                    Tables\Actions\Action::make('export_excel_salaires_impot')
                        ->label('Excel OPT Impôt - Salaires')
                        ->icon('heroicon-o-table-cells')->color('warning')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            $nomenclatureIds = $data['nomenclature_ids'] ?? [];
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);
                            return Excel::download(
                                new \App\Exports\OrdonnancesSalairesExport('impot', $nomenclatureIds, $dateDebut, $dateFin),
                                'OPT_Impot_Salaires_' . now()->format('Y-m-d') . '.xlsx'
                            );
                        }),

                    Tables\Actions\Action::make('export_pdf_salaires_impot')
                        ->label('PDF OPT Impôt - Salaires')
                        ->icon('heroicon-o-document-text')->color('gray')
                        ->form(static::formulaireExportSalaires())
                        ->action(function (array $data) {
                            [$dateDebut, $dateFin] = static::resoudrePeriode($data);
                            return static::exportPdfSalaires('impot', $data['nomenclature_ids'] ?? [], $dateDebut, $dateFin);
                        }),
                ])
                    ->label('💼 Rapports Salaires')
                    ->icon('heroicon-o-banknotes')
                    ->button()->color('info'),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_excel_standard')
                        ->label('Export Excel OP Standard')
                        ->icon('heroicon-o-table-cells')->color('success')
                        ->form(static::formulaireExportMensuel())
                        ->action(fn(array $data) => Excel::download(
                            new OrdonnancesPaiementExport('standard', null, null, $data['mois'], $data['annee']),
                            'OP_Standard_' . $data['mois'] . '_' . $data['annee'] . '.xlsx'
                        )),

                    Tables\Actions\Action::make('export_excel_impot')
                        ->label('Export Excel OP Impôt')
                        ->icon('heroicon-o-table-cells')->color('warning')
                        ->form(static::formulaireExportMensuel())
                        ->action(fn(array $data) => Excel::download(
                            new OrdonnancesPaiementExport('impot', null, null, $data['mois'], $data['annee']),
                            'OP_Impot_' . $data['mois'] . '_' . $data['annee'] . '.xlsx'
                        )),

                    Tables\Actions\Action::make('export_pdf_standard')
                        ->label('Export PDF OP Standard')
                        ->icon('heroicon-o-document-text')->color('danger')
                        ->form(static::formulaireExportMensuel())
                        ->action(fn(array $data) => static::exportPdf('standard', $data['mois'], $data['annee'])),

                    Tables\Actions\Action::make('export_pdf_impot')
                        ->label('Export PDF OP Impôt')
                        ->icon('heroicon-o-document-text')->color('gray')
                        ->form(static::formulaireExportMensuel())
                        ->action(fn(array $data) => static::exportPdf('impot', $data['mois'], $data['annee'])),
                ])
                    ->label('📥 Exports')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->button()->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ════════════════════════════════════════════════════════
    // FORMULAIRES PARTAGÉS
    // ════════════════════════════════════════════════════════

    protected static function formulaireExportMensuel(): array
    {
        $moisOptions = [
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
        ];
        $anneeOptions = [];
        for ($i = date('Y'); $i >= date('Y') - 5; $i--) $anneeOptions[$i] = $i;

        return [
            Forms\Components\Select::make('mois')->label('Mois')
                ->options($moisOptions)->required()->default(date('m')),
            Forms\Components\Select::make('annee')->label('Année')
                ->options($anneeOptions)->required()->default(date('Y')),
        ];
    }

    protected static function formulaireExportSalaires(): array
    {
        $moisOptions = [
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
        ];
        $anneeOptions = [];
        for ($i = date('Y'); $i >= date('Y') - 5; $i--) $anneeOptions[$i] = $i;

        return [
            Forms\Components\Select::make('nomenclature_ids')
                ->label('Lignes de nomenclature budgétaire')
                ->options(function () {
                    $ids = \App\Models\Engagement::withoutGlobalScope('exercice')
                        ->whereIn('statut', ['provisoire', 'definitif'])
                        ->whereNotNull('nomenclature_principale_id')
                        ->pluck('nomenclature_principale_id')->unique();
                    return \App\Models\NomenclatureBudgetaire::whereIn('id', $ids)
                        ->orderBy('code')->get()
                        ->mapWithKeys(fn($n) => [$n->id => "{$n->code} — {$n->libelle}"]);
                })
                ->multiple()->required()->searchable()
                ->helperText('Sélectionnez une ou plusieurs lignes')
                ->columnSpanFull(),

            Forms\Components\Radio::make('mode_periode')
                ->label('Mode de sélection de la période')
                ->options(['mois' => '📅 Par mois', 'plage' => '📆 Par plage de dates'])
                ->default('mois')->live()->columnSpanFull(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('mois')->label('Mois')
                    ->options($moisOptions)->default(date('m'))
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'mois')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'mois'),
                Forms\Components\Select::make('annee')->label('Année')
                    ->options($anneeOptions)->default(date('Y'))
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'mois')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'mois'),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DatePicker::make('date_debut')->label('Date début')
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'plage')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'plage'),
                Forms\Components\DatePicker::make('date_fin')->label('Date fin')
                    ->required(fn(Forms\Get $get) => $get('mode_periode') === 'plage')
                    ->visible(fn(Forms\Get $get)  => $get('mode_periode') === 'plage'),
            ]),
        ];
    }

    protected static function resoudrePeriode(array $data): array
    {
        if (($data['mode_periode'] ?? 'mois') === 'mois') {
            $mois  = $data['mois']  ?? date('m');
            $annee = $data['annee'] ?? date('Y');
            return [
                \Carbon\Carbon::createFromDate($annee, $mois, 1)->startOfMonth()->toDateString(),
                \Carbon\Carbon::createFromDate($annee, $mois, 1)->endOfMonth()->toDateString(),
            ];
        }
        return [$data['date_debut'] ?? null, $data['date_fin'] ?? null];
    }

    // ════════════════════════════════════════════════════════
    // EXPORTS PDF
    // ════════════════════════════════════════════════════════

    protected static function exportPdf(string $type, string $mois, string $annee)
    {
        $ordonnances = OrdonnancePaiement::with(['engagement', 'beneficiaire', 'exercice'])
            ->where('type_ordonnance', $type)
            ->whereMonth('date_emission', $mois)
            ->whereYear('date_emission', $annee)
            ->orderBy('date_emission', 'desc')
            ->orderBy('numero', 'asc')
            ->get();

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

        $pdf = Pdf::loadView('pdf.ordonnances-liste', [
            'ordonnances'  => $ordonnances,
            'statistiques' => [
                'nombre_total'  => $ordonnances->count(),
                'montant_brut'  => $ordonnances->sum('montant_brut'),
                'montant_impot' => $ordonnances->sum('montant_impot'),
                'montant_net'   => $ordonnances->sum('montant_net'),
            ],
            'periode'     => $moisNom . ' ' . $annee,
            'filtres'     => ['Type : ' . ($type === 'standard' ? 'OP Standard' : 'OP Impôt'), 'Période : ' . $moisNom . ' ' . $annee],
            'utilisateur' => auth()->user()->name,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 10)->setOption('margin-right', 10)
            ->setOption('margin-bottom', 10)->setOption('margin-left', 10);

        $filename = 'Liste_OP_' . ($type === 'standard' ? 'Standard' : 'Impot') . '_' . $mois . '_' . $annee . '.pdf';
        return response()->streamDownload(fn() => print($pdf->output()), $filename);
    }

    protected static function exportPdfSalaires(string $typeOrdonnance, array $nomenclatureIds, ?string $dateDebut, ?string $dateFin)
    {
        $groupes = OrdonnancePaiement::with(['engagement.nomenclaturePrincipale', 'engagement.engageable'])
            ->where('type_ordonnance', $typeOrdonnance)
            ->whereHas('engagement', fn($q) => $q->whereIn('nomenclature_principale_id', $nomenclatureIds))
            ->when($dateDebut, fn($q) => $q->whereDate('date_emission', '>=', $dateDebut))
            ->when($dateFin,   fn($q) => $q->whereDate('date_emission', '<=', $dateFin))
            ->get()
            ->groupBy(fn($op) => $op->engagement?->nomenclature_principale_id ?? 'sans')
            ->map(fn($ops) => [
                'nomenclature' => $ops->first()?->engagement?->nomenclaturePrincipale,
                'ordonnances'  => $ops->sortBy('numero'),
                'total'        => $ops->sum(fn($op) => (float) ($op->engagement?->montant_engage ?? 0)),
            ]);

        $periode = '';
        if ($dateDebut && $dateFin)
            $periode = \Carbon\Carbon::parse($dateDebut)->format('d/m/Y') . ' — ' . \Carbon\Carbon::parse($dateFin)->format('d/m/Y');

        $pdf = Pdf::loadView('pdf.ordonnances-salaires', [
            'groupes'        => $groupes,
            'grandTotal'     => $groupes->sum('total'),
            'periode'        => $periode,
            'titre'          => $typeOrdonnance === 'standard'
                ? 'RAPPORT DES ORDONNANCES DE PAIEMENT — PAR NOMENCLATURE'
                : 'RAPPORT DES ORDONNANCES DE PAIEMENT IMPÔT — PAR NOMENCLATURE',
            'typeOrdonnance' => $typeOrdonnance,
            'utilisateur'    => auth()->user()->name,
            'dateGeneration' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 10)->setOption('margin-right', 10)
            ->setOption('margin-bottom', 10)->setOption('margin-left', 10);

        $suffix = $typeOrdonnance === 'standard' ? 'Standard' : 'Impot';
        return response()->streamDownload(fn() => print($pdf->output()), "Rapport_OP_{$suffix}_" . now()->format('Y-m-d') . '.pdf');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrdonnancePaiements::route('/'),
            'create' => Pages\CreateOrdonnancePaiement::route('/create'),
            'view'   => Pages\ViewOrdonnancePaiement::route('/{record}'),
            'edit'   => Pages\EditOrdonnancePaiement::route('/{record}/edit'),
        ];
    }
}
