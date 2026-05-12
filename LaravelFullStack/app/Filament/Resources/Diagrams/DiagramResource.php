<?php

namespace App\Filament\Resources\Diagrams;

use App\Filament\Resources\Diagrams\Pages\CreateDiagram;
use App\Filament\Resources\Diagrams\Pages\ListDiagrams;
use App\Filament\Resources\Diagrams\Schemas\DiagramForm;
use App\Filament\Resources\Diagrams\Tables\DiagramsTable;
use App\Models\Diagram;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DiagramResource extends Resource
{
    protected static ?string $model = Diagram::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    //protected static bool $shouldRegisterNavigation = false;
    #region Labels
    protected static ?string $modelLabel = 'Diagrama'; // Editar/Apagar
    protected static ?string $pluralModelLabel = 'Os meus Diagramas'; // Navegação/Listagem
    protected static bool $hasTitleCaseModelLabel = false; // Maiuscula na primeira letra de cada palavra
    protected static ?string $navigationLabel = 'Os meus Diagramas';
    protected static ?string $slug = 'meus-diagramas';
    #endregion

    public static function form(Schema $schema): Schema
    {
        return DiagramForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiagramsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->where('version', function ($subquery) {
                $subquery->select(DB::raw('MAX(version)'))
                    ->from('diagrams as d2')
                    ->whereColumn('d2.diagram_id', 'diagrams.diagram_id');
            });
    }
    public static function getPages(): array
    {
        return [
            'index' => ListDiagrams::route('/'),
            'create' => CreateDiagram::route('/criar'),
        ];
    }
}
