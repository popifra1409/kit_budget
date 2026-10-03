<?php

namespace App\Filament\Resources\PersonnelResource\RelationManagers;

use App\Filament\Budget\Resources\EngagementResource;
use App\Models\Avenant;
use App\Models\Engagement;
use App\Services\Budget\ChangementBeneficiaireService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;

/**
 * Fiche agent — onglet « Avenants » : changements de bénéficiaire dans lesquels l'agent
 * a été REMPLACÉ (ancien bénéficiaire) ou qu'il a REÇUS (nouveau bénéficiaire).
 * Lecture seule.
 */
class AvenantsRelationManager extends RelationManager
{
    protected static string $relationship = 'avenantsBeneficiaire';
    protected static ?string $title = 'Avenants (bénéficiaire)';
    protected static ?string $icon = 'heroicon-o-arrows-right-left';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $agent = $this->getOwnerRecord();
        $formes = Engagement::formesDuType($agent::class);

        return $table
            // Les deux sens : agent reçu OU remplacé (la relation seule ne couvre que le premier)
            ->query(fn() => Avenant::query()
                ->where('type_correction', Avenant::TYPE_BENEFICIAIRE)
                ->where(fn($q) => $q
                    ->where(fn($r) => $r->where('beneficiaire_corrige_id', $agent->id)->whereIn('beneficiaire_corrige_type', $formes))
                    ->orWhere(fn($r) => $r->where('beneficiaire_original_id', $agent->id)->whereIn('beneficiaire_original_type', $formes)))
                ->with(['engagementOriginal', 'beneficiaireOriginal', 'beneficiaireCorrige']))
            ->columns([
                Tables\Columns\TextColumn::make('numero_avenant')
                    ->label('Avenant n°')->weight('bold'),

                Tables\Columns\TextColumn::make('engagementOriginal.numero')
                    ->label('Engagement')->placeholder('—'),

                Tables\Columns\TextColumn::make('sens')
                    ->label('Pour cet agent')
                    ->getStateUsing(fn($record) => (int) $record->beneficiaire_corrige_id === (int) $agent->id
                        && in_array($record->beneficiaire_corrige_type, $formes, true) ? 'recu' : 'remplace')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state === 'recu' ? 'Devenu bénéficiaire' : 'Remplacé')
                    ->color(fn($state) => $state === 'recu' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('autre_beneficiaire')
                    ->label('Autre bénéficiaire')
                    ->getStateUsing(function ($record) use ($agent, $formes) {
                        $estRecu = (int) $record->beneficiaire_corrige_id === (int) $agent->id
                            && in_array($record->beneficiaire_corrige_type, $formes, true);

                        return app(ChangementBeneficiaireService::class)->libelleBeneficiaire(
                            $estRecu ? $record->beneficiaireOriginal : $record->beneficiaireCorrige
                        );
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('motif')
                    ->label('Motif')->limit(40)->tooltip(fn($record) => $record->motif),

                Tables\Columns\TextColumn::make('date_application')
                    ->label('Appliqué le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('date_application', 'desc')
            ->actions([
                Tables\Actions\Action::make('voir_engagement')
                    ->label('Voir l\'engagement')->icon('heroicon-o-eye')->color('info')
                    ->visible(fn($record) => $record->engagement_original_id !== null)
                    ->url(fn($record) => EngagementResource::getUrl('view', ['record' => $record->engagement_original_id], panel: 'budget'))
                    ->openUrlInNewTab(),
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([]);
    }
}
