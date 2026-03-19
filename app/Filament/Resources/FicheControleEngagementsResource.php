<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FicheControleEngagementsResource\Pages;
use App\Models\LigneBudgetaire;
use App\Models\Exercice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class FicheControleEngagementsResource extends Resource
{
    protected static ?string $model = LigneBudgetaire::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Fiches de Contrôle';

    protected static ?string $modelLabel = 'Fiche de Contrôle';

    protected static ?string $pluralModelLabel = 'Fiches de Contrôle des Engagements';

    protected static ?string $navigationGroup = 'Contrôle & Suivi';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_fiche_controle_engagements') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Pas de création, c'est généré automatiquement
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('exercice.annee')
                    ->label('Exercice')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('budget.libelle')
                    ->label('Budget')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('nomenclature.code')
                    ->label('Nomenclature')
                    ->searchable()
                    ->description(fn($record) => $record->nomenclature?->libelle)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('dotation_initiale')
                    ->label('Dotation Initiale')
                    ->money('XAF')
                    ->sortable()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_engage')
                    ->label('Total Engagé')
                    ->getStateUsing(function ($record) {
                        return $record->engagements()->sum('montant_engage');
                    })
                    ->money('XAF')
                    ->color('warning')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('disponible_engagement')
                    ->label('Disponible')
                    ->money('XAF')
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('taux_consommation')
                    ->label('Taux Conso.')
                    ->getStateUsing(function ($record) {
                        if ($record->dotation_initiale == 0) return 0;
                        $totalEngage = $record->engagements()->sum('montant_engage');
                        return ($totalEngage / $record->dotation_initiale) * 100;
                    })
                    ->formatStateUsing(fn($state) => number_format($state, 1) . '%')
                    ->badge()
                    ->color(function ($state) {
                        if ($state < 50) return 'success';
                        if ($state < 80) return 'warning';
                        if ($state < 100) return 'danger';
                        return 'danger';
                    }),

                Tables\Columns\TextColumn::make('nb_engagements')
                    ->label('Nb Engagements')
                    ->getStateUsing(fn($record) => $record->engagements()->count())
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('exercice_id')
                    ->label('Exercice')
                    ->relationship('exercice', 'annee')
                    ->default(fn() => Exercice::getActif()?->id)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('budget_id')
                    ->label('Budget')
                    ->relationship('budget', 'libelle')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('avec_engagements')
                    ->label('Avec engagements uniquement')
                    ->query(fn($query) => $query->has('engagements'))
                    ->toggle()
                    ->default(true),

                Tables\Filters\Filter::make('depassement')
                    ->label('Dépassements de crédits')
                    ->query(fn($query) => $query->where('disponible_engagement', '<', 0))
                    ->toggle(),
            ])
            ->actions([
                // Action PDF
                Tables\Actions\Action::make('generer_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->url(fn($record) => route('fiche-controle-engagements.pdf', $record->id))
                    ->openUrlInNewTab(),

                // Action Preview
                Tables\Actions\Action::make('preview')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => route('fiche-controle-engagements.preview', $record->id))
                    ->openUrlInNewTab(),

                // Action Voir les détails
                Tables\Actions\Action::make('details')
                    ->label('Détails')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->modalHeading('Détails de la Ligne Budgétaire')
                    ->modalContent(function ($record) {
                        $engagements = $record->engagements()
                            ->with('engageable')
                            ->orderBy('date_engagement')
                            ->get();

                        $totalEngage = $engagements->sum('montant_engage');
                        $tauxConso = $record->dotation_initiale > 0
                            ? ($totalEngage / $record->dotation_initiale) * 100
                            : 0;

                        return view('filament.pages.fiche-controle-details', [
                            'record' => $record,
                            'engagements' => $engagements,
                            'totalEngage' => $totalEngage,
                            'tauxConso' => $tauxConso,
                        ]);
                    })
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('generer_fiches_pdf')
                    ->label('Générer les Fiches PDF')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        // Générer un ZIP avec toutes les fiches
                        $zip = new \ZipArchive();
                        $zipFilename = storage_path('app/temp/fiches_controle_' . time() . '.zip');

                        if ($zip->open($zipFilename, \ZipArchive::CREATE) === TRUE) {
                            foreach ($records as $record) {
                                $pdfService = new \App\Services\FicheControleEngagementsPdfService();
                                $pdf = $pdfService->genererPdf($record->id);

                                $filename = "fiche_controle_{$record->nomenclature->code}.pdf";
                                $zip->addFromString($filename, $pdf->output());
                            }
                            $zip->close();

                            Notification::make()
                                ->title('Fiches générées')
                                ->success()
                                ->body(count($records) . ' fiche(s) générée(s) avec succès.')
                                ->send();

                            return response()->download($zipFilename)->deleteFileAfterSend();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFicheControleEngagements::route('/'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['exercice', 'budget', 'nomenclature', 'engagements']);
    }
}
