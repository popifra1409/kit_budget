<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\ParametresFournisseurResource\Pages;
use App\Models\ParametresFournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Support\Colors\Color;

class ParametresFournisseurResource extends Resource
{
    protected static ?string $model = ParametresFournisseur::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Informations Fournisseur';

    protected static ?string $modelLabel = 'Paramètres Fournisseur';

    protected static ?string $pluralModelLabel = 'Paramètres Fournisseur';

    protected static ?string $navigationGroup = 'Paramétrage';

    protected static ?int $navigationSort = 101;

    /**
     * Permissions – Paramètres système (Super Admin uniquement)
     */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_parametres_fournisseur');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('view_parametres_fournisseur');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('create_parametres_fournisseur');
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->can('update_parametres_fournisseur');
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->can('delete_parametres_fournisseur');
    }

    /**
     * Action spéciale : Activer un paramètre
     */
    public static function canActiver($record): bool
    {
        return auth()->check() && auth()->user()->can('activer_parametres_fournisseur');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Tabs::make('Tabs')
                    ->tabs([

                        // ====================================
                        // ONGLET 1: INFORMATIONS SOCIÉTÉ
                        // ====================================
                        Tabs\Tab::make('Société')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([

                                Section::make('Identité de la Société')
                                    ->description('Informations principales de votre société éditrice')
                                    ->schema([
                                        Forms\Components\TextInput::make('nom_societe')
                                            ->label('Nom de la Société')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: TechSolutions SARL')
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('sigle')
                                                    ->label('Sigle')
                                                    ->maxLength(50)
                                                    ->placeholder('Ex: TS'),

                                                Forms\Components\TextInput::make('forme_juridique')
                                                    ->label('Forme Juridique')
                                                    ->maxLength(100)
                                                    ->placeholder('Ex: SARL, SA, SAS'),
                                            ]),

                                        Forms\Components\FileUpload::make('logo')
                                            ->label('Logo de la Société')
                                            ->image()
                                            ->disk('public')
                                            ->directory('logos/fournisseur')
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ])
                                            ->maxSize(2048)
                                            ->helperText('Format recommandé: 200x60 pixels, PNG avec fond transparent')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Coordonnées')
                                    ->schema([
                                        Forms\Components\Textarea::make('adresse')
                                            ->label('Adresse')
                                            ->rows(2)
                                            ->placeholder('Rue, avenue, quartier...')
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('ville')
                                                    ->label('Ville')
                                                    ->maxLength(100),

                                                Forms\Components\TextInput::make('code_postal')
                                                    ->label('Code Postal')
                                                    ->maxLength(20),

                                                Forms\Components\TextInput::make('pays')
                                                    ->label('Pays')
                                                    ->maxLength(100)
                                                    ->default('Cameroun'),
                                            ]),

                                        Forms\Components\TextInput::make('boite_postale')
                                            ->label('Boîte Postale')
                                            ->maxLength(50)
                                            ->placeholder('Ex: BP 1234'),
                                    ])
                                    ->columns(2),

                                Section::make('Informations Légales')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('numero_contribuable')
                                                    ->label('Numéro de Contribuable')
                                                    ->maxLength(100),

                                                Forms\Components\TextInput::make('rccm')
                                                    ->label('RCCM')
                                                    ->maxLength(100),

                                                Forms\Components\TextInput::make('numero_agrement')
                                                    ->label('Numéro d\'Agrément')
                                                    ->maxLength(100)
                                                    ->helperText('Si applicable'),
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),

                        // ====================================
                        // ONGLET 2: CONTACT
                        // ====================================
                        Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([

                                Section::make('Emails')
                                    ->schema([
                                        Forms\Components\TextInput::make('email_general')
                                            ->label('Email Général')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('contact@votresociete.com')
                                            ->suffixIcon('heroicon-o-envelope')
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('email_support')
                                                    ->label('Email Support Technique')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->placeholder('support@votresociete.com')
                                                    ->suffixIcon('heroicon-o-lifebuoy'),

                                                Forms\Components\TextInput::make('email_commercial')
                                                    ->label('Email Commercial')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->placeholder('commercial@votresociete.com')
                                                    ->suffixIcon('heroicon-o-briefcase'),
                                            ]),
                                    ])
                                    ->columns(2),

                                Section::make('Téléphones')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('telephone')
                                                    ->label('Téléphone Principal')
                                                    ->tel()
                                                    ->maxLength(50)
                                                    ->placeholder('+237 00 00 00 00')
                                                    ->suffixIcon('heroicon-o-phone'),

                                                Forms\Components\TextInput::make('fax')
                                                    ->label('Fax')
                                                    ->maxLength(50)
                                                    ->suffixIcon('heroicon-o-printer'),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('telephone_support')
                                                    ->label('Hotline Support')
                                                    ->tel()
                                                    ->maxLength(50)
                                                    ->placeholder('+237 00 00 00 00')
                                                    ->helperText('Numéro du support technique')
                                                    ->suffixIcon('heroicon-o-lifebuoy'),

                                                Forms\Components\TextInput::make('telephone_urgence')
                                                    ->label('Numéro d\'Urgence 24/7')
                                                    ->tel()
                                                    ->maxLength(50)
                                                    ->suffixIcon('heroicon-o-exclamation-triangle'),
                                            ]),

                                        Forms\Components\Textarea::make('horaires_support')
                                            ->label('Horaires du Support')
                                            ->rows(3)
                                            ->placeholder('Lundi - Vendredi: 8h00 - 18h00
Samedi: 9h00 - 13h00
Dimanche: Fermé')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Web & Réseaux Sociaux')
                                    ->schema([
                                        Forms\Components\TextInput::make('site_web')
                                            ->label('Site Web')
                                            ->url()
                                            ->maxLength(255)
                                            ->placeholder('https://www.votresociete.com')
                                            ->prefixIcon('heroicon-o-globe-alt')
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('facebook')
                                                    ->label('Facebook')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder('https://facebook.com/...')
                                                    ->prefixIcon('heroicon-o-at-symbol'),

                                                Forms\Components\TextInput::make('twitter')
                                                    ->label('Twitter / X')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder('https://twitter.com/...')
                                                    ->prefixIcon('heroicon-o-at-symbol'),

                                                Forms\Components\TextInput::make('linkedin')
                                                    ->label('LinkedIn')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder('https://linkedin.com/...')
                                                    ->prefixIcon('heroicon-o-at-symbol'),

                                                Forms\Components\TextInput::make('youtube')
                                                    ->label('YouTube')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder('https://youtube.com/...')
                                                    ->prefixIcon('heroicon-o-at-symbol'),
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),

                        // ====================================
                        // ONGLET 3: LOGICIEL
                        // ====================================
                        Tabs\Tab::make('Logiciel')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([

                                Section::make('Informations Produit')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('nom_logiciel')
                                                    ->label('Nom du Logiciel')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->default('Budget Manager')
                                                    ->placeholder('Ex: Budget Manager Pro'),

                                                Forms\Components\TextInput::make('version_logiciel')
                                                    ->label('Version')
                                                    ->required()
                                                    ->maxLength(20)
                                                    ->default('1.0.0')
                                                    ->placeholder('1.0.0')
                                                    ->helperText('Format: X.Y.Z'),
                                            ]),

                                        Forms\Components\Textarea::make('description_logiciel')
                                            ->label('Description')
                                            ->rows(4)
                                            ->placeholder('Description du logiciel qui apparaîtra dans le footer...')
                                            ->helperText('Cette description sera affichée dans le footer de l\'application')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make('Documentation')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('url_documentation')
                                                    ->label('URL Documentation')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder('https://docs.votresociete.com')
                                                    ->prefixIcon('heroicon-o-book-open'),

                                                Forms\Components\TextInput::make('url_guide_utilisateur')
                                                    ->label('URL Guide Utilisateur')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->placeholder('https://guide.votresociete.com')
                                                    ->prefixIcon('heroicon-o-document-text'),
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),

                        // ====================================
                        // ONGLET 4: COPYRIGHT & LÉGAL
                        // ====================================
                        Tabs\Tab::make('Copyright & Légal')
                            ->icon('heroicon-o-scale')
                            ->schema([

                                Section::make('Copyright')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('copyright_texte')
                                                    ->label('Texte Copyright')
                                                    ->maxLength(255)
                                                    ->default('Tous droits réservés')
                                                    ->placeholder('Tous droits réservés'),

                                                Forms\Components\TextInput::make('copyright_annee_debut')
                                                    ->label('Année de Création')
                                                    ->numeric()
                                                    ->minValue(1900)
                                                    ->maxValue(date('Y'))
                                                    ->default(date('Y'))
                                                    ->helperText('L\'année en cours sera ajoutée automatiquement'),
                                            ]),
                                    ])
                                    ->columns(2),

                                Section::make('Mentions Légales')
                                    ->schema([
                                        Forms\Components\RichEditor::make('mentions_legales')
                                            ->label('Mentions Légales')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'bulletList',
                                                'orderedList',
                                                'link',
                                            ])
                                            ->columnSpanFull(),

                                        Forms\Components\RichEditor::make('conditions_utilisation')
                                            ->label('Conditions d\'Utilisation')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'bulletList',
                                                'orderedList',
                                                'link',
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible(),
                            ]),

                        // ====================================
                        // ONGLET 5: APPARENCE
                        // ====================================
                        Tabs\Tab::make('Apparence')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([

                                Section::make('Paramètres d\'Affichage')
                                    ->schema([
                                        Forms\Components\Toggle::make('afficher_footer')
                                            ->label('Afficher le Footer')
                                            ->default(true)
                                            ->helperText('Afficher le footer avec les informations du fournisseur')
                                            ->inline(false),

                                        Forms\Components\Toggle::make('afficher_badge_licence')
                                            ->label('Afficher le Badge de Licence')
                                            ->default(true)
                                            ->helperText('Afficher le badge "Licence accordée à..." dans le header')
                                            ->inline(false),
                                    ]),

                                Section::make('Couleurs')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('couleur_principale')
                                            ->label('Couleur Principale')
                                            ->default('#0ea5e9')
                                            ->helperText('Couleur primaire de l\'interface (format hexadécimal)'),
                                    ])
                                    ->collapsible(),
                            ]),

                        // ====================================
                        // ONGLET 6: SYSTÈME
                        // ====================================
                        Tabs\Tab::make('Système')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([

                                Section::make('Paramètres Système')
                                    ->schema([
                                        Forms\Components\Toggle::make('actif')
                                            ->label('Paramétrage Actif')
                                            ->default(true)
                                            ->helperText('Un seul paramétrage peut être actif à la fois')
                                            ->disabled(fn($record) => $record?->actif === true)
                                            ->dehydrated(),

                                        Forms\Components\Placeholder::make('created_at')
                                            ->label('Créé le')
                                            ->content(fn($record): string => $record?->created_at?->format('d/m/Y H:i') ?? '-'),

                                        Forms\Components\Placeholder::make('updated_at')
                                            ->label('Modifié le')
                                            ->content(fn($record): string => $record?->updated_at?->format('d/m/Y H:i') ?? '-'),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-logo.png')),

                Tables\Columns\TextColumn::make('nom_societe')
                    ->label('Société')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->sigle),

                Tables\Columns\TextColumn::make('nom_logiciel')
                    ->label('Logiciel')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('version_logiciel')
                    ->label('Version')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('email_support')
                    ->label('Support')
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Email copié!')
                    ->copyMessageDuration(1500),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('actif', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParametresFournisseurs::route('/'),
            'create' => Pages\CreateParametresFournisseur::route('/create'),
            'edit' => Pages\EditParametresFournisseur::route('/{record}/edit'),
        ];
    }
}
