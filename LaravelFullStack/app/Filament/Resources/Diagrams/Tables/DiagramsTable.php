<?php

namespace App\Filament\Resources\Diagrams\Tables;

use App\Filament\Actions\EditDiagramMetadataAction;
use App\Filament\Actions\PublishDiagramAction;
use App\Models\Diagram;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                    ->searchable()
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
                ActionGroup::make([
                    PublishDiagramAction::make(),
                    Action::make('delete_all')
                        ->label('Eliminar')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Diagrama')
                        ->modalDescription('Tem a certeza que deseja eliminar este diagrama e TODAS as suas versões? Esta ação é irreversível.')
                        ->modalSubmitActionLabel('Sim, eliminar tudo')
                        ->action(function ($record) {
                            Diagram::where('diagram_id', $record->diagram_id)->delete();

                            Notification::make()
                                ->title('Diagrama eliminado')
                                ->body('Todas as versões foram removidas com sucesso.')
                                ->success()
                                ->send();
                        }),
                    EditDiagramMetadataAction::configure(
                        Action::make('edit_metadata')->label('Editar dados')
                    ),
                ]),
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->button()
                    ->url(fn ($record) => '/diagram/' . $record->diagram_id . '?v=' . $record->version . '&source=mine'),
            ])
            ->searchPlaceholder('Pesquisar pelo nome')

            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Ainda não tem diagramas')
            ->emptyStateDescription('Gere o seu primeiro diagrama para o ver aqui.');
    }
}
