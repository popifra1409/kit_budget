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
use Filament\Tables\Enums\ActionsPosition;
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
        if (!auth()->user()?->can('update_etat_config')) return false;
        if (method_exists($record, 'estModifiable') && !$record->estModifiable()) {
            Notification::make()->title('Modification impossible')->warning()
                ->body('Cet état système ne peut pas être modifié.')->send();
            return false;
        }
        return true;
    }

    public static function canDelete($record): bool
    {
        if (!auth()->user()?->can('delete_etat_config')) return false;
        if (method_exists($record, 'estSupprimable') && !$record->estSupprimable()) {
            Notification::make()->title('Suppression impossible')->warning()
                ->body('Cet état est utilisé par le système.')->send();
            return false;
        }
        return true;
    }

    // =========================================================
    // FORM — identique à l'original
    // =========================================================
    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code unique')->required()->unique(ignoreRecord: true)->maxLength(255)
                            ->helperText('Ex: op_detaillee, ce_preimprime'),
                        Forms\Components\TextInput::make('nom')
                            ->label('Nom de la variante')->required()->maxLength(255)
                            ->helperText('Ex: OP Détaillée avec décompte'),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('type_document')
                            ->label('Type de document')
                            ->options([
                                'certificat_engagement'    => 'Certificat d\'Engagement',
                                'bon_commande'             => 'Bon de Commande',
                                'bon_commande_regie'       => 'Bon de Commande Régie (BCR/BCM)',
                                'autorisation_engagement'  => 'Autorisation d\'Engagement',
                                'bordereau_engagement'     => 'Bordereau d\'Engagement',
                                'memoire_depense'          => 'Mémoire de Dépense',
                                'decision_administrative'  => 'Décision Administrative',
                                'decision_previsionnelle'  => 'Décision Prévisionnelle',
                                'ordonnance_paiement'      => 'Ordonnance de Paiement',
                                'ordonnance_paiement_impot' => 'Ordonnance Paiement Impôt (OPT)',
                                'pv_reception'             => 'PV de Réception',
                                'ordre_entree'             => 'Ordre d\'Entrée (OE)',
                                'bon_sortie_provisoire'    => 'Bon de Sortie Provisoire (BSP)',
                                'ordre_sortie'             => 'Ordre de Sortie (OS)',
                                'fiche_detenteur'          => 'Fiche de Détenteur',
                            ])
                            ->required()->searchable()->live()
                            ->helperText('Regroupe toutes les variantes du même document'),

                        Forms\Components\Select::make('categorie')
                            ->label('Catégorie')
                            ->options([
                                'Budgétaire'            => 'Budgétaire',
                                'Commercial'            => 'Commercial',
                                'Administratif'         => 'Administratif',
                                'Paiement'              => 'Paiement',
                                'Comptabilité Matières' => 'Comptabilité Matières',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('ordre')
                            ->label('Ordre dans la liste')->numeric()->default(1)
                            ->helperText('1 = premier dans le Select'),
                    ]),

                    Forms\Components\Textarea::make('description')->label('Description')->rows(2)->columnSpanFull(),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('est_defaut')
                            ->label('⭐ Variante par défaut')
                            ->helperText('Utilisée si aucune variante n\'est choisie')
                            ->default(false)->inline(false)->live()
                            ->afterStateUpdated(function ($state, $get, $set) {
                                if ($state) {
                                    $typeDoc = $get('type_document');
                                    if ($typeDoc) {
                                        $existante = EtatConfig::where('type_document', $typeDoc)
                                            ->where('est_defaut', true)
                                            ->where('code', '!=', $get('code') ?? '')->first();
                                        if ($existante) {
                                            Notification::make()->title('⚠️ Attention')->warning()
                                                ->body("La variante \"{$existante->nom}\" est actuellement la par défaut. Sauvegarder remplacera cette configuration.")
                                                ->send();
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Toggle::make('actif')->label('État actif')->default(true)->inline(false),

                        Forms\Components\Placeholder::make('variantes_existantes')
                            ->label('Variantes existantes pour ce type')
                            ->content(function (Get $get) {
                                $typeDoc = $get('type_document');
                                if (!$typeDoc) return 'Sélectionnez un type de document';
                                $variantes = EtatConfig::where('type_document', $typeDoc)
                                    ->orderBy('est_defaut', 'desc')->orderBy('ordre')->get();
                                if ($variantes->isEmpty()) return 'Aucune variante — ce sera la première';
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
                        ->label('Template Blade')->required()->maxLength(255)
                        ->helperText('Ex: pdf.templates.ordonnance-paiement-detaillee')
                        ->placeholder('pdf.templates.mon-template'),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('orientation')
                            ->label('Orientation')
                            ->options(['portrait' => 'Portrait', 'landscape' => 'Paysage'])->default('portrait'),
                        Forms\Components\Select::make('format_papier')
                            ->label('Format papier')
                            ->options(['A4' => 'A4', 'A3' => 'A3', 'Letter' => 'Letter'])->default('A4'),
                    ]),
                ])
                ->columns(1),

            Forms\Components\Section::make('Champs & Calculs')
                ->schema([
                    Forms\Components\Textarea::make('champs_variables')
                        ->label('Champs variables (JSON)')->rows(12)
                        ->helperText('Format JSON — chaque champ avec source et type')
                        ->formatStateUsing(fn($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}')
                        ->dehydrateStateUsing(fn($state) => json_decode($state, true) ?? [])
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('calculs')
                        ->label('Calculs automatiques (JSON)')->rows(5)
                        ->helperText('Ex: {"montant_lettres": {"fonction": "nombre_en_lettres", "params": ["_raw.montant_total"]}}')
                        ->formatStateUsing(fn($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}')
                        ->dehydrateStateUsing(fn($state) => json_decode($state, true) ?? [])
                        ->columnSpanFull(),
                ])
                ->collapsible()->collapsed(),

            Forms\Components\Section::make('Signatures & Options PDF')
                ->schema([
                    Forms\Components\Tabs::make('config_visuelle')->tabs([
                        Forms\Components\Tabs\Tab::make('Signatures')->schema([
                            Forms\Components\Textarea::make('signature_config')
                                ->label('Configuration signatures (JSON)')->rows(6)
                                ->formatStateUsing(fn($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}')
                                ->dehydrateStateUsing(fn($state) => json_decode($state, true) ?? []),
                        ]),
                        Forms\Components\Tabs\Tab::make('Options PDF')->schema([
                            Forms\Components\Textarea::make('options_pdf')
                                ->label('Options PDF (JSON)')->rows(4)
                                ->formatStateUsing(fn($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}')
                                ->dehydrateStateUsing(fn($state) => json_decode($state, true) ?? []),
                        ]),
                        Forms\Components\Tabs\Tab::make('En-tête')->icon('heroicon-o-building-office')->schema([
                            Forms\Components\Placeholder::make('info_entete')->label('')->content(new \Illuminate\Support\HtmlString(
                                '<div style="background:#fef9c3;border:1px solid #ca8a04;border-radius:.5rem;padding:.6rem .75rem;font-size:.82rem;">
                                💡 Laissez vide pour utiliser les valeurs de <strong>ParametresStructure</strong>.
                                Seuls les champs renseignés ici remplaceront les valeurs par défaut.</div>'
                            ))->columnSpanFull(),

                            Forms\Components\TextInput::make('entete_config.titre_document')
                                ->label('Titre du document (centre)')->live(onBlur: true)
                                ->placeholder('Ex: BON DE COMMANDE RÉGIE D\'AVANCE')
                                ->helperText('Remplace le titre affiché au centre du PDF')->columnSpanFull(),

                            Forms\Components\Placeholder::make('logo_structure_info')
                                ->label('Logo de la structure (ParametresStructure)')
                                ->content(function () {
                                    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
                                    if (!$parametres?->logo) {
                                        return new \Illuminate\Support\HtmlString('<div style="background:#fef9c3;border:1px solid #ca8a04;border-radius:.5rem;padding:.6rem .75rem;font-size:.8rem;color:#854d0e;">⚠️ Aucun logo défini dans ParametresStructure.</div>');
                                    }
                                    $logoPath = storage_path('app/public/' . ltrim($parametres->logo, '/'));
                                    if (!file_exists($logoPath)) {
                                        return new \Illuminate\Support\HtmlString('<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:.5rem;padding:.6rem .75rem;font-size:.8rem;color:#991b1b;">⚠️ Fichier logo introuvable : ' . e($parametres->logo) . '</div>');
                                    }
                                    $logoB64  = base64_encode(file_get_contents($logoPath));
                                    $mimeType = mime_content_type($logoPath) ?: 'image/png';
                                    return new \Illuminate\Support\HtmlString(
                                        '<div style="display:flex;align-items:center;gap:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:.5rem;padding:.75rem;">
                                        <img src="data:' . $mimeType . ';base64,' . $logoB64 . '" style="height:50px;border:1px solid #e2e8f0;border-radius:.375rem;padding:4px;background:#fff;">
                                        <div style="font-size:.78rem;color:#64748b;line-height:1.5;">Ce logo sera affiché automatiquement sous le titre de l\'institution, en mode entête texte — uniquement si <strong>aucun logo alternatif</strong> n\'est défini ci-dessous.</div>
                                        </div>'
                                    );
                                })->columnSpanFull(),

                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('entete_config.titre_fr')->label('Nom institution (FR)')->live(onBlur: true)->placeholder('Laisser vide = ParametresStructure')->helperText('Ex: CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE'),
                                Forms\Components\TextInput::make('entete_config.titre_en')->label('Nom institution (EN)')->live(onBlur: true)->placeholder('Laisser vide = ParametresStructure'),
                                Forms\Components\TextInput::make('entete_config.ministere_fr')->label('Ministère (FR)')->live(onBlur: true)->placeholder('Ex: MINISTERE DE LA SANTE PUBLIQUE'),
                                Forms\Components\TextInput::make('entete_config.ministere_en')->label('Ministère (EN)')->live(onBlur: true)->placeholder('Ex: MINISTRY OF PUBLIC HEALTH'),
                                Forms\Components\TextInput::make('entete_config.sous_direction_fr')->label('Sous-direction (FR)')->live(onBlur: true)->placeholder('Ex: DIRECTION DES RESSOURCES HUMAINES ET FINANCIÈRES'),
                                Forms\Components\TextInput::make('entete_config.sous_direction_en')->label('Sous-direction (EN)')->live(onBlur: true)->placeholder('Ex: HUMAN RESOURCES AND FINANCIAL DIRECTORATE'),
                                Forms\Components\TextInput::make('entete_config.sigle')->label('Sigle institution')->live(onBlur: true)->placeholder('Laisser vide = ParametresStructure')->helperText('Ex: CHUY'),
                                Forms\Components\Placeholder::make('apercu_logo')->label('Logo actuel')
                                    ->content(function ($record) {
                                        if (!$record) return new \Illuminate\Support\HtmlString('<span style="color:#94a3b8;font-size:.8rem;">Aucun logo — sauvegardez d\'abord.</span>');
                                        $logo = $record->entete_config['logo_override'] ?? null;
                                        if (!$logo) return new \Illuminate\Support\HtmlString('<span style="color:#94a3b8;font-size:.8rem;">Aucun logo alternatif défini.</span>');
                                        $url = asset('storage/' . $logo);
                                        return new \Illuminate\Support\HtmlString("<div style='display:flex;align-items:center;gap:1rem;'><img src='{$url}' style='max-height:60px;border:1px solid #e2e8f0;border-radius:.375rem;padding:4px;'><span style='font-size:.75rem;color:#64748b;'>{$logo}</span></div>");
                                    })->columnSpanFull(),
                                Forms\Components\Hidden::make('entete_config.logo_override'),
                            ]),

                            Forms\Components\Placeholder::make('apercu_entete_json')->label('Aperçu JSON généré')
                                ->content(function (Forms\Get $get) {
                                    $config = array_filter([
                                        'titre_document'    => $get('entete_config.titre_document'),
                                        'titre_fr'          => $get('entete_config.titre_fr'),
                                        'titre_en'          => $get('entete_config.titre_en'),
                                        'ministere_fr'      => $get('entete_config.ministere_fr'),
                                        'ministere_en'      => $get('entete_config.ministere_en'),
                                        'sous_direction_fr' => $get('entete_config.sous_direction_fr'),
                                        'sous_direction_en' => $get('entete_config.sous_direction_en'),
                                        'sigle'             => $get('entete_config.sigle'),
                                        'logo_override'     => $get('entete_config.logo_override'),
                                    ], fn($v) => !is_null($v) && $v !== '');
                                    if (empty($config)) return new \Illuminate\Support\HtmlString('<span style="color:#94a3b8;font-size:.8rem;">Tout sera pris depuis ParametresStructure</span>');
                                    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    return new \Illuminate\Support\HtmlString('<pre style="background:#1e293b;color:#e2e8f0;padding:.75rem;border-radius:.5rem;font-size:.72rem;overflow-x:auto;">' . htmlspecialchars($json) . '</pre>');
                                })->columnSpanFull(),

                            Forms\Components\Placeholder::make('apercu_entete_texte')
                                ->label('Aperçu — Mode entête texte')
                                ->content(function (Forms\Get $get) {
                                    $parametres  = \App\Models\ParametresStructure::where('actif', true)->first();
                                    $titreFr     = $get('entete_config.titre_fr') ?: ($parametres?->nom_complet ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE');
                                    $titreEn     = $get('entete_config.titre_en') ?: ($parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL');
                                    $sousDirFr   = $get('entete_config.sous_direction_fr');
                                    $sousDirEn   = $get('entete_config.sous_direction_en');
                                    $ministereFr = $get('entete_config.ministere_fr') ?: 'MINISTERE DE LA SANTE PUBLIQUE';
                                    $ministereEn = $get('entete_config.ministere_en') ?: 'MINISTRY OF PUBLIC HEALTH';
                                    $titreDocument = $get('entete_config.titre_document') ?: 'BON DE COMMANDE ADMINISTRATIF';
                                    $logoHtml = '';
                                    if ($parametres?->logo) {
                                        $logoPath = storage_path('app/public/' . ltrim($parametres->logo, '/'));
                                        if (file_exists($logoPath)) {
                                            $logoB64  = base64_encode(file_get_contents($logoPath));
                                            $mimeType = mime_content_type($logoPath) ?: 'image/png';
                                            $logoHtml = '<div style="text-align:center;margin-top:4px;margin-bottom:3px;"><img src="data:' . $mimeType . ';base64,' . $logoB64 . '" style="height:42px;display:inline-block;"></div>';
                                        }
                                    }
                                    $sousDirHtml = '';
                                    if ($sousDirFr) {
                                        $sousDirHtml = "<div style='font-size:7.5pt;margin-top:2px;'>" . e($sousDirFr);
                                        if ($sousDirEn) $sousDirHtml .= "<br><em style='font-size:7pt;'>" . e($sousDirEn) . "</em>";
                                        $sousDirHtml .= "</div>";
                                    }
                                    return new \Illuminate\Support\HtmlString('<div style="border:1px solid #e2e8f0;border-radius:.5rem;padding:1rem;background:#fff;"><table style="width:100%;border-collapse:collapse;border:none;"><tr><td style="width:22%;vertical-align:top;text-align:center;font-size:7.5pt;border:none;"><strong>REPUBLIQUE DU CAMEROUN</strong><br><em>Paix - Travail - Patrie</em><br><span style="font-size:6.5pt;">' . e($ministereFr) . '</span></td><td style="width:56%;text-align:center;vertical-align:top;border:none;"><div style="font-size:10pt;font-weight:bold;text-transform:uppercase;">' . e($titreFr) . '</div><div style="font-size:8pt;font-style:italic;">' . e($titreEn) . '</div>' . $logoHtml . $sousDirHtml . '<div style="font-size:8.5pt;font-weight:bold;margin-top:6px;text-transform:uppercase;border:1px solid #000;padding:3px;">' . e($titreDocument) . '</div></td><td style="width:22%;vertical-align:top;text-align:center;font-size:7.5pt;border:none;"><strong>REPUBLIC OF CAMEROON</strong><br><em>Peace - Work - Fatherland</em><br><span style="font-size:6.5pt;">' . e($ministereEn) . '</span></td></tr></table></div>');
                                })->live()->columnSpanFull(),
                        ]),
                    ]),
                ])
                ->collapsible()->collapsed(),
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
                    ->label('Type document')->sortable()->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code variante')->searchable()->sortable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')->searchable()->limit(35),
                Tables\Columns\BadgeColumn::make('categorie')
                    ->label('Catégorie')
                    ->colors([
                        'primary' => 'Budgétaire',
                        'success' => 'Commercial',
                        'warning' => 'Comptabilité Matières',
                        'info'    => 'Paiement',
                        'danger'  => 'Administratif',
                    ]),
                Tables\Columns\IconColumn::make('est_defaut')
                    ->label('⭐ Défaut')->boolean()
                    ->trueIcon('heroicon-o-star')->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')->falseColor('gray')->alignCenter(),
                Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean()->alignCenter(),
                Tables\Columns\TextColumn::make('ordre')->label('Ordre')->sortable()->alignCenter(),
            ])
            ->defaultSort('type_document')
            ->groupingSettingsHidden()
            ->groups([
                Tables\Grouping\Group::make('type_document')->label('Type de document')->collapsible(),
            ])
            ->defaultGroup('type_document')
            ->filters([
                Tables\Filters\SelectFilter::make('type_document')
                    ->label('Type de document')
                    ->options(EtatConfig::distinct()->pluck('type_document', 'type_document')->filter()->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('categorie')
                    ->options([
                        'Budgétaire'            => 'Budgétaire',
                        'Commercial'            => 'Commercial',
                        'Administratif'         => 'Administratif',
                        'Paiement'              => 'Paiement',
                        'Comptabilité Matières' => 'Comptabilité Matières',
                    ]),
                Tables\Filters\TernaryFilter::make('est_defaut')->label('Variante par défaut'),
                Tables\Filters\TernaryFilter::make('actif')->label('Actif'),
            ])

            // ════════════════════════════════════════════════════════
            // ✅ ACTIONS — un seul ActionGroup, aligné à gauche
            //    Pattern identique à BonCommandeResource
            // ════════════════════════════════════════════════════════
            ->actions([
                Tables\Actions\ActionGroup::make([

                    Tables\Actions\EditAction::make(),

                    // ── Gérer le logo ─────────────────────────────
                    Tables\Actions\Action::make('gerer_logo')
                        ->label('🖼 Gérer le logo')
                        ->icon('heroicon-o-photo')->color('info')
                        ->visible(fn($record) => $record->type_document !== null)
                        ->form(function ($record) {
                            $logo    = $record->entete_config['logo_override'] ?? null;
                            $hasLogo = $logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo);
                            return [
                                Forms\Components\Placeholder::make('logo_actuel')->label('Logo actuel')
                                    ->content(function () use ($logo, $hasLogo) {
                                        if (!$logo || !$hasLogo) return new \Illuminate\Support\HtmlString('<div style="padding:.75rem;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:.5rem;text-align:center;color:#94a3b8;font-size:.82rem;">Aucun logo alternatif défini</div>');
                                        $url = asset('storage/' . $logo);
                                        return new \Illuminate\Support\HtmlString("<div style='text-align:center;padding:.5rem;'><img src='{$url}?v=" . time() . "' style='max-height:80px;max-width:100%;border:1px solid #e2e8f0;border-radius:.375rem;padding:4px;'></div>");
                                    })->columnSpanFull(),

                                Forms\Components\Radio::make('action_logo')->label('Action')
                                    ->options(array_filter([
                                        'remplacer' => '📤 Charger un nouveau logo',
                                        'supprimer' => $hasLogo ? '🗑️ Supprimer le logo actuel' : null,
                                        'rien'      => '↩ Ne rien changer',
                                    ]))
                                    ->default('remplacer')->live()->columnSpanFull(),

                                Forms\Components\FileUpload::make('nouveau_logo')->label('Nouveau logo')
                                    ->image()->disk('public')->directory('logos/etats')->maxSize(3072)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imagePreviewHeight('80')
                                    ->helperText('JPG/PNG/WEBP — max 3 Mo.')
                                    ->visible(fn(Forms\Get $get) => $get('action_logo') === 'remplacer')
                                    ->required(fn(Forms\Get $get) => $get('action_logo') === 'remplacer')
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('confirm_suppression')->label('')
                                    ->content(new \Illuminate\Support\HtmlString('<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:.5rem;padding:.75rem;color:#991b1b;font-size:.82rem;">⚠️ Le logo sera supprimé. L\'en-tête reviendra au mode texte institutionnel.</div>'))
                                    ->visible(fn(Forms\Get $get) => $get('action_logo') === 'supprimer')
                                    ->columnSpanFull(),
                            ];
                        })
                        ->modalHeading(fn($record) => "Logo — {$record->nom}")
                        ->modalWidth('lg')
                        ->action(function ($record, array $data) {
                            $action = $data['action_logo'] ?? 'rien';
                            if ($action === 'rien') return;
                            $entete = $record->entete_config ?? [];
                            if ($action === 'supprimer') {
                                $ancienLogo = $entete['logo_override'] ?? null;
                                if ($ancienLogo) \Illuminate\Support\Facades\Storage::disk('public')->delete($ancienLogo);
                                unset($entete['logo_override']);
                                $record->update(['entete_config' => $entete]);
                                Notification::make()->title('🗑️ Logo supprimé')->success()->body('L\'en-tête utilisera le mode texte institutionnel.')->send();
                                return;
                            }
                            if ($action === 'remplacer' && !empty($data['nouveau_logo'])) {
                                $ancienLogo = $entete['logo_override'] ?? null;
                                if ($ancienLogo && $ancienLogo !== $data['nouveau_logo']) \Illuminate\Support\Facades\Storage::disk('public')->delete($ancienLogo);
                                $entete['logo_override'] = $data['nouveau_logo'];
                                $record->update(['entete_config' => $entete]);
                                Notification::make()->title('✅ Logo mis à jour')->success()->body('Logo chargé avec succès.')->send();
                            }
                        }),

                    // ── Définir par défaut ────────────────────────
                    Tables\Actions\Action::make('set_defaut')
                        ->label('Définir par défaut')
                        ->icon('heroicon-o-star')->color('warning')
                        ->visible(fn($record) => !$record->est_defaut)
                        ->requiresConfirmation()
                        ->modalHeading('Définir comme variante par défaut')
                        ->modalDescription(fn($record) => "La variante \"{$record->nom}\" sera utilisée par défaut pour le type \"{$record->type_document}\".")
                        ->action(function ($record) {
                            EtatConfig::where('type_document', $record->type_document)
                                ->where('id', '!=', $record->id)
                                ->update(['est_defaut' => false]);
                            $record->update(['est_defaut' => true]);
                            Notification::make()->title('⭐ Variante par défaut mise à jour')->success()
                                ->body("\"{$record->nom}\" est maintenant la variante par défaut.")->send();
                        }),

                    // ── Supprimer ─────────────────────────────────
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn($record) => $record->estSupprimable()),

                ])
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
            ]);
    }

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
            'index'  => Pages\ListEtatConfigs::route('/'),
            'create' => Pages\CreateEtatConfig::route('/create'),
            'edit'   => Pages\EditEtatConfig::route('/{record}/edit'),
        ];
    }
}
