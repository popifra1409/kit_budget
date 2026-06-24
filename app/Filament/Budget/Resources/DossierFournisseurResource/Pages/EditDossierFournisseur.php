<?php

namespace App\Filament\Budget\Resources\DossierFournisseurResource\Pages;

use App\Filament\Budget\Resources\DossierFournisseurResource;
use App\Models\PieceDossier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditDossierFournisseur extends EditRecord
{
    protected static string $resource = DossierFournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    /**
     * ✅ Interception après sauvegarde — traitement des pièces manuelles
     */
    protected function afterSave(): void
    {
        $data            = $this->form->getRawState();
        $nouvellesPieces = $data['nouvelles_pieces'] ?? [];

        foreach ($nouvellesPieces as $pieceData) {
            if (empty($pieceData['type_piece']) || empty($pieceData['libelle'])) continue;

            PieceDossier::create([
                'dossier_fournisseur_id' => $this->record->id,
                'type_piece'             => $pieceData['type_piece'],
                'source'                 => 'manuelle',
                'libelle'                => $pieceData['libelle'],
                'fichier_upload'         => $pieceData['fichier_upload'] ?? null,
                'nom_fichier'            => $pieceData['fichier_upload'] ?? $pieceData['libelle'],
                'chemin_fichier'         => $pieceData['fichier_upload'] ?? '',
                'observations'           => $pieceData['observations'] ?? null,
                'valide'                 => false, // En attente de validation
                'document_type'          => null,
                'document_id'            => null,
            ]);
        }

        if (!empty($nouvellesPieces)) {
            Notification::make()
                ->title('✅ ' . count($nouvellesPieces) . ' pièce(s) ajoutée(s) au dossier')
                ->success()->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
