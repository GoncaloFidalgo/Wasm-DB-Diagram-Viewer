<?php

namespace App\Filament\Resources\Diagrams;

use App\Filament\Resources\Diagrams\Pages\CreateDiagram;
use App\Filament\Resources\Diagrams\Pages\EditDiagram;
use App\Filament\Resources\Diagrams\Pages\Extract;
use App\Filament\Resources\Diagrams\Pages\ExtractDatabase;
use App\Filament\Resources\Diagrams\Pages\ListDiagrams;
use App\Filament\Resources\Diagrams\Schemas\DiagramForm;
use App\Filament\Resources\Diagrams\Tables\DiagramsTable;
use App\Models\Diagram;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiagramResource extends Resource
{
    protected static ?string $model = Diagram::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #region Labels
    protected static ?string $modelLabel = 'Diagrama'; // Editar/Apagar
    protected static ?string $pluralModelLabel = 'Diagramas'; // Navegação/Listagem
    protected static bool $hasTitleCaseModelLabel = false; // Maiuscula na primeira letra de cada palavra
    protected static ?string $navigationLabel = 'Diagramas';

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

    public static function getPages(): array
    {
        return [
            'index' => ListDiagrams::route('/'),
            'extract' => Extract::route('/extrair'),
        ];
    }
}
