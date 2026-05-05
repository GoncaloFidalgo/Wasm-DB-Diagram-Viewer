<?php

namespace App\Filament\Resources\Diagrams\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiagramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->sortable(),

                TextColumn::make('version')
                    ->label('Versão')
                    ->formatStateUsing(fn(string $state): string => 'v' . $state)
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data de criação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('is_published')
                    ->label('Publicado')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sim' : 'Não')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->recordActions([
                //EditAction::make(),
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->button()
                    ->url(fn ($record) => '/diagram/' . $record->diagram_id . '?v=' . $record->version),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Ainda não tem diagramas')
            ->emptyStateDescription('Gere o seu primeiro diagrama para o ver aqui.');
    }
}
