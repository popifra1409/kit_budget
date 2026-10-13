<?php

namespace App\Filament\Budget\Resources\CollectifBudgetaireResource\Pages;

use App\Filament\Budget\Resources\CollectifBudgetaireResource;
use App\Services\Budget\FinancementCollectifService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCollectifBudgetaire extends ViewRecord
{
    protected static string $resource = CollectifBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // ✅ Association facultative recette → dépenses (lecture de l'équilibre par financement)
            Actions\Action::make('associerFinancements')
                ->label('Associer recettes et dépenses')
                ->icon('heroicon-o-link')
                ->color('info')
                ->visible(fn($record) => in_array($record->statut, ['projet', 'adopte'], true)
                    && auth()->user()?->can('update_collectif_budgetaire'))
                ->modalHeading(fn($record) => "Financements du collectif {$record->numero}")
                ->modalDescription('Pour chaque recette, cochez les dépenses qu\'elle finance. Facultatif, et sans effet sur les montants : '
                    . 'l\'association sert à présenter chaque recette en face de ses dépenses dans l\'état du collectif. '
                    . 'Une dépense ne peut être rattachée qu\'à une recette.')
                ->form(function ($record) {
                    $service = app(FinancementCollectifService::class);
                    $recettes = $service->mouvements($record, 'recette');
                    $depenses = $service->mouvements($record, 'depense');

                    if ($recettes->isEmpty() || $depenses->isEmpty()) {
                        return [Forms\Components\Placeholder::make('vide')->label('')
                            ->content('Ce collectif doit comporter au moins une recette et une dépense.')];
                    }

                    $options = $depenses->mapWithKeys(fn($d) => [$d->id => $service->libelle($d)])->all();

                    return $recettes->map(
                        fn($r) => Forms\Components\CheckboxList::make("r_{$r->id}")
                            ->label('Recette : ' . $service->libelle($r))
                            ->options($options)
                            ->default($depenses->where('mouvement_recette_id', $r->id)->pluck('id')->map(fn($id) => (string) $id)->all())
                            ->columns(2)
                            ->bulkToggleable()
                    )->all();
                })
                ->action(function ($record, array $data) {
                    $service = app(FinancementCollectifService::class);
                    $associations = collect($data)
                        ->filter(fn($v, $k) => str_starts_with((string) $k, 'r_'))
                        ->mapWithKeys(fn($ids, $k) => [(int) substr($k, 2) => array_map('intval', (array) $ids)])
                        ->all();
                    try {
                        $service->associer($record, $associations);
                        $s = $service->synthese($record);
                        $desequilibres = $s['financements']->filter(fn($fi) => abs($fi['ecart']) >= 1)->count();
                        Notification::make()->success()->title('Associations enregistrées')
                            ->body($s['financements']->count() . ' recette(s) associée(s)'
                                . ($desequilibres ? " — ⚠️ {$desequilibres} financement(s) dont recette et dépenses ne s'équilibrent pas" : ' — financements équilibrés')
                                . ($s['depenses_non_associees']->count() ? ' ; ' . $s['depenses_non_associees']->count() . ' dépense(s) non associée(s).' : '.'))
                            ->send();
                    } catch (\DomainException $e) {
                        Notification::make()->warning()->title('Association impossible')->body($e->getMessage())->persistent()->send();
                    }
                }),

            // ✅ Impression de l'état du collectif (recettes en face de leurs dépenses, virements regroupés)
            Actions\Action::make('imprimerEtat')
                ->label('Imprimer l\'état (PDF)')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn($record) => CollectifBudgetaireResource::telechargerEtat([$record->id], $record->exercice_id)),
        ];
    }
}
