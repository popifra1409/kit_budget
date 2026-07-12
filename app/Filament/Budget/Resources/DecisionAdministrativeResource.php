<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;
use App\Models\DecisionAdministrative;
use App\Models\Budget;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use App\Models\Exercice;
use App\Filament\Actions\WorkflowActions;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Enums\ActionsPosition;

class DecisionAdministrativeResource extends Resource
{
    protected static ?string $model           = DecisionAdministrative::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Décisions';
    protected static ?string $modelLabel      = 'Décision';
    protected static ?string $pluralModelLabel = 'Décisions';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $recordTitleAttribute = 'numero';
    protected static int     $globalSearchResultsLimit = 20;

    // ── Navigation badge ──────────────────────────────────────
    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\Transmission::query()
            ->where('document_type', 'App\Models\DecisionAdministrative')
            ->pourDestinataire(auth()->id())
            ->enAttente()
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'objet', 'statut'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Objet'        => $record->objet,
            'Statut'       => $record->statut,
            'Bénéficiaire' => $record->getNomCompletPersonnel()
                ?: $record->fournisseur?->raison_sociale,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['fournisseur']);
    }

    // ── Permissions ───────────────────────────────────────────
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_decision_administrative');
    }
    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_decision_administrative');
    }
    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('create_decision_administrative');
    }
    public static function canEdit($record): bool
    {
        if (!auth()->check()) return false;
        $user = auth()->user();
        if (!$user->can('update_decision_administrative')) return false;
        if ($record->estEnCoursDeTransmission()) {
            return $user->can('force_update_decision_administrative');
        }
        if (!$record->estModifiable()) {
            if ($record->exercice && !$record->exercice->estModifiable()) {
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
        if (!auth()->user()->can('delete_decision_administrative')) return false;
        return $record->estModifiable();
    }
    public static function canValider($record): bool
    {
        return auth()->check() && auth()->user()->can('valider_decision_administrative');
    }
    public static function canAnnuler($record): bool
    {
        return auth()->check() && auth()->user()->can('annuler_decision_administrative');
    }
    public static function canRecuperer($record): bool
    {
        return auth()->user()?->can('recuperer_decision_administrative') ?? false;
    }
    public static function canEngager($record): bool
    {
        return auth()->check() && auth()->user()?->can('engager_decision_administrative') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScope('exercice')
            ->with('exercice')
            ->addSelect([
                'memoire_numero' => \App\Models\MemoireDepense::select('numero')
                    ->whereColumn('decision_administrative_id', 'decisions_administratives.id')
                    ->limit(1),
            ]);

        $user = auth()->user();
        if (!$user) return $query->whereRaw('1 = 0');

        // ✅ Supervision : voit tout
        if ($user->hasAnyRole(['super_admin', 'admin', 'daaf', 'agence_comptable'])) {
            return $query;
        }

        // ✅ Permission view_any = voit tout (contrôleur, chef de service global, etc.)
        if ($user->can('view_any_decision_administrative')) {
            return $query;
        }

        // ✅ Utilisateur standard : vision restreinte
        return $query->where(function ($q) use ($user) {

            // Mes documents SANS transmission active (brouillon ou retournés)
            $q->where(function ($s) use ($user) {
                $s->where('created_by', $user->id)
                    ->whereDoesntHave('transmissions', function ($t) {
                        $t->where('statut', 'en_attente');
                    });
            })

                // Documents transmis À MOI (en_attente)
                ->orWhereHas('transmissions', function ($t) use ($user) {
                    $t->where('destinataire_id', $user->id)
                        ->where('statut', 'en_attente');
                });
        });
    }

    // =========================================================
    // FORMULAIRE
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Exercice')
                ->schema([
                    ExerciceSelect::make(
                        avecReports: auth()->user()?->hasAnyRole(['super_admin', 'admin'])
                    ),
                ])
                ->collapsible()
                ->collapsed(fn($record) => $record !== null),

            Forms\Components\Section::make('Informations principales')
                ->schema([
                    Forms\Components\Select::make('budget_id')
                        ->label('Budget')
                        ->options(Budget::where('actif', true)->pluck('libelle', 'id'))
                        ->required()->searchable()->preload()->live()->columnSpan(1),

                    Forms\Components\Select::make('service_emetteur_id')
                        ->label('Service émetteur')
                        ->options(
                            \App\Models\Service::where('actif', true)->pluck('nom', 'id')
                        )
                        ->searchable()->preload(),

                    Forms\Components\ToggleButtons::make('type_beneficiaire')
                        ->label('Type de bénéficiaire')
                        ->options(['personnel' => 'Personnel', 'fournisseur' => 'Fournisseur'])
                        ->icons(['personnel' => 'heroicon-o-user', 'fournisseur' => 'heroicon-o-building-office'])
                        ->default('personnel')->inline()->required()->live()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if ($state === 'personnel') $set('fournisseur_id', null);
                            else $set('personnel_id', null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('personnel_id')
                        ->label('Personnel concerné')
                        ->options(fn() => \App\Models\Personnel::where('actif', true)
                            ->orderBy('nom')->orderBy('prenoms')->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->matricule} - {$p->nom} {$p->prenoms}"
                                    . ($p->fonction ? " ({$p->fonction})" : '')
                            ]))
                        ->searchable()->preload()
                        ->required(fn(Get $get) => $get('type_beneficiaire') === 'personnel')
                        ->visible(fn(Get $get)   => $get('type_beneficiaire') === 'personnel')
                        ->live()->columnSpanFull(),

                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur concerné')
                        ->relationship('fournisseur', 'raison_sociale')
                        ->searchable()->preload()
                        ->required(fn(Get $get) => $get('type_beneficiaire') === 'fournisseur')
                        ->visible(fn(Get $get)   => $get('type_beneficiaire') === 'fournisseur')
                        ->live()->columnSpanFull(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Type et objet')
                ->schema([
                    Forms\Components\Select::make('type_decision_id')
                        ->label('Type de décision')
                        ->relationship(
                            'typeDecision',
                            'libelle',
                            fn(\Illuminate\Database\Eloquent\Builder $query) => $query->actif()->ordonne()
                        )
                        ->required()->searchable()->preload(),

                    Forms\Components\DatePicker::make('date_decision')
                        ->label('Date de décision')->required()->default(now()),

                    Forms\Components\DatePicker::make('date_effet')
                        ->label("Date de prise d'effet")->after('date_decision'),

                    Forms\Components\DatePicker::make('date_fin')
                        ->label('Date de fin')->after('date_effet')
                        ->helperText('Pour missions, formations, etc.'),

                    Forms\Components\Textarea::make('objet')
                        ->label('Objet de la décision')->required()->rows(3)
                        ->placeholder("Ex: Prime exceptionnelle de fin d'année")
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // =========================================================
            // MONTANTS ET RETENUES
            // =========================================================
            Forms\Components\Section::make('Montants et retenues')
                ->description('Choisissez le mode de saisie.')
                ->schema([

                    Forms\Components\ToggleButtons::make('mode_saisie')
                        ->label('Mode de saisie')
                        ->options([
                            'calcule' => '🔢 Calculé (formules)',
                            'forfait' => '✍️ Forfaitaire (libre)',
                        ])
                        ->default('calcule')->inline()->live()->dehydrated(true)
                        ->columnSpanFull()
                        ->helperText(
                            fn(Get $get) =>
                            $get('mode_saisie') === 'forfait'
                                ? '⚠️ Mode forfaitaire : vous saisissez tous les montants manuellement.'
                                : '💡 Mode calculé : les retenues sont calculées automatiquement.'
                        ),

                    // =================================================
                    // MODE CALCULÉ
                    // =================================================
                    Forms\Components\Group::make([

                        // ── Montant brut + TVA ─────────────────────────
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('montant_brut')
                                ->label('Montant Brut (TTC)')
                                ->numeric()->required()->prefix('FCFA')
                                ->live(onBlur: true)
                                ->helperText('Montant TTC incluant la TVA'),

                            Forms\Components\Select::make('type_tva')
                                ->label('Type TVA')
                                ->options(['taux' => 'Taux (%)', 'forfait' => 'Forfait (FCFA)'])
                                ->default('taux')->live()->required(),

                            Forms\Components\TextInput::make('taux_tva')
                                ->label('Taux TVA (%)')->numeric()->default(19.25)->step(0.01)->suffix('%')
                                ->live(onBlur: true),

                            Forms\Components\Placeholder::make('montant_ht_affiche')
                                ->label('💰 Montant HT (calculé)')
                                ->content(function (Get $get) {
                                    $brut = (float) ($get('montant_brut') ?? 0);
                                    $taux = (float) ($get('taux_tva') ?? 19.25);
                                    if ($brut <= 0) return '0 FCFA';
                                    return number_format($brut / (1 + $taux / 100), 0, ',', ' ') . ' FCFA';
                                }),
                        ])->columnSpanFull(),

                        // ── CNPS ──────────────────────────────────────
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\TextInput::make('taux_cnps')
                                ->label('CNPS (%)')->numeric()->placeholder(4.2)->step(0.01)->suffix('%')
                                ->live(onBlur: true),

                            Forms\Components\Placeholder::make('montant_cnps_calcule')
                                ->label('Montant CNPS calculé')
                                ->content(function (Get $get) {
                                    $ht = static::getHt($get);
                                    $taux = (float) ($get('taux_cnps') ?? 0);
                                    return number_format($ht * $taux / 100, 0, ',', ' ') . ' FCFA';
                                })
                                ->columnSpan(3),
                        ])->columnSpanFull(),

                        // ✅ IR standard — juste après CNPS
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\TextInput::make('taux_ir')
                                ->label('IR (%)')
                                ->numeric()->default(0)->step(0.01)->suffix('%')
                                ->live(onBlur: true)
                                ->helperText('Impôt sur le Revenu standard'),

                            Forms\Components\Placeholder::make('montant_ir_calcule')
                                ->label('Montant IR calculé')
                                ->content(function (Get $get) {
                                    $ht   = static::getHt($get);
                                    $taux = (float) ($get('taux_ir') ?? 0);
                                    return number_format($ht * $taux / 100, 0, ',', ' ') . ' FCFA';
                                })
                                ->columnSpan(3),
                        ])->columnSpanFull(),

                        // ✅ IRNC — dans les "autres retenues" avec type (taux/forfait)
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('type_irnc')
                                ->label('Type IR(NC)')
                                ->options(['taux' => 'Taux (%)', 'forfait' => 'Forfait (FCFA)'])
                                ->default('taux')->live()->required()
                                ->helperText('IR Non Commercial'),

                            Forms\Components\TextInput::make('taux_irnc')
                                ->label('Taux IR(NC) (%)')
                                ->numeric()->default(0)->step(0.01)->suffix('%')
                                ->visible(fn(Get $get) => ($get('type_irnc') ?? 'taux') === 'taux')
                                ->live(onBlur: true),

                            Forms\Components\TextInput::make('montant_irnc')
                                ->label('Montant IR(NC) (forfait)')
                                ->numeric()->default(0)->prefix('FCFA')
                                ->visible(fn(Get $get) => ($get('type_irnc') ?? 'taux') === 'forfait')
                                ->live(onBlur: true),

                            Forms\Components\Placeholder::make('montant_irnc_calcule')
                                ->label('Montant IR(NC) calculé')
                                ->content(function (Get $get) {
                                    $ht   = static::getHt($get);
                                    if (($get('type_irnc') ?? 'taux') === 'forfait') {
                                        return number_format((float) ($get('montant_irnc') ?? 0), 0, ',', ' ') . ' FCFA';
                                    }
                                    $taux = (float) ($get('taux_irnc') ?? 0);
                                    return number_format($ht * $taux / 100, 0, ',', ' ') . ' FCFA';
                                })
                                ->visible(fn(Get $get) => ($get('type_irnc') ?? 'taux') === 'taux'),
                        ])->columnSpanFull(),

                        // ── Redevance audiovisuelle ────────────────────
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('type_redevance_audiovisuelle')
                                ->label('Type Redevance audiovisuelle')
                                ->options(['taux' => 'Taux (%)', 'forfait' => 'Forfait (FCFA)'])
                                ->default('forfait')->live()->required(),

                            Forms\Components\TextInput::make('taux_redevance_audiovisuelle')
                                ->label('Taux Redevance (%)')
                                ->numeric()->default(0)->step(0.01)->suffix('%')
                                ->visible(fn(Get $get) => $get('type_redevance_audiovisuelle') === 'taux')
                                ->live(onBlur: true),

                            Forms\Components\TextInput::make('montant_redevance_audiovisuelle')
                                ->label('Montant Redevance')
                                ->numeric()->default(0)->prefix('FCFA')
                                ->visible(fn(Get $get) => $get('type_redevance_audiovisuelle') === 'forfait')
                                ->live(onBlur: true),
                        ])->columnSpanFull(),

                        // ── FEICOM ─────────────────────────────────────
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('type_feicom')
                                ->label('Type FEICOM')
                                ->options(['taux' => 'Taux (%)', 'forfait' => 'Forfait (FCFA)'])
                                ->default('forfait')->live()->required(),

                            Forms\Components\TextInput::make('taux_feicom')
                                ->label('Taux FEICOM (%)')
                                ->numeric()->default(0)->step(0.01)->suffix('%')
                                ->visible(fn(Get $get) => $get('type_feicom') === 'taux')
                                ->live(onBlur: true),

                            Forms\Components\TextInput::make('montant_feicom')
                                ->label('Montant FEICOM')
                                ->numeric()->default(0)->prefix('FCFA')
                                ->visible(fn(Get $get) => $get('type_feicom') === 'forfait')
                                ->live(onBlur: true),
                        ])->columnSpanFull(),

                        Forms\Components\TextInput::make('autres_retenues')
                            ->label('Autres retenues')->numeric()->default(0)->prefix('FCFA')
                            ->live(onBlur: true)->columnSpanFull(),

                        // ── Résumé calculé ─────────────────────────────
                        Forms\Components\Placeholder::make('resume_montants')
                            ->label('📊 Résumé des montants')
                            ->content(function (Get $get) {
                                $brut    = (float) ($get('montant_brut') ?? 0);
                                if ($brut <= 0) return 'Veuillez saisir un montant brut';

                                $tauxTva = (float) ($get('taux_tva') ?? 19.25);
                                $ht      = static::getHt($get);
                                $tva     = $ht * ($tauxTva / 100);

                                $cnps    = $ht * ((float) ($get('taux_cnps') ?? 0) / 100);
                                $ir      = $ht * ((float) ($get('taux_ir')   ?? 0) / 100);

                                // IRNC
                                if (($get('type_irnc') ?? 'taux') === 'forfait') {
                                    $irnc = (float) ($get('montant_irnc') ?? 0);
                                } else {
                                    $irnc = $ht * ((float) ($get('taux_irnc') ?? 0) / 100);
                                }

                                // Redevance
                                $typeRed  = $get('type_redevance_audiovisuelle') ?? 'forfait';
                                $redevance = $typeRed === 'taux'
                                    ? $ht * ((float) ($get('taux_redevance_audiovisuelle') ?? 0) / 100)
                                    : (float) ($get('montant_redevance_audiovisuelle') ?? 0);

                                // FEICOM
                                $typeFei = $get('type_feicom') ?? 'forfait';
                                $feicom  = $typeFei === 'taux'
                                    ? $ht * ((float) ($get('taux_feicom') ?? 0) / 100)
                                    : (float) ($get('montant_feicom') ?? 0);

                                $autres = (float) ($get('autres_retenues') ?? 0);
                                $total  = $cnps + $ir + $irnc + $redevance + $feicom + $autres;
                                $net    = $ht - $total;

                                return collect([
                                    "💰 MONTANT BRUT (TTC) : " . number_format($brut, 0, ',', ' ') . " FCFA",
                                    "   Montant HT         : " . number_format($ht,   0, ',', ' ') . " FCFA",
                                    "   TVA ({$tauxTva}%)  : " . number_format($tva,  0, ',', ' ') . " FCFA",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "💸 RETENUES :",
                                    "   CNPS               : " . number_format($cnps,     0, ',', ' ') . " FCFA",
                                    "   IR                 : " . number_format($ir,       0, ',', ' ') . " FCFA",
                                    "   IR(NC)             : " . number_format($irnc,     0, ',', ' ') . " FCFA",
                                    "   Redevance AV       : " . number_format($redevance, 0, ',', ' ') . " FCFA",
                                    "   FEICOM             : " . number_format($feicom,   0, ',', ' ') . " FCFA",
                                    "   Autres             : " . number_format($autres,   0, ',', ' ') . " FCFA",
                                    "   TOTAL retenues     : " . number_format($total,    0, ',', ' ') . " FCFA",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "✅ NET À PAYER          : " . number_format($net, 0, ',', ' ') . " FCFA",
                                ])->implode("\n");
                            })
                            ->columnSpanFull(),

                    ])->hidden(fn(Get $get) => ($get('mode_saisie') ?? 'calcule') === 'forfait'),

                    // =================================================
                    // MODE FORFAITAIRE
                    // =================================================
                    Forms\Components\Group::make([

                        Forms\Components\Placeholder::make('_info_forfait')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="rounded-lg p-3 text-sm '
                                    . 'bg-yellow-50 dark:bg-yellow-900/30 '
                                    . 'text-yellow-800 dark:text-yellow-200 '
                                    . 'border border-yellow-300 dark:border-yellow-700">'
                                    . '<strong>⚠️ Mode forfaitaire</strong> — '
                                    . 'Tous les montants sont saisis directement, sans recalcul automatique.'
                                    . '</div>'
                            ))
                            ->columnSpanFull(),

                        // ── Montants principaux ────────────────────────
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('montant_brut')
                                ->label('Montant Brut (TTC)')
                                ->numeric()->required()->prefix('FCFA')
                                ->helperText('Montant total TTC'),

                            Forms\Components\TextInput::make('montant_ht')
                                ->label('Montant HT')
                                ->numeric()->prefix('FCFA')
                                ->helperText('Montant hors taxes'),

                            Forms\Components\TextInput::make('montant_tva')
                                ->label('Montant TVA')
                                ->numeric()->default(0)->prefix('FCFA'),
                        ])->columnSpanFull(),

                        // ── Retenues ───────────────────────────────────
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('montant_cnps')
                                ->label('Montant CNPS')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true),

                            // ✅ IR ET IRNC présents simultanément
                            Forms\Components\TextInput::make('montant_ir')
                                ->label('Montant IR')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true)
                                ->helperText('Impôt sur le Revenu standard'),

                            Forms\Components\TextInput::make('montant_irnc')
                                ->label('Montant IR(NC)')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true)
                                ->helperText('IR Non Commercial'),
                        ])->columnSpanFull(),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('montant_redevance_audiovisuelle')
                                ->label('Redevance audiovisuelle')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true),

                            Forms\Components\TextInput::make('montant_feicom')
                                ->label('Montant FEICOM')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true),

                            Forms\Components\TextInput::make('autres_retenues')
                                ->label('Autres retenues')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true),
                        ])->columnSpanFull(),

                        // ✅ Banque et Billetage — spécifiques au forfait
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('banque')
                                ->label('💳 Banque')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true)
                                ->helperText('Montant viré en banque'),

                            Forms\Components\TextInput::make('billetage')
                                ->label('💵 Billetage')
                                ->numeric()->default(0)->prefix('FCFA')->dehydrated(true)
                                ->helperText('Montant payé en espèces'),

                            Forms\Components\TextInput::make('montant_net')
                                ->label('Net à payer')
                                ->numeric()->required()->prefix('FCFA')->dehydrated(true)
                                ->helperText('Total = Banque + Billetage'),
                        ])->columnSpanFull(),

                        // ── Vérification forfait ───────────────────────
                        Forms\Components\Placeholder::make('_resume_forfait')
                            ->label('📊 Vérification')
                            ->content(function (Get $get) {
                                $brut    = (float) ($get('montant_brut')  ?? 0);
                                $ht      = (float) ($get('montant_ht')    ?? 0);
                                $tva     = (float) ($get('montant_tva')   ?? 0);
                                $cnps    = (float) ($get('montant_cnps')  ?? 0);
                                $ir      = (float) ($get('montant_ir')    ?? 0);
                                $irnc    = (float) ($get('montant_irnc')  ?? 0);
                                $red     = (float) ($get('montant_redevance_audiovisuelle') ?? 0);
                                $feicom  = (float) ($get('montant_feicom')    ?? 0);
                                $autres  = (float) ($get('autres_retenues')   ?? 0);
                                $banque  = (float) ($get('banque')            ?? 0);
                                $billet  = (float) ($get('billetage')         ?? 0);
                                $net     = (float) ($get('montant_net')       ?? 0);

                                $totalRet = $cnps + $ir + $irnc + $red + $feicom + $autres;
                                $netCalc  = $ht - $totalRet;
                                $somBanBil = $banque + $billet;

                                $ecartNet     = abs($net - $netCalc);
                                $ecartBanBil  = abs($net - $somBanBil);
                                $okNet        = $ecartNet < 1;
                                $okBanBil     = $somBanBil <= 0 || $ecartBanBil < 1;

                                return collect([
                                    "MONTANT BRUT (TTC)  : " . number_format($brut,    0, ',', ' ') . " FCFA",
                                    "Montant HT          : " . number_format($ht,      0, ',', ' ') . " FCFA",
                                    "TVA (forfait)       : " . number_format($tva,     0, ',', ' ') . " FCFA",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "💸 RETENUES :",
                                    "   CNPS             : " . number_format($cnps,    0, ',', ' ') . " FCFA",
                                    "   IR               : " . number_format($ir,      0, ',', ' ') . " FCFA",
                                    "   IR(NC)           : " . number_format($irnc,    0, ',', ' ') . " FCFA",
                                    "   Redevance AV     : " . number_format($red,     0, ',', ' ') . " FCFA",
                                    "   FEICOM           : " . number_format($feicom,  0, ',', ' ') . " FCFA",
                                    "   Autres           : " . number_format($autres,  0, ',', ' ') . " FCFA",
                                    "   TOTAL retenues   : " . number_format($totalRet, 0, ',', ' ') . " FCFA",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "Net calculé (HT-Ret): " . number_format($netCalc, 0, ',', ' ') . " FCFA",
                                    "Net saisi           : " . number_format($net,     0, ',', ' ') . " FCFA",
                                    $okNet ? "✅ Net OK" : "⚠️ Écart net : " . number_format($ecartNet, 0, ',', ' ') . " FCFA",
                                    "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
                                    "💳 Banque           : " . number_format($banque, 0, ',', ' ') . " FCFA",
                                    "💵 Billetage        : " . number_format($billet, 0, ',', ' ') . " FCFA",
                                    "   Banque + Billetage: " . number_format($somBanBil, 0, ',', ' ') . " FCFA",
                                    $okBanBil
                                        ? "✅ Banque + Billetage = Net à payer"
                                        : "⚠️ Écart Banque+Billetage vs Net : "
                                        . number_format($ecartBanBil, 0, ',', ' ') . " FCFA",
                                ])->implode("\n");
                            })
                            ->columnSpanFull(),

                    ])->hidden(fn(Get $get) => ($get('mode_saisie') ?? 'calcule') === 'calcule'),

                ])
                ->columns(2),

            Forms\Components\Section::make('Références')
                ->schema([
                    Forms\Components\TextInput::make('reference_decision')
                        ->label('Référence de la décision')->maxLength(255),
                    Forms\Components\TextInput::make('signataire')
                        ->label('Signataire')->maxLength(255),
                ])
                ->columns(2)->collapsible(),

            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')->rows(2)->columnSpanFull(),
                ])
                ->collapsible()->collapsed(),
        ]);
    }

    // =========================================================
    // HELPER — Montant HT depuis le brut et la TVA
    // =========================================================
    protected static function getHt(Get $get): float
    {
        $brut = (float) ($get('montant_brut') ?? 0);
        $taux = (float) ($get('taux_tva')     ?? 19.25);
        if ($brut <= 0) return 0;
        return $brut / (1 + $taux / 100);
    }

    // =========================================================
    // TABLE
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->filtersFormWidth(\Filament\Support\Enums\MaxWidth::Small)

            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° DA')->searchable()->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('typeDecision.libelle')
                    ->label('Type')->searchable()->sortable()->badge()->color('info'),

                Tables\Columns\TextColumn::make('memoire_numero')
                    ->label("Issu d'un MD")
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-document-text')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('type_beneficiaire')
                    ->label('Bénéficiaire')
                    ->formatStateUsing(function ($record) {
                        $nom  = $record->getNomCompletPersonnel();
                        $icon = match ($record->type_beneficiaire) {
                            'personnel'   => '👤',
                            'fournisseur' => '🏢',
                            default       => '',
                        };
                        return "{$icon} {$nom}";
                    })
                    ->description(fn($record) => match ($record->type_beneficiaire) {
                        'personnel'   => $record->personnel?->matricule ?? '',
                        'fournisseur' => $record->fournisseur?->numero_contribuable ?? '',
                        default       => '',
                    })
                    ->searchable(query: function (
                        \Illuminate\Database\Eloquent\Builder $query,
                        string $search
                    ) {
                        $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($search) {
                            $q->whereHas(
                                'personnel',
                                fn(\Illuminate\Database\Eloquent\Builder $p) =>
                                $p->where('nom', 'ilike', "%{$search}%")
                                    ->orWhere('prenoms', 'ilike', "%{$search}%")
                            )
                                ->orWhereHas(
                                    'fournisseur',
                                    fn(\Illuminate\Database\Eloquent\Builder $f) =>
                                    $f->where('raison_sociale', 'ilike', "%{$search}%")
                                );
                        });
                    })
                    ->sortable()->limit(30),

                Tables\Columns\TextColumn::make('date_decision')
                    ->label('Date')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('montant_brut')
                    ->label('Montant Brut')->money('XAF')->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Montant Net')->money('XAF')->sortable()->weight('bold')->color('success'),

                Tables\Columns\BadgeColumn::make('statut')
                    ->label('Statut')
                    ->colors([
                        'secondary' => 'brouillon',
                        'warning'   => 'validee',
                        'primary'   => 'engagee',
                        'info'      => 'ordonnancee',
                        'success'   => fn($state) => in_array($state, ['liquidee', 'payee']),
                        'danger'    => 'annulee',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'brouillon'    => 'Brouillon',
                        'validee'      => 'Validée',
                        'engagee'      => 'Engagée',
                        'ordonnancee'  => 'Ordonnancée',
                        'liquidee'     => 'Liquidée',
                        'payee'        => 'Payée',
                        'annulee'      => 'Annulée',
                        default        => $state,
                    }),

                Tables\Columns\IconColumn::make('engagee')
                    ->label('Engagée')->boolean()
                    ->trueColor('success')->falseColor('gray'),

                Tables\Columns\TextColumn::make('transmission_status')
                    ->label('Transmission')
                    ->getStateUsing(function ($record) {
                        // ✅ Utiliser la relation morphMany directement
                        $t = $record->transmissions()
                            ->where('statut', 'en_attente')
                            ->with('destinataire', 'expediteur')
                            ->latest('date_transmission')
                            ->first();

                        if (!$t) return null;

                        if ($t->destinataire_id === auth()->id()) {
                            return '🔔 À traiter — ' . $t->getActionLabel();
                        }
                        if ($t->expediteur_id === auth()->id()) {
                            return '📤 Chez ' . ($t->destinataire?->name ?? '?');
                        }
                        return '📋 Transmis à ' . ($t->destinataire?->name ?? '?');
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        str_starts_with($state ?? '', '🔔') => 'warning',
                        str_starts_with($state ?? '', '📤') => 'info',
                        str_starts_with($state ?? '', '📋') => 'gray',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(),

                // ✅ Ajouter aussi la colonne Historique
                Tables\Columns\TextColumn::make('nb_transmissions')
                    ->label('Historique')
                    ->getStateUsing(fn($record) => $record->transmissions()->count())
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'primary' : 'gray')
                    ->formatStateUsing(fn($state) => $state > 0 ? "{$state} transmission(s)" : '—')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                // ── Filtre par période prédéfinie ─────────────────────────
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\Select::make('periode')
                            ->label('Période prédéfinie')
                            ->options([
                                'today'        => "Aujourd'hui",
                                'yesterday'    => 'Hier',
                                'this_week'    => 'Cette semaine',
                                'last_week'    => 'Semaine dernière',
                                'this_month'   => 'Ce mois',
                                'last_month'   => 'Mois dernier',
                                'this_quarter' => 'Ce trimestre',
                                'last_quarter' => 'Trimestre dernier',
                                'this_year'    => 'Cette année',
                                'last_year'    => 'Année dernière',
                            ])
                            ->default('today')
                            ->placeholder('Sélectionner une période'),
                    ])
                    ->query(function ($query, array $data) {
                        $periode = $data['periode'] ?? null;
                        if (!$periode) return $query;

                        // ✅ Filtrer sur date_decision OU created_at
                        return match ($periode) {
                            'today' => $query->where(function ($q) {
                                $q->whereDate('date_decision', today())
                                    ->orWhereDate('created_at', today());
                            }),
                            'yesterday' => $query->where(function ($q) {
                                $q->whereDate('date_decision', today()->subDay())
                                    ->orWhereDate('created_at', today()->subDay());
                            }),
                            'this_week' => $query->where(function ($q) {
                                $q->whereBetween('date_decision', [now()->startOfWeek(), now()->endOfWeek()])
                                    ->orWhereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                            }),
                            'last_week' => $query->where(function ($q) {
                                $q->whereBetween('date_decision', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                                    ->orWhereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
                            }),
                            'this_month' => $query->where(function ($q) {
                                $q->where(function ($s) {
                                    $s->whereMonth('date_decision', now()->month)
                                        ->whereYear('date_decision', now()->year);
                                })
                                    ->orWhere(function ($s) {
                                        $s->whereMonth('created_at', now()->month)
                                            ->whereYear('created_at', now()->year);
                                    });
                            }),
                            'last_month' => $query->where(function ($q) {
                                $q->where(function ($s) {
                                    $s->whereMonth('date_decision', now()->subMonth()->month)
                                        ->whereYear('date_decision', now()->subMonth()->year);
                                })
                                    ->orWhere(function ($s) {
                                        $s->whereMonth('created_at', now()->subMonth()->month)
                                            ->whereYear('created_at', now()->subMonth()->year);
                                    });
                            }),
                            'this_quarter' => $query->where(function ($q) {
                                $q->whereBetween('date_decision', [now()->startOfQuarter(), now()->endOfQuarter()])
                                    ->orWhereBetween('created_at', [now()->startOfQuarter(), now()->endOfQuarter()]);
                            }),
                            'last_quarter' => $query->where(function ($q) {
                                $q->whereBetween('date_decision', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()])
                                    ->orWhereBetween('created_at', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]);
                            }),
                            'this_year' => $query->where(function ($q) {
                                $q->whereYear('date_decision', now()->year)
                                    ->orWhereYear('created_at', now()->year);
                            }),
                            'last_year' => $query->where(function ($q) {
                                $q->whereYear('date_decision', now()->subYear()->year)
                                    ->orWhereYear('created_at', now()->subYear()->year);
                            }),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['periode'] ?? null)) return null;
                        $labels = [
                            'today'        => "Aujourd'hui",
                            'yesterday'    => 'Hier',
                            'this_week'    => 'Cette semaine',
                            'last_week'    => 'Semaine dernière',
                            'this_month'   => 'Ce mois',
                            'last_month'   => 'Mois dernier',
                            'this_quarter' => 'Ce trimestre',
                            'last_quarter' => 'Trimestre dernier',
                            'this_year'    => 'Cette année',
                            'last_year'    => 'Année dernière',
                        ];
                        return 'Période : ' . ($labels[$data['periode']] ?? $data['periode']);
                    }),

                Tables\Filters\Filter::make('date_decision')
                    ->form([
                        Forms\Components\DatePicker::make('date_decision_from')
                            ->label('Date de décision du')
                            ->placeholder('JJ/MM/AAAA'),
                        Forms\Components\DatePicker::make('date_decision_until')
                            ->label('Date de décision au')
                            ->placeholder('JJ/MM/AAAA'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['date_decision_from'] ?? null,
                                fn(Builder $q, $date) => $q->whereDate('date_decision', '>=', $date)
                            )
                            ->when(
                                $data['date_decision_until'] ?? null,
                                fn(Builder $q, $date) => $q->whereDate('date_decision', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_decision_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make(
                                'Décision depuis le '
                                    . \Carbon\Carbon::parse($data['date_decision_from'])->format('d/m/Y')
                            )->removeField('date_decision_from');
                        }
                        if ($data['date_decision_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make(
                                'Décision jusqu\'au '
                                    . \Carbon\Carbon::parse($data['date_decision_until'])->format('d/m/Y')
                            )->removeField('date_decision_until');
                        }
                        return $indicators;
                    }),

                Tables\Filters\Filter::make('mes_decisions')
                    ->label('📌 Mes décisions actives')
                    ->query(function ($query) {
                        $userId = auth()->id();

                        return $query->where(function ($q) use ($userId) {

                            // Mes brouillons non transmis
                            $q->where(function ($subQ) use ($userId) {
                                $subQ->where('created_by', $userId)
                                    ->where('statut', 'brouillon')
                                    ->whereDoesntHave('transmissions', function ($t) {
                                        $t->where('statut', 'en_attente');
                                    });
                            })

                                // Transmis À MOI
                                ->orWhereHas('transmissions', function ($t) use ($userId) {
                                    $t->where('destinataire_id', $userId)
                                        ->where('statut', 'en_attente');
                                })

                                // Que j'ai transmis (suivi)
                                ->orWhereHas('transmissions', function ($t) use ($userId) {
                                    $t->where('expediteur_id', $userId)
                                        ->where('statut', 'en_attente');
                                });
                        });
                    })
                    ->toggle()
                    ->default(false)
                    ->indicateUsing(fn() => '📌 Mes décisions actives'),

                Tables\Filters\Filter::make('mes_transmissions')
                    ->label('📤 Mes transmissions envoyées')
                    ->query(function ($query) {
                        return $query->whereHas('transmissions', function ($t) {
                            $t->where('expediteur_id', auth()->id())
                                ->where('statut', 'en_attente');
                        });
                    })
                    ->toggle()
                    ->default(false)
                    ->indicateUsing(fn() => '📤 Transmissions envoyées en attente'),

                Tables\Filters\Filter::make('a_traiter')
                    ->label('📌 A traiter par moi')
                    ->query(function ($query) {
                        $userId = auth()->id();
                        return $query->where(function ($q) use ($userId) {

                            // 1. Mes brouillons non transmis
                            $q->where(function ($subQ) use ($userId) {
                                $subQ->where('created_by', $userId)
                                    ->where('statut', 'brouillon')
                                    ->whereDoesntHave('transmissions', function ($t) {
                                        $t->where('statut', 'en_attente');
                                    });
                            })

                                // 2. Transmis À MOI
                                ->orWhereHas('transmissions', function ($transmission) use ($userId) {
                                    $transmission->where('destinataire_id', $userId)
                                        ->where('statut', 'en_attente');
                                })

                                // 3. Retournés À MOI
                                ->orWhere(function ($subQ) use ($userId) {
                                    $subQ->where('created_by', $userId)
                                        ->whereHas('transmissions', function ($transmission) {
                                            $transmission->where('statut', 'retourne')
                                                ->latest()->limit(1);
                                        });
                                });
                        });
                    })
                    ->toggle()
                    ->default(true)
                    ->indicateUsing(fn() => '📌 Décisions nécessitant mon action'),


                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')->relationship('exercice', 'annee')
                    ->searchable()->preload()
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'brouillon'   => 'Brouillon',
                        'validee'     => 'Validée',
                        'engagee'     => 'Engagée',
                        'ordonnancee' => 'Ordonnancée',
                        'liquidee'    => 'Liquidée',
                        'payee'       => 'Payée',
                        'annulee'     => 'Annulée',
                    ]),

                Tables\Filters\TernaryFilter::make('engagee')
                    ->label('Engagée')->placeholder('Toutes')
                    ->trueLabel('Engagées')->falseLabel('Non engagées'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make(
                    array_merge(
                        WorkflowActions::make(avecEngagement: true),
                        [
                            // ════════════════════════════════════════════════════
                            // ✅ ANNULER LA TRANSFORMATION MD → DA
                            //
                            // Visible uniquement si :
                            //   - La DA provient d'un mémoire de dépense
                            //   - La DA est encore en brouillon (pas encore validée)
                            //   - L'utilisateur a la permission de supprimer une DA
                            //
                            // Action :
                            //   1. Supprime la DA
                            //   2. Réinitialise le MD → statut=brouillon
                            //      decision_administrative_id=null, numero_decision=null
                            // ════════════════════════════════════════════════════
                            Tables\Actions\Action::make('annuler_transformation')
                                ->label('Annuler la transformation')
                                ->icon('heroicon-o-arrow-uturn-left')
                                ->color('danger')
                                ->visible(function ($record) {
                                    // Chercher si un MD est lié à cette DA
                                    $memoireLie = \App\Models\MemoireDepense::where(
                                        'decision_administrative_id',
                                        $record->id
                                    )->exists();

                                    return $memoireLie
                                        && $record->statut === 'brouillon'
                                        && (auth()->user()?->can('annuler_transformation_decision_administrative') || auth()->user()?->can('delete_decision_administrative'));
                                })
                                ->modalHeading(fn($record) => 'Annuler la transformation — DA N° ' . $record->numero)
                                ->modalDescription(fn($record) => new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg p-3 mb-2 bg-red-50 dark:bg-red-900/20 '
                                        . 'border border-red-300 text-sm text-red-800 dark:text-red-200">'
                                        . '⚠️ <strong>Cette action va :</strong><br>'
                                        . '• Supprimer la DA <strong>' . $record->numero . '</strong><br>'
                                        . '• Remettre le Mémoire de Dépense lié en <strong>Brouillon</strong><br>'
                                        . '• Permettre la re-transformation ou modification du mémoire'
                                        . '</div>'
                                        . '<div class="rounded-lg p-3 bg-amber-50 dark:bg-amber-900/20 '
                                        . 'border border-amber-300 text-sm text-amber-800 dark:text-amber-200">'
                                        . '📋 Le mémoire pourra être modifié et retransformé en DA.'
                                        . '</div>'
                                ))
                                ->modalSubmitActionLabel('✅ Confirmer l\'annulation')
                                ->modalCancelActionLabel('Annuler')
                                ->requiresConfirmation()
                                ->action(function ($record) {
                                    try {
                                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {

                                            // ── 1. Trouver le mémoire lié ─────────────────
                                            $memoire = \App\Models\MemoireDepense::where(
                                                'decision_administrative_id',
                                                $record->id
                                            )->first();

                                            if (!$memoire) {
                                                throw new \Exception(
                                                    "Aucun mémoire de dépense lié à cette DA."
                                                );
                                            }

                                            $numeroDA = $record->numero;
                                            $numeroMD = $memoire->numero;

                                            // ── 2. Supprimer la DA ────────────────────────
                                            $record->delete();

                                            // ── 3. Réinitialiser le MD en brouillon ───────
                                            $memoire->update([
                                                'statut'                     => 'brouillon',
                                                'decision_administrative_id' => null,
                                                'numero_decision'            => null,
                                                'date_decision'              => null,
                                                'observations'               => ($memoire->observations ?? '')
                                                    . "\n\n--- TRANSFORMATION ANNULÉE LE "
                                                    . now()->format('d/m/Y H:i') . " ---\n"
                                                    . "DA supprimée : {$numeroDA}\n"
                                                    . "Par : " . auth()->user()->name,
                                            ]);

                                            \Illuminate\Support\Facades\Log::info(
                                                "Transformation MD→DA annulée",
                                                [
                                                    'da_numero' => $numeroDA,
                                                    'md_numero' => $numeroMD,
                                                    'user'      => auth()->id(),
                                                ]
                                            );
                                        });

                                        Notification::make()
                                            ->title('✅ Transformation annulée')
                                            ->success()
                                            ->body('La DA a été supprimée. Le mémoire est remis en brouillon.')
                                            ->send();
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->title('❌ Erreur')
                                            ->danger()
                                            ->body($e->getMessage())
                                            ->persistent()
                                            ->send();
                                    }
                                }),
                        ]
                    )
                )
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button()
                    ->size('sm'),
            ], position: ActionsPosition::BeforeColumns)
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
            'index'  => Pages\ListDecisionAdministratives::route('/'),
            'create' => Pages\CreateDecisionAdministrative::route('/create'),
            'edit'   => Pages\EditDecisionAdministrative::route('/{record}/edit'),
            'view'   => Pages\ViewDecisionAdministrative::route('/{record}'),
        ];
    }

    // =========================================================
    // MUTATE FORM DATA
    // =========================================================
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['mode_saisie'] ?? 'calcule') === 'forfait') {
            $data['taux_tva']  = 0;
            $data['taux_cnps'] = 0;
            $data['taux_ir']   = 0;
            $data['taux_irnc'] = 0;
            $data['taux_redevance_audiovisuelle'] = 0;
            $data['taux_feicom'] = 0;
            $data['type_tva']  = 'forfait';
            return $data;
        }

        // ✅ Mode calculé — calculer montant_ir depuis taux_ir
        $brut    = (float) ($data['montant_brut'] ?? 0);
        $tauxTva = (float) ($data['taux_tva'] ?? 19.25);
        $montantHT = $tauxTva > 0 ? $brut / (1 + $tauxTva / 100) : $brut;

        // ✅ Calculer montant_ir explicitement
        $tauxIr            = (float) ($data['taux_ir'] ?? 0);
        $data['montant_ir'] = round($montantHT * ($tauxIr / 100), 2);

        // Calculer montant_cnps
        $tauxCnps            = (float) ($data['taux_cnps'] ?? 0);
        $data['montant_cnps'] = round($montantHT * ($tauxCnps / 100), 2);

        // Valeurs par défaut
        foreach (
            [
                'taux_cnps',
                'taux_ir',
                'taux_irnc',
                'taux_tva',
                'taux_redevance_audiovisuelle',
                'taux_feicom',
                'montant_irnc',
                'montant_tva',
                'montant_redevance_audiovisuelle',
                'montant_feicom',
                'autres_retenues',
                'banque',
                'billetage',
            ] as $field
        ) {
            $data[$field] = $data[$field] ?? 0;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateFormDataBeforeCreate($data);
    }
}
