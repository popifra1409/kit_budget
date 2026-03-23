<?php

namespace App\Filament\Budget\Resources;

use App\Filament\Budget\Resources\ReferenceMercurialeResource\Pages;
use App\Models\ReferenceMercuriale;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Forms\Components\ExerciceSelect;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ReferenceMercurialeImport;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReferenceMercurialeResource extends Resource
{
    protected static ?string $model = ReferenceMercuriale::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Références Mercuriales';

    protected static ?string $modelLabel = 'Référence Mercuriale';

    protected static ?string $pluralModelLabel = 'Références Mercuriales';

    protected static ?string $navigationGroup = 'Configuration Budget';

    protected static ?int $navigationSort = 5;

    /**
     * ==========================================
     * Permissions – Références Mercuriales
     * ==========================================
     */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_reference_mercuriale') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->can('view_reference_mercuriale') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_reference_mercuriale') ?? false;
    }

    public static function canEdit($record): bool
    {
        if (!auth()->user()?->can('update_reference_mercuriale')) {
            return false;
        }

        // Règle métier : modifiable uniquement si l'exercice est modifiable
        return $record->estModifiable();
    }

    public static function canDelete($record): bool
    {
        if (!auth()->user()?->can('delete_reference_mercuriale')) {
            return false;
        }

        // Règle métier : suppression uniquement si l'exercice est modifiable
        return $record->estModifiable();
    }

    /**
     * Action métier personnalisée : Activer/Désactiver
     */
    public static function canActiver($record): bool
    {
        return auth()->user()?->can('update_reference_mercuriale') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('exercice');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercice')
                    ->description('Exercice budgétaire de rattachement')
                    ->schema([
                        ExerciceSelect::make(),
                    ])
                    ->collapsible()
                    ->collapsed(fn($record) => $record !== null),

                Forms\Components\Section::make('Informations de la référence')
                    ->schema([
                        Forms\Components\TextInput::make('code_reference')
                            ->label('Code de référence')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Ex: REF-2025-001')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('designation')
                            ->label('Désignation')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Ordinateur portable HP EliteBook')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('unite')
                            ->label('Unité')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('pièce, kg, m, etc.')
                            ->default('pièce'),

                        Forms\Components\TextInput::make('prix_reference')
                            ->label('Prix de référence')
                            ->required()
                            ->numeric()
                            ->prefix('FCFA')
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('rubrique')
                            ->label('Rubrique')
                            ->maxLength(255)
                            ->placeholder('Ex: Informatique'),

                        Forms\Components\TextInput::make('sous_rubrique')
                            ->label('Sous-rubrique')
                            ->maxLength(255)
                            ->placeholder('Ex: Matériel informatique'),

                        Forms\Components\Toggle::make('actif')
                            ->label('Actif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\BadgeColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->colors([
                        'success' => fn($record) => $record->exercice?->estActif(),
                        'warning' => fn($record) => $record->exercice?->estCloture(),
                        'danger' => fn($record) => $record->exercice?->estArchive(),
                        'gray' => fn($record) => $record->exercice?->estBrouillon(),
                    ]),

                Tables\Columns\TextColumn::make('code_reference')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('designation')
                    ->label('Désignation')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),

                Tables\Columns\TextColumn::make('rubrique')
                    ->label('Rubrique')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sous_rubrique')
                    ->label('Sous-rubrique')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unite')
                    ->label('Unité')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('prix_reference')
                    ->label('Prix référence')
                    ->money('XAF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->searchable()
                    ->preload()
                    ->default(fn() => Exercice::getActif()?->id),

                Tables\Filters\SelectFilter::make('rubrique')
                    ->label('Rubrique')
                    ->options(fn() => ReferenceMercuriale::distinct()->pluck('rubrique', 'rubrique')->filter()),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importer')
                    ->label('Importer depuis Excel/CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('exercice_id')
                            ->label('Exercice budgétaire')
                            ->options(fn() => \App\Models\Exercice::pluck('libelle', 'id'))
                            ->default(fn() => \App\Models\Exercice::getActif()?->id)
                            ->required()
                            ->helperText('Les références seront importées pour cet exercice'),

                        FileUpload::make('fichier')
                            ->label('Fichier à importer')
                            ->acceptedFileTypes([
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                                'text/plain',
                            ])
                            ->maxSize(102400)
                            ->required()
                            ->helperText('Formats acceptés : .xlsx, .csv (max 100MB)')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('instructions')
                            ->label('Instructions')
                            ->content('Le fichier doit contenir : Code référence, Désignation, Unité, Prix référence, Rubrique, Sous-rubrique')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        try {
                            if (empty($data['fichier'])) {
                                throw new \Exception("Aucun fichier uploadé.");
                            }

                            // ✅ TESTER TOUS LES CHEMINS POSSIBLES
                            $cheminsPossibles = [
                                storage_path('app/livewire-tmp/' . $data['fichier']),
                                storage_path('app/' . $data['fichier']),
                                storage_path('app/public/' . $data['fichier']),
                                $data['fichier'],
                            ];

                            $cheminFichier = null;
                            foreach ($cheminsPossibles as $chemin) {
                                if (file_exists($chemin)) {
                                    $cheminFichier = $chemin;
                                    break;
                                }
                            }

                            // Si toujours pas trouvé, chercher dans livewire-tmp
                            if (!$cheminFichier) {
                                $nomFichier = basename($data['fichier']);
                                $fichiers = \Storage::files('livewire-tmp');

                                foreach ($fichiers as $fichier) {
                                    if (basename($fichier) === $nomFichier) {
                                        $cheminFichier = storage_path('app/' . $fichier);
                                        break;
                                    }
                                }
                            }

                            if (!$cheminFichier || !file_exists($cheminFichier)) {
                                throw new \Exception("Fichier introuvable. Vérifiez que l'upload s'est bien passé.");
                            }

                            if (filesize($cheminFichier) === 0) {
                                throw new \Exception("Le fichier est vide.");
                            }

                            // ✅ IMPORT
                            $import = new \App\Imports\ReferenceMercurialeImport($data['exercice_id']);
                            Excel::import($import, $cheminFichier);

                            $failures = $import->getFailures();

                            if (count($failures) > 0) {
                                $erreurs = collect($failures)->map(function ($failure) {
                                    return "Ligne {$failure->row()}: " . implode(', ', $failure->errors());
                                })->take(10)->implode("\n");

                                Notification::make()
                                    ->title('Import avec erreurs')
                                    ->warning()
                                    ->body("{$erreurs}")
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Import réussi ✅')
                                    ->success()
                                    ->body('Toutes les références ont été importées.')
                                    ->send();
                            }

                            // Nettoyage
                            try {
                                @unlink($cheminFichier);
                            } catch (\Exception $e) {
                            }
                        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                            $erreurs = [];
                            foreach ($e->failures() as $failure) {
                                $erreurs[] = "Ligne {$failure->row()}: " . implode(', ', $failure->errors());
                            }

                            Notification::make()
                                ->title('Erreur de validation')
                                ->danger()
                                ->body(implode("\n", array_slice($erreurs, 0, 10)))
                                ->persistent()
                                ->send();
                        } catch (\Exception $e) {
                            \Log::error('Erreur import', ['error' => $e->getMessage()]);

                            Notification::make()
                                ->title('Erreur import')
                                ->danger()
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('telecharger_modele')
                    ->label('Télécharger le modèle')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $csv = "Code référence,Désignation,Unité,Prix référence,Rubrique,Sous-rubrique\n";
                            $csv .= "REF-2026-001,Ordinateur portable HP EliteBook,pièce,450000,Informatique,Matériel informatique\n";
                            $csv .= "REF-2026-002,Imprimante Laser Canon,pièce,85000,Informatique,Périphériques\n";
                            $csv .= "REF-2026-003,Papier A4 80g (Ramette),ramette,2500,Fournitures,Papeterie\n";
                            echo $csv;
                        }, 'modele-references-mercuriales.csv');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code_reference', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferenceMercuriales::route('/'),
            'create' => Pages\CreateReferenceMercuriale::route('/create'),
            'edit' => Pages\EditReferenceMercuriale::route('/{record}/edit'),
        ];
    }
}
