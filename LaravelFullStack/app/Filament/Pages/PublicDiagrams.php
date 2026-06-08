<?php

namespace App\Filament\Pages;

use App\Models\Diagram;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\Filter; // <-- Add this import
use Illuminate\Database\Eloquent\Builder; // <-- Add this import
use Illuminate\Support\Facades\DB;

class PublicDiagrams extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Diagramas Públicos';
    protected static ?string $title = 'Explorar Diagramas Públicos';
    protected static ?string $slug = 'explorar-diagramas';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.public-diagrams';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Diagram::query()
                    ->where('is_published', true)
                    ->where('visibility', 'public')
                    ->where('version', function ($subquery) {
                        $subquery->select(DB::raw('MAX(version)'))
                            ->from('diagrams as d2')
                            ->whereColumn('d2.diagram_id', 'diagrams.diagram_id')
                            ->where('d2.is_published', true)
                            ->where('d2.visibility', 'public');
                    })
            )
            ->searchPlaceholder('Pesquisar pelo nome')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Autor')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Publicado em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('hide_mine')
                    ->label('Ocultar os meus diagramas')
                    ->toggle()
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query->where('user_id', '!=', auth()->id())),
            ])
            // ---------------------------
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->button()
                    ->url(fn($record) => '/diagram/' . $record->diagram_id . '?v=' . $record->version . '&source=public'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhum diagrama público disponível');
    }
}
