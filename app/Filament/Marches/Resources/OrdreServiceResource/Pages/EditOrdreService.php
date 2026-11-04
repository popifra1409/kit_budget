<?php

namespace App\Filament\Marches\Resources\OrdreServiceResource\Pages;

use App\Filament\Marches\Resources\OrdreServiceResource;
use App\Models\OrdreService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

/**
 * La retouche d'un ordre de service déjà émis : sa date, son objet, sa pièce jointe,
 * ses observations. Le marché et le type sont fermés — l'un porte le cycle que l'acte
 * a fait avancer, l'autre la règle de cet effet.
 *
 * Le contrôle de l'antériorité se rejoue ici : le modèle ne l'impose qu'à la création
 * (hook `creating` d'OrdreService), alors que la date, elle, se corrige. Un ordre de
 * service re-daté avant l'homologation de son marché daterait le point de départ du
 * délai d'exécution n'importe comment.
 */
class EditOrdreService extends EditRecord
{
    protected static string $resource = OrdreServiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;
        $marche = $record instanceof OrdreService ? $record->marche : null;

        if (!$marche || empty($data['date_emission']) || !$marche->date_homologation) {
            return $data;
        }

        $date = \Illuminate\Support\Carbon::parse($data['date_emission']);
        if ($date->lt($marche->date_homologation->toDateString())) {
            $message = sprintf(
                'La date d\'émission (%s) est antérieure à l\'homologation du marché %s (%s) : un ordre '
                    . 'de service exécute une attribution, il ne la précède pas.',
                $date->format('d/m/Y'),
                $marche->numero,
                $marche->date_homologation->format('d/m/Y'),
            );

            Notification::make()->title('❌ Date refusée')->body($message)
                ->danger()->persistent()->send();

            throw ValidationException::withMessages(['date_emission' => $message]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn (OrdreService $record) => OrdreServiceResource::refuserRetrait($record)),
        ];
    }

    public function getSubheading(): ?string
    {
        $record = $this->record;

        return $record instanceof OrdreService
            ? ($record->type?->libelle ?? '—') . ' — ' . ($record->marche?->numero ?? '—')
                . ' · notifié le ' . ($record->date_emission?->format('d/m/Y') ?? '—')
                . ' par ' . ($record->emetteur?->name ?? '—')
            : null;
    }
}
