<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Utilisateurs';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 1;

    // ========================================
    // PERMISSIONS
    // ========================================

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_user') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_user') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_user') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_user') ?? false;
    }

    public static function canView($record): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if ($user->can('super_admin_user')) {
            return true;
        }

        if ($user->can('admin_user')) {
            if ($record->hasRole('super_admin')) {
                return false;
            }
            return true;
        }

        return false;
    }

    // ========================================
    // QUERY
    // ========================================

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Bloquer la visibilité des super admin pour les admin "classiques"
        if (auth()->check() && auth()->user()->can('admin_user') && !auth()->user()->can('super_admin_user')) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super_admin');
            });
        }

        return $query;
    }


    // ========================================
    // FORMULAIRE
    // ========================================

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations personnelles')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->required(fn($record) => $record === null)
                            ->dehydrated(fn($state) => filled($state))
                            ->minLength(8)
                            ->maxLength(255)
                            ->helperText('Minimum 8 caractères. Laisser vide pour ne pas changer.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Rôles et Permissions')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Rôle')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(function () {
                                if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                                    return Role::where('name', '!=', 'super_admin')
                                        ->pluck('name', 'id');
                                }
                                return Role::pluck('name', 'id');
                            })
                            ->helperText(function () {
                                if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                                    return '⚠️ Le rôle Super Admin est réservé et non disponible';
                                }
                                return 'Sélectionnez un ou plusieurs rôles';
                            })
                            ->required(),
                    ]),

                Forms\Components\Section::make('Personnel associé')
                    ->description('Lier cet utilisateur à un dossier personnel')
                    ->schema([

                        Forms\Components\Select::make('personnel_id')
                            ->label('Dossier Personnel')
                            ->relationship('personnel', 'matricule')
                            ->getOptionLabelFromRecordUsing(
                                fn(\App\Models\Personnel $p) =>
                                "{$p->matricule} — {$p->nom} {$p->prenoms}"
                            )
                            ->searchable()->preload()
                            ->live()
                            ->hint(function ($record) {
                                if (!$record) return null;
                                $p = $record->personnel;
                                if (!$p) return '⚠️ Aucun dossier personnel lié';
                                return "✅ {$p->matricule} — {$p->nom} {$p->prenoms}";
                            })
                            ->hintColor(fn($record) => $record?->personnel ? 'success' : 'warning')
                            ->columnSpanFull(),

                        // ✅ Bouton de création avec pré-remplissage garanti
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('creer_personnel')
                                ->label('➕ Créer le dossier personnel')
                                ->icon('heroicon-o-user-plus')
                                ->color('info')
                                ->visible(fn(Forms\Get $get) => !$get('personnel_id'))
                                ->modalHeading('Créer un dossier personnel')
                                ->modalWidth('3xl')
                                ->modalSubmitActionLabel('Créer et lier')

                                // ✅ Pré-remplir depuis les champs User AVANT ouverture du modal
                                ->fillForm(function (Forms\Get $get): array {
                                    $userName  = $get('name')  ?? '';
                                    $userEmail = $get('email') ?? '';

                                    $parts   = explode(' ', trim($userName), 2);
                                    $nom     = strtoupper($parts[0] ?? '');
                                    $prenoms = ucwords(strtolower($parts[1] ?? ''));

                                    return [
                                        'nom'     => $nom,
                                        'prenoms' => $prenoms,
                                        'email'   => $userEmail,
                                        'statut'  => 'actif',
                                        'actif'   => true,
                                    ];
                                })

                                ->form([
                                    Forms\Components\Placeholder::make('info')
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString(
                                            '<div class="rounded p-2 text-xs '
                                                . 'bg-blue-50 dark:bg-blue-900/30 '
                                                . 'text-blue-700 dark:text-blue-300 '
                                                . 'border border-blue-200 dark:border-blue-700">'
                                                . '💡 Champs pré-remplis depuis le compte utilisateur.'
                                                . '</div>'
                                        ))
                                        ->columnSpanFull(),

                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('matricule')
                                            ->label('Matricule')
                                            ->unique(\App\Models\Personnel::class, 'matricule')
                                            ->maxLength(50)
                                            ->placeholder('Laissez vide → génération auto'),

                                        Forms\Components\Select::make('civilite')
                                            ->label('Civilité')
                                            ->options([
                                                'M.'   => 'M.',
                                                'Mme'  => 'Mme',
                                                'Mlle' => 'Mlle',
                                            ]),

                                        Forms\Components\Select::make('sexe')
                                            ->label('Sexe')
                                            ->options(['M' => 'Masculin', 'F' => 'Féminin'])
                                            ->required(),
                                    ]),

                                    Forms\Components\Grid::make(2)->schema([
                                        // ✅ Pré-rempli via fillForm
                                        Forms\Components\TextInput::make('nom')
                                            ->label('Nom')
                                            ->required()->maxLength(255),

                                        Forms\Components\TextInput::make('prenoms')
                                            ->label('Prénoms')
                                            ->required()->maxLength(255),
                                    ]),

                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('service_id')
                                            ->label('Service')
                                            ->options(
                                                \App\Models\Service::where('actif', true)
                                                    ->pluck('nom', 'id')
                                            )
                                            ->searchable()->preload()->required(),

                                        Forms\Components\TextInput::make('fonction')
                                            ->label('Fonction')
                                            ->required()->maxLength(255),
                                    ]),

                                    Forms\Components\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('grade')
                                            ->label('Grade')->maxLength(255),

                                        Forms\Components\Select::make('categorie')
                                            ->label('Catégorie')
                                            ->options([
                                                'A' => 'Catégorie A',
                                                'B' => 'Catégorie B',
                                                'C' => 'Catégorie C',
                                                'D' => 'Catégorie D',
                                            ]),

                                        Forms\Components\TextInput::make('echelon')
                                            ->label('Échelon')->maxLength(255),
                                    ]),

                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('telephone')
                                            ->label('Téléphone')->tel()->maxLength(255),

                                        // ✅ Pré-rempli via fillForm
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email')->email()->maxLength(255),
                                    ]),

                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\Select::make('statut')
                                            ->label('Statut')
                                            ->options([
                                                'actif'          => 'Actif',
                                                'conge'          => 'En congé',
                                                'detache'        => 'Détaché',
                                                'disponibilite'  => 'En disponibilité',
                                                'suspendu'       => 'Suspendu',
                                                'retraite'       => 'Retraité',
                                                'demissionnaire' => 'Démissionnaire',
                                            ])
                                            ->required()->default('actif'),

                                        Forms\Components\Toggle::make('actif')
                                            ->label('Actif')
                                            ->default(true)
                                            ->inline(false),
                                    ]),
                                ])

                                ->action(function (array $data, Forms\Set $set, Forms\Get $get) {
                                    // ✅ Générer matricule si vide
                                    if (empty($data['matricule'])) {
                                        $data['matricule'] = \App\Models\Personnel::genererMatricule();
                                    }

                                    $data['statut'] = $data['statut'] ?? 'actif';
                                    $data['actif']  = $data['actif']  ?? true;

                                    $personnel = \App\Models\Personnel::create($data);

                                    // ✅ Injecter l'id dans le Select du formulaire parent
                                    $set('personnel_id', $personnel->id);

                                    \Filament\Notifications\Notification::make()
                                        ->title('✅ Personnel créé et lié')
                                        ->success()
                                        ->body("{$personnel->matricule} — {$personnel->nom} {$personnel->prenoms}")
                                        ->send();
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Statut du Compte')
                    ->description('Activer ou désactiver l\'accès de l\'utilisateur à l\'application')
                    ->schema([
                        Forms\Components\Toggle::make('actif')
                            ->label('Compte actif')
                            ->default(true)
                            ->inline(false)
                            ->helperText(function ($record) {
                                // Message spécial pour super_admin
                                if ($record && $record->hasRole('super_admin')) {
                                    return '🔒 Les comptes Super Admin sont toujours actifs et ne peuvent pas être désactivés';
                                }
                                return 'Un utilisateur inactif ne peut pas se connecter';
                            })
                            ->disabled(function ($record) {
                                // Super admin ne peut jamais être désactivé
                                return $record && $record->hasRole('super_admin');
                            })
                            ->dehydrated(function ($record) {
                                // Si super_admin, forcer actif = true
                                if ($record && $record->hasRole('super_admin')) {
                                    return false; // Ne pas sauvegarder (garder true)
                                }
                                return true;
                            })
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    // ========================================
    // TABLE
    // ========================================

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copié')
                    ->copyMessageDuration(1500),

                Tables\Columns\BadgeColumn::make('roles.name')
                    ->label('Rôle(s)')
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'success' => fn($state) => in_array($state, ['directeur_general', 'daaf']),
                        'info' => fn($state) => in_array($state, ['chef_service_budget', 'sous_directeur_budget']),
                        'primary' => fn($state) => in_array($state, ['controleur_financier', 'agence_comptable']),
                        'gray' => 'operateur_budget',
                    ])
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        $translations = [
                            'super_admin' => 'Super Admin',
                            'admin' => 'Admin',
                            'directeur_general' => 'Directeur Général',
                            'daaf' => 'DAAF',
                            'sous_directeur_budget' => 'Sous-Directeur Budget',
                            'chef_service_budget' => 'Chef Service Budget',
                            'operateur_budget' => 'Opérateur Budget',
                            'controleur_financier' => 'Contrôleur Financier',
                            'agence_comptable' => 'Agence Comptable',
                        ];

                        return $translations[$state] ?? ucfirst(str_replace('_', ' ', $state));
                    }),

                Tables\Columns\TextColumn::make('personnel.matricule')
                    ->label('Matricule')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('actif')
                    ->label('Actif')
                    ->sortable()
                    ->disabled(function ($record) {
                        // ⭐ PROTECTION : Super admin ne peut jamais être désactivé
                        return $record->hasRole('super_admin');
                    })
                    ->beforeStateUpdated(function ($record, $state) {
                        // ⭐ PROTECTION DOUBLE : Vérification avant modification
                        if ($record->hasRole('super_admin')) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Action impossible')
                                ->body('🔒 Les comptes Super Admin doivent toujours rester actifs pour garantir l\'accès à l\'application.')
                                ->persistent()
                                ->send();

                            return false;
                        }

                        // Empêcher la désactivation de son propre compte
                        if ($record->id === auth()->id() && !$state) {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Action impossible')
                                ->body('Vous ne pouvez pas désactiver votre propre compte.')
                                ->send();

                            return false;
                        }

                        // Empêcher admin de désactiver super_admin
                        if (
                            auth()->user()->hasRole('admin') &&
                            !auth()->user()->hasRole('super_admin') &&
                            $record->hasRole('super_admin')
                        ) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Action interdite')
                                ->body('Vous ne pouvez pas modifier un compte Super Admin.')
                                ->send();

                            return false;
                        }
                    })
                    ->afterStateUpdated(function ($record, $state) {
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Statut mis à jour')
                            ->body($state ?
                                '✅ L\'utilisateur peut maintenant se connecter.' :
                                '⚠️ L\'utilisateur ne peut plus se connecter. Il devra contacter un administrateur.')
                            ->send();
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Statut')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement')
                    ->default(null),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    // Action rapide pour activer/désactiver
                    Tables\Actions\Action::make('toggle_actif')
                        ->label(fn($record) => $record->actif ? 'Désactiver' : 'Activer')
                        ->icon(fn($record) => $record->actif ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn($record) => $record->actif ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn($record) => $record->actif ? 'Désactiver cet utilisateur ?' : 'Activer cet utilisateur ?')
                        ->modalDescription(fn($record) => $record->actif ?
                            'L\'utilisateur ne pourra plus se connecter. Il devra contacter un administrateur pour réactiver son compte.' :
                            'L\'utilisateur pourra se connecter à l\'application.')
                        ->action(function ($record) {
                            // ⭐ PROTECTION : Super admin ne peut jamais être désactivé
                            if ($record->hasRole('super_admin')) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Action impossible')
                                    ->body('🔒 Les comptes Super Admin doivent toujours rester actifs.')
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            // Empêcher la désactivation de son propre compte
                            if ($record->id === auth()->id()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Action impossible')
                                    ->body('Vous ne pouvez pas désactiver votre propre compte.')
                                    ->send();
                                return;
                            }

                            // Empêcher admin de modifier super_admin
                            if (
                                auth()->user()->hasRole('admin') &&
                                !auth()->user()->hasRole('super_admin') &&
                                $record->hasRole('super_admin')
                            ) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Action interdite')
                                    ->body('Vous ne pouvez pas modifier un compte Super Admin.')
                                    ->send();
                                return;
                            }

                            $record->actif = !$record->actif;
                            $record->save();

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Statut modifié')
                                ->body($record->actif ?
                                    'Utilisateur activé avec succès.' :
                                    'Utilisateur désactivé. Il devra contacter un administrateur.')
                                ->send();
                        })
                        ->visible(
                            fn($record) =>
                            // Masquer pour super_admin
                            !$record->hasRole('super_admin') &&
                                // Masquer pour son propre compte
                                $record->id !== auth()->id() &&
                                // Masquer pour super_admin si on est admin
                                !(auth()->user()->hasRole('admin') &&
                                    !auth()->user()->hasRole('super_admin') &&
                                    $record->hasRole('super_admin'))
                        ),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            if (auth()->check() && auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                                $hasSuperAdmin = $records->filter(function ($record) {
                                    return $record->hasRole('super_admin');
                                })->isNotEmpty();

                                if ($hasSuperAdmin) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Action interdite')
                                        ->body('Vous ne pouvez pas supprimer des utilisateurs Super Admin.')
                                        ->send();

                                    throw new \Exception('Vous ne pouvez pas supprimer des utilisateurs Super Admin.');
                                }
                            }
                        }),

                    // Action en masse pour activer
                    Tables\Actions\BulkAction::make('activer')
                        ->label('Activer la sélection')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            $skippedSuperAdmin = 0;

                            foreach ($records as $record) {
                                // ⭐ Ignorer les super_admin (déjà actifs)
                                if ($record->hasRole('super_admin')) {
                                    $skippedSuperAdmin++;
                                    continue;
                                }

                                if (
                                    auth()->user()->hasRole('admin') &&
                                    !auth()->user()->hasRole('super_admin') &&
                                    $record->hasRole('super_admin')
                                ) {
                                    continue;
                                }

                                $record->update(['actif' => true]);
                                $count++;
                            }

                            $message = $count . ' utilisateur(s) activé(s).';
                            if ($skippedSuperAdmin > 0) {
                                $message .= ' (' . $skippedSuperAdmin . ' Super Admin déjà actif(s))';
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Utilisateurs activés')
                                ->body($message)
                                ->send();
                        }),

                    // Action en masse pour désactiver
                    Tables\Actions\BulkAction::make('desactiver')
                        ->label('Désactiver la sélection')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Les utilisateurs ne pourront plus se connecter. Ils devront contacter un administrateur.')
                        ->action(function ($records) {
                            $count = 0;
                            $skippedSuperAdmin = 0;
                            $skippedSelf = 0;

                            foreach ($records as $record) {
                                // ⭐ PROTECTION : Ne jamais désactiver super_admin
                                if ($record->hasRole('super_admin')) {
                                    $skippedSuperAdmin++;
                                    continue;
                                }

                                // Ne pas désactiver son propre compte
                                if ($record->id === auth()->id()) {
                                    $skippedSelf++;
                                    continue;
                                }

                                if (
                                    auth()->user()->hasRole('admin') &&
                                    !auth()->user()->hasRole('super_admin') &&
                                    $record->hasRole('super_admin')
                                ) {
                                    continue;
                                }

                                $record->update(['actif' => false]);
                                $count++;
                            }

                            $message = $count . ' utilisateur(s) désactivé(s).';
                            if ($skippedSuperAdmin > 0) {
                                $message .= ' ⚠️ ' . $skippedSuperAdmin . ' Super Admin ignoré(s) (protection).';
                            }
                            if ($skippedSelf > 0) {
                                $message .= ' ⚠️ Votre propre compte ignoré.';
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Utilisateurs désactivés')
                                ->body($message)
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getEloquentQuery()->where('actif', true);
        return (string) $query->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
