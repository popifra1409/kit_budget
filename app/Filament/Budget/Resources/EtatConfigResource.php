<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\EtatConfigResource\Pages;
use App\Models\EtatConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class EtatConfigResource extends Resource
{
    protected static ?string $model = EtatConfig::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Configuration États PDF';
    protected static ?string $modelLabel = 'État PDF';
    protected static ?string $pluralModelLabel = 'États PDF';
    protected static ?string $navigationGroup = 'Paramétrage';
    protected static ?int $navigationSort = 99;

    // ── Permissions ───────────────────────────────────────────
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_etat_config') ?? false;
    }
    public static function canView($r): bool
    {
        return auth()->user()?->can('view_etat_config') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_etat_config') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->user()?->can('update_etat_config'))
            return false;
        if (method_exists($record, 'estModifiable') && !$record->estModifiable()) {
            Notification::make()->title('Modification impossible')->warning()
                ->body('Cet état système ne peut pas être modifié.')->send();
            return false;
        }
        return true;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->user()?->can('delete_etat_config'))
            return false;
        if (method_exists($record, 'estSupprimable') && !$record->estSupprimable()) {
            Notification::make()->title('Suppression impossible')->warning()
                ->body('Cet état est utilisé par le système.')->send();
            return false;
        }
        return true;
    }

    // =========================================================
    // FORM
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code unique')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Ex: op_detaillee, ce_preimprime'),

                        Forms\Components\TextInput::make('nom')
                            ->label('Nom de la variante')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Ex: OP Détaillée avec décompte'),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        // ✅ Type de document — regroupe les variantes
                        Forms\Components\Select::make('type_document')
                            ->label('Type de document')
                            ->options([
                                // Budget
                                'certificat_engagement' => 'Certificat d\'Engagement',
                                'bon_commande' => 'Bon de Commande',
                                'autorisation_engagement' => 'Autorisation d\'Engagement',
                                'bordereau_engagement' => 'Bordereau d\'Engagement',
                                'memoire_depense' => 'Mémoire de Dépense',
                                'decision_administrative' => 'Décision Administrative',
                                'decision_previsionnelle' => 'Décision Prévisionnelle',
                                'ordonnance_paiement' => 'Ordonnance de Paiement',
                                'ordonnance_paiement_impot' => 'Ordonnance Paiement Impôt (OPT)',
                                // Comptabilité Matières
                                'pv_reception' => 'PV de Réception',
                                'ordre_entree' => 'Ordre d\'Entrée (OE)',
                                'bon_sortie_provisoire' => 'Bon de Sortie Provisoire (BSP)',
                                'ordre_sortie' => 'Ordre de Sortie (OS)',
                                'fiche_detenteur' => 'Fiche de Détenteur',
                            ])
                            ->required()
                            ->searchable()
                            ->live()
                            ->helperText('Regroupe toutes les variantes du même document'),

                        Forms\Components\Select::make('categorie')
                            ->label('Catégorie')
                            ->options([
                                'Budgétaire' => 'Budgétaire',
                                'Commercial' => 'Commercial',
                                'Administratif' => 'Administratif',
                                'Paiement' => 'Paiement',
                                'Comptabilité Matières' => 'Comptabilité Matières',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre dans la liste')
                            ->numeric()
                            ->default(1)
                            ->helperText('1 = premier dans le Select'),
                    ]),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(3)->schema([
                        // ✅ Variante par défaut
                        Forms\Components\Toggle::make('est_defaut')
                            ->label('⭐ Variante par défaut')
                            ->helperText('Utilisée si aucune variante n\'est choisie')
                            ->default(false)
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(function ($state, $get, $set) {
                                // Info si déjà une variante par défaut
                                if ($state) {
                                    $typeDoc = $get('type_document');
                                    if ($typeDoc) {
                                        $existante = EtatConfig::where('type_document', $typeDoc)
                                            ->where('est_defaut', true)
                                            ->where('code', '!=', $get('code') ?? '')
                                            ->first();
                                        if ($existante) {
                                            Notification::make()
                                                ->title('⚠️ Attention')
                                                ->warning()
                                                ->body("La variante \"{$existante->nom}\" est actuellement la par défaut. Sauvegarder remplacera cette configuration.")
                                                ->send();
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Toggle::make('actif')
                            ->label('État actif')
                            ->default(true)
                            ->inline(false),

                        // Aperçu des variantes existantes
                        Forms\Components\Placeholder::make('variantes_existantes')
                            ->label('Variantes existantes pour ce type')
                            ->content(function (Get $get) {
                                $typeDoc = $get('type_document');
                                if (!$typeDoc)
                                    return 'Sélectionnez un type de document';

                                $variantes = EtatConfig::where('type_document', $typeDoc)
                                    ->orderBy('est_defaut', 'desc')
                                    ->orderBy('ordre')
                                    ->get();

                                if ($variantes->isEmpty())
                                    return 'Aucune variante — ce sera la première';

                                $html = '<ul style="list-style:none;padding:0;margin:0;font-size:.8rem;">';
                                foreach ($variantes as $v) {
                                    $badge = $v->est_defaut ? ' <span style="color:#d97706;">⭐</span>' : '';
                                    $actif = $v->actif ? '🟢' : '🔴';
                                    $html .= "<li style='padding:2px 0;'>{$actif} [{$v->code}] {$v->nom}{$badge}</li>";
                                }
                                $html .= '</ul>';

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ]),
                ])
                ->columns(1),

            Forms\Components\Section::make('Template')
                ->schema([
                    Forms\Components\TextInput::make('template')
                        ->label('Template Blade')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Ex: pdf.templates.ordonnance-paiement-detaillee')
                        ->placeholder('pdf.templates.mon-template'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('orientation')
                            ->label('Orientation')
                            ->options(['portrait' => 'Portrait', 'landscape' => 'Paysage'])
                            ->default('portrait'),

                        Forms\Components\Select::make('format_papier')
                            ->label('Format papier')
                            ->options(['A4' => 'A4', 'A3' => 'A3', 'Letter' => 'Letter'])
                            ->default('A4'),
                    ]),
                ])
                ->columns(1),

            Forms\Components\Section::make('Champs & Calculs')
                ->schema([
                    Forms\Components\Textarea::make('champs_variables')
                        ->label('Champs variables (JSON)')
                        ->rows(12)
                        ->helperText('Format JSON — chaque champ avec source et type')
                        ->formatStateUsing(
                            fn($state) =>
                            $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}'
                        )
                        ->dehydrateStateUsing(
                            fn($state) =>
                            json_decode($state, true) ?? []
                        )
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('calculs')
                        ->label('Calculs automatiques (JSON)')
                        ->rows(5)
                        ->helperText('Ex: {"montant_lettres": {"fonction": "nombre_en_lettres", "params": ["_raw.montant_total"]}}')
                        ->formatStateUsing(
                            fn($state) =>
                            $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}'
                        )
                        ->dehydrateStateUsing(
                            fn($state) =>
                            json_decode($state, true) ?? []
                        )
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Signatures & Options PDF')
                ->schema([
                    Forms\Components\Tabs::make('config_visuelle')->tabs([
                        Forms\Components\Tabs\Tab::make('Signatures')
                            ->schema([
                                Forms\Components\Textarea::make('signature_config')
                                    ->label('Configuration signatures (JSON)')
                                    ->rows(6)
                                    ->formatStateUsing(
                                        fn($state) =>
                                        $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}'
                                    )
                                    ->dehydrateStateUsing(
                                        fn($state) =>
                                        json_decode($state, true) ?? []
                                    ),
                            ]),
                        Forms\Components\Tabs\Tab::make('Options PDF')
                            ->schema([
                                Forms\Components\Textarea::make('options_pdf')
                                    ->label('Options PDF (JSON)')
                                    ->rows(4)
                                    ->formatStateUsing(
                                        fn($state) =>
                                        $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}'
                                    )
                                    ->dehydrateStateUsing(
                                        fn($state) =>
                                        json_decode($state, true) ?? []
                                    ),
                            ]),
                        Forms\Components\Tabs\Tab::make('En-tête')
                            ->schema([
                                Forms\Components\Textarea::make('entete_config')
                                    ->label('Configuration en-tête (JSON)')
                                    ->rows(4)
                                    ->formatStateUsing(
                                        fn($state) =>
                                        $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}'
                                    )
                                    ->dehydrateStateUsing(
                                        fn($state) =>
                                        json_decode($state, true) ?? []
                                    ),
                            ]),
                    ]),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    // =========================================================
    // TABLE
    // =========================================================
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type_document')
                    ->label('Type document')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code variante')
                    ->searchable()->sortable()->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()->limit(35),

                Tables\Columns\BadgeColumn::make('categorie')
                    ->label('Catégorie')
                    ->colors([
                        'primary' => 'Budgétaire',
                        'success' => 'Commercial',
                        'warning' => 'Comptabilité Matières',
                        'info' => 'Paiement',
                        'danger' => 'Administratif',
                    ]),

                // ✅ Variante par défaut
                Tables\Columns\IconColumn::make('est_defaut')
                    ->label('⭐ Défaut')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('type_document')
            ->groupingSettingsHidden()
            ->groups([
                Tables\Grouping\Group::make('type_document')
                    ->label('Type de document')
                    ->collapsible(),
            ])
            ->defaultGroup('type_document')
            ->filters([
                Tables\Filters\SelectFilter::make('type_document')
                    ->label('Type de document')
                    ->options(
                        EtatConfig::distinct()->pluck('type_document', 'type_document')
                            ->filter()->toArray()
                    )
                    ->searchable(),

                Tables\Filters\SelectFilter::make('categorie')
                    ->options([
                        'Budgétaire' => 'Budgétaire',
                        'Commercial' => 'Commercial',
                        'Administratif' => 'Administratif',
                        'Paiement' => 'Paiement',
                        'Comptabilité Matières' => 'Comptabilité Matières',
                    ]),

                Tables\Filters\TernaryFilter::make('est_defaut')->label('Variante par défaut'),
                Tables\Filters\TernaryFilter::make('actif')->label('Actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // ✅ Définir comme variante par défaut
                Tables\Actions\Action::make('set_defaut')
                    ->label('Définir par défaut')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn($record) => !$record->est_defaut)
                    ->requiresConfirmation()
                    ->modalHeading('Définir comme variante par défaut')
                    ->modalDescription(
                        fn($record) =>
                        "La variante \"{$record->nom}\" sera utilisée par défaut pour le type \"{$record->type_document}\"."
                    )
                    ->action(function ($record) {
                        // Retirer le défaut des autres variantes du même type
                        EtatConfig::where('type_document', $record->type_document)
                            ->where('id', '!=', $record->id)
                            ->update(['est_defaut' => false]);

                        // Définir cette variante comme défaut
                        $record->update(['est_defaut' => true]);

                        Notification::make()
                            ->title('⭐ Variante par défaut mise à jour')
                            ->success()
                            ->body("\"{$record->nom}\" est maintenant la variante par défaut pour \"{$record->type_document}\".")
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->estSupprimable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // =========================================================
    // BOOT — s'assurer qu'il n'y a qu'un seul défaut par type
    // =========================================================
    public static function saving($record): void
    {
        if ($record->est_defaut) {
            EtatConfig::where('type_document', $record->type_document)
                ->where('id', '!=', $record->id)
                ->update(['est_defaut' => false]);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEtatConfigs::route('/'),
            'create' => Pages\CreateEtatConfig::route('/create'),
            'edit' => Pages\EditEtatConfig::route('/{record}/edit'),
        ];
    }
}