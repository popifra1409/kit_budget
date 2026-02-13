<?php

namespace App\Filament\Resources\BordereauEngagementResource\Pages;

use App\Filament\Resources\BordereauEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBordereauEngagements extends ListRecords
{
    protected static string $resource = BordereauEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau bordereau')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Onglets de filtrage par statut
     */
    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Tous')
                ->badge(fn() => \App\Models\BordereauEngagement::count()),

            'brouillon' => Tab::make('Brouillons')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('statut', 'brouillon'))
                ->badge(fn() => \App\Models\BordereauEngagement::where('statut', 'brouillon')->count())
                ->badgeColor('gray'),

            'transmis' => Tab::make('Transmis')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('statut', 'transmis'))
                ->badge(fn() => \App\Models\BordereauEngagement::where('statut', 'transmis')->count())
                ->badgeColor('info'),

            'en_cours' => Tab::make('En cours')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('statut', 'en_cours'))
                ->badge(fn() => \App\Models\BordereauEngagement::where('statut', 'en_cours')->count())
                ->badgeColor('warning'),

            'valide' => Tab::make('Validés')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('statut', 'valide'))
                ->badge(fn() => \App\Models\BordereauEngagement::where('statut', 'valide')->count())
                ->badgeColor('success'),

            'rejete' => Tab::make('Rejetés')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereIn('statut', ['rejete_total', 'rejete_partiel']))
                ->badge(fn() => \App\Models\BordereauEngagement::whereIn('statut', ['rejete_total', 'rejete_partiel'])->count())
                ->badgeColor('danger'),

            'mes_bordereaux' => Tab::make('Mes bordereaux')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('emis_par', auth()->id()))
                ->badge(fn() => \App\Models\BordereauEngagement::where('emis_par', auth()->id())->count())
                ->badgeColor('primary'),

            'detenus' => Tab::make('Détenus par moi')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('detenu_par_id', auth()->id()))
                ->badge(fn() => \App\Models\BordereauEngagement::where('detenu_par_id', auth()->id())->count())
                ->badgeColor('warning'),
        ];
    }

    /**
     * Ordre par défaut
     */
    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->orderBy('created_at', 'desc');
    }
}
