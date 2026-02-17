<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrdonnancePaiementResource\Pages;
use App\Models\OrdonnancePaiement;
use App\Models\Engagement;
use App\Models\Fournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Filament\Forms\Components\ExerciceSelect;
use Illuminate\Database\Eloquent\Builder;

class OrdonnancePaiementResource extends Resource
{
    protected static ?string $model = OrdonnancePaiement::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Ordonnances de Paiement';
    protected static ?string $modelLabel = 'Ordonnance de Paiement';
    protected static ?string $pluralModelLabel = 'Ordonnances de Paiement';
    protected static ?string $navigationGroup = 'Commandes & Engagement';
    protected static ?int $navigationSort = 5;

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
        if (!$engagementId) return '';

        try {
            $engagement = Engagement::with('engageable', 'beneficiaire')->find($engagementId);
            if (!$engagement) return '';

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
                    ->label('A précompter')
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
                    Tables\Actions\Action::make('telecharger_op')
                        ->label('Télécharger OP')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->visible(fn($record) => $record->type_ordonnance === 'standard')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'ordonnance_paiement',
                            'id' => $record->id,
                        ])),

                    Tables\Actions\Action::make('afficher_op')
                        ->label('Aperçu OP')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn($record) => $record->type_ordonnance === 'standard')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'ordonnance_paiement',
                            'id' => $record->id,
                        ]))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('telecharger_op_impot')
                        ->label('Télécharger OP Impôt')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->visible(fn($record) => $record->type_ordonnance === 'impot')
                        ->url(fn($record) => route('pdf.telecharger', [
                            'etat' => 'ordonnance_paiement_impot',
                            'id' => $record->id,
                        ])),

                    Tables\Actions\Action::make('afficher_op_impot')
                        ->label('Aperçu OP Impôt')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn($record) => $record->type_ordonnance === 'impot')
                        ->url(fn($record) => route('pdf.afficher', [
                            'etat' => 'ordonnance_paiement_impot',
                            'id' => $record->id,
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('Télécharger / Aperçu')
                    ->icon('heroicon-m-document-arrow-down')
                    ->size('sm')
                    ->button(),
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
            'index' => Pages\ListOrdonnancePaiements::route('/'),
            'create' => Pages\CreateOrdonnancePaiement::route('/create'),
            'view' => Pages\ViewOrdonnancePaiement::route('/{record}'),
            'edit' => Pages\EditOrdonnancePaiement::route('/{record}/edit'),
        ];
    }
}
