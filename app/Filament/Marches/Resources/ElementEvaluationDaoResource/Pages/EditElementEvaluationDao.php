<?php

namespace App\Filament\Marches\Resources\ElementEvaluationDaoResource\Pages;

use App\Filament\Marches\Resources\ElementEvaluationDaoResource;
use App\Models\ElementEvaluationDao;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * Le barème d'une étape devient une règle de classement à l'étape D : les notes des
 * offres se comparent dessus. Le baisser sous une note déjà portée rendrait cette
 * note hors barème sans que rien ne le dise, et fausserait le classement retenu pour
 * l'attribution — la correction se fait en relevant l'offre concernée d'abord.
 *
 * Le contrôle est ici, sur le champ, et non dans un hook qui écrirait la valeur :
 * un hook `saving` qui plafonne le barème ferait disparaître la saisie sans message.
 */
class EditElementEvaluationDao extends EditRecord
{
    protected static string $resource = ElementEvaluationDaoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        if (!$record instanceof ElementEvaluationDao || !array_key_exists('bareme_max', $data)) {
            return $data;
        }

        $nouveau = (float) $data['bareme_max'];
        $plusHaute = (float) $record->evaluations()->max('note');

        if ($plusHaute > $nouveau) {
            $message = sprintf(
                'Le barème ne peut pas descendre sous la note déjà portée : la plus haute note de cette '
                    . 'étape est %s. Relevez cette note d\'abord, ou laissez le barème à %s.',
                number_format($plusHaute, 2, ',', ' '),
                number_format((float) $record->bareme_max, 2, ',', ' '),
            );

            Notification::make()->title('❌ Barème refusé')->body($message)
                ->danger()->persistent()->send();

            throw ValidationException::withMessages(['bareme_max' => $message]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn (ElementEvaluationDao $record) => ElementEvaluationDaoResource::refuserSuppression($record)),
        ];
    }
}
