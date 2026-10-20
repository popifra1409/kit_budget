<?php

namespace App\Filament\Budget\Resources\NomenclatureBudgetaireResource\Pages;

use App\Filament\Budget\Resources\NomenclatureBudgetaireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNomenclatureBudgetaire extends EditRecord
{
    protected static string $resource = NomenclatureBudgetaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['modifie_par'] = auth()->id();

        return $data;
    }

    /**
     * ✅ Le groupe choisi est TOUJOURS enregistré : si le modèle l'ignore (absent de $fillable)
     *    ou le recalcule à l'enregistrement, il est réécrit tel que l'utilisateur l'a choisi.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $groupeVoulu = array_key_exists('groupe_id', $data) ? ($data['groupe_id'] ?: null) : $record->groupe_id;

        $record->update($data);

        if ((int) $record->fresh()->groupe_id !== (int) $groupeVoulu) {
            $record->forceFill(['groupe_id' => $groupeVoulu])->saveQuietly();
            \Log::info("Nomenclature {$record->code} : groupe réécrit après enregistrement", ['groupe_id' => $groupeVoulu]);
        }

        return $record->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
