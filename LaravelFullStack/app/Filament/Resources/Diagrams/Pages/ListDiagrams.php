<?php

namespace App\Filament\Resources\Diagrams\Pages;

use App\Filament\Resources\Diagrams\DiagramResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListDiagrams extends ListRecords
{
    protected static string $resource = DiagramResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Gerar Diagrama')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => DiagramResource::getUrl('create')),
        ];
    }
    public function getTabs(): array
    {
        return [
            'Os meus diagramas' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', Auth::id())),
            'Todos' => Tab::make(),
        ];
    }
}
