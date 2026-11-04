<?php

namespace App\Filament\Marches\Resources\TypeOrdreServiceResource\Pages;

use App\Filament\Marches\Resources\TypeOrdreServiceResource;
use App\Models\TypeOrdreService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * `demarre_lexecution` n'est pas une étiquette : c'est la règle qui décide si
 * l'émission d'un type met un marché en cours d'exécution. Y toucher après coup
 * réécrirait le sens des ordres de service déjà émis sous ce type.
 *
 * La case se fige donc dès que le type a servi, dans les deux sens :
 *
 * - l'arrêter ferait des ordres de démarrage déjà notifiés de simples pièces de
 *   chronologie — le marché qu'ils ont mis en exécution n'aurait plus d'acte
 *   d'ouverture, et son délai d'exécution resterait sans origine ;
 * - la poser ferait d'arrêts ou de reprises déjà émis des actes de démarrage, ce qui
 *   autoriserait un marché encore homologué à passer en exécution sans qu'on ait
 *   jamais commandé le service.
 *
 * Le contrôle est ici, sur le champ, et non dans un hook qui écrirait la valeur : un
 * garde-fu `saving` qui remettrait la case à son ancienne valeur ferait disparaître
 * la saisie sans message. Un nouveau besoin se règle par un nouveau type, pas en
 * renommant le sens d'un type qui a servi.
 */
class EditTypeOrdreService extends EditRecord
{
    protected static string $resource = TypeOrdreServiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        if (!$record instanceof TypeOrdreService || !array_key_exists('demarre_lexecution', $data)) {
            return $data;
        }

        $avant = (bool) $record->demarre_lexecution;
        $apres = (bool) $data['demarre_lexecution'];
        if ($avant === $apres) {
            return $data;
        }

        // Retirés du dossier compris : la case a porté sur ces lignes-là aussi.
        $emis = $record->ordresService()->count();
        if ($emis === 0) {
            return $data;
        }

        $message = sprintf(
            '« %s » est %s : ce type a déjà servi à émettre %s ordre(s) de service, et la case décide '
                . 'de la portée de ces lignes-là. %s Ce qui se corrige encore : le libellé, la description, '
                . 'l\'ordre, et le fait qu\'il soit proposé ou non.',
            $record->libelle,
            $avant ? 'un type de démarrage' : 'un type qui ne démarre rien',
            $emis,
            $avant
                ? 'Le retirer effacerait l\'acte qui a ouvert l\'exécution des marchés concernés.'
                : 'Le poser ferait de ces ordres déjà émis des actes de démarrage rétroactifs.',
        );

        Notification::make()->title('❌ Règle refusée')->body($message)
            ->danger()->persistent()->send();

        throw ValidationException::withMessages(['demarre_lexecution' => $message]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn (TypeOrdreService $record) => TypeOrdreServiceResource::refuserSuppression($record)),
        ];
    }
}
