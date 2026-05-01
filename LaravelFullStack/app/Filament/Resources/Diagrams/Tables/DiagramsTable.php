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
                TextColumn::make('version')
                    ->label('Versão')
                    ->formatStateUsing(fn(string $state): string => 'v' . $state)
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data de Gravação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('open')
                    ->label('Abrir Diagrama')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->button()
                    ->url(fn ($record) => '/diagram/' . $record->diagram_id),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Ainda não tem diagramas')
            ->emptyStateDescription('Extraia uma base de dados e grave o seu primeiro diagrama para o ver aqui.');
    }
}
