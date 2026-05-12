<?php

namespace App\Filament\Resources\Diagrams\Pages;

use App\Filament\Resources\Diagrams\DiagramResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'Os meus diagramas' => Tab::make()
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('user_id', auth()->id())
                        // Filtra para manter apenas a última versão de cada diagrama deste user
                        ->where('version', function ($subquery) {
                            $subquery->select(DB::raw('MAX(version)'))
                                ->from('diagrams as d2')
                                ->whereColumn('d2.diagram_id', 'diagrams.diagram_id');
                        });
                }),

            'Públicos' => Tab::make()
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('is_published', true)
                        ->where('visibility', 'public')
                        ->where('version', function ($subquery) {
                            $subquery->select(DB::raw('MAX(version)'))
                                ->from('diagrams as d2')
                                ->whereColumn('d2.diagram_id', 'diagrams.diagram_id')
                                ->where('d2.is_published', true)
                                ->where('d2.visibility', 'public');
                        });
                }),
        ];
    }
}
