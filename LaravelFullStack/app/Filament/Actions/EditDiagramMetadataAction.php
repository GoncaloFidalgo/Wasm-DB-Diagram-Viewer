<?php

namespace App\Filament\Actions;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\Diagram;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class EditDiagramMetadataAction
{
    public static function configure($action)
    {
        return $action
        ->icon(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'heroicon-m-pencil-square' : 'heroicon-m-information-circle')
        ->tooltip(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'Editar Detalhes' : 'Ver Detalhes')
        ->modalHeading(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'Editar Detalhes do Diagrama' : 'Detalhes do Diagrama')
        ->modalWidth('md')

        // Esconde o botão "Guardar" se for apenas leitura
        ->modalSubmitAction(fn ($action, $livewire, $record = null) => self::canEdit($livewire, $record) ? $action->label('Guardar') : false)
        ->modalCancelActionLabel(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'Cancelar' : 'Fechar')

        ->form([
            TextInput::make('name')
                ->label('Nome do Diagrama')
                ->required()
                ->maxLength(255)
                // Bloqueia a edição se não for o dono ou se já estiver publicado
                ->disabled(fn ($livewire, $record = null) => !self::canEdit($livewire, $record)),

            Textarea::make('description')
                ->label('Descrição')
                ->placeholder('Adicione uma breve descrição sobre este diagrama...')
                ->rows(3)
                ->maxLength(1000)
                ->disabled(fn ($livewire, $record = null) => !self::canEdit($livewire, $record)),

            Section::make('Estatísticas')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('tables_count')
                                ->label('Tabelas')
                                ->disabled(),
                            TextInput::make('columns_count')
                                ->label('Colunas')
                                ->disabled(),
                            TextInput::make('relations_count')
                                ->label('Relações')
                                ->disabled(),
                        ]),
                ])->compact(),

            Section::make('Histórico')
                ->schema([
                    TextInput::make('created_at')
                        ->label('Data de Criação')
                        ->disabled()
                        ->inlineLabel(),

                    TextInput::make('published_count')
                        ->label('Total de Publicações')
                        ->disabled()
                        ->inlineLabel(),

                    TextInput::make('last_published_at')
                        ->label('Última Publicação')
                        ->disabled()
                        ->inlineLabel(),
                ])->compact(),
        ])
        ->fillForm(function ($livewire, $record = null) {
            $diagram = $record ?? Diagram::find($livewire->recordId);
            if (!$diagram) return [];

            $baseQuery = Diagram::where('diagram_id', $diagram->diagram_id);
            $createdAt = (clone $baseQuery)->min('created_at');

            $publishedCount = (clone $baseQuery)->where('is_published', true)->count();
            $lastPublishedAt = (clone $baseQuery)->where('is_published', true)->max('published_at');


            $schema = $diagram->diagram ?? [];

            $tablesCount = isset($schema['tables']) ? count($schema['tables']) : 0;
            $relationsCount = isset($schema['relations']) ? count($schema['relations']) : 0;
            $columnsCount = 0;


            if (isset($schema['tables'])) {
                foreach ($schema['tables'] as $table) {
                    $columnsCount += isset($table['columns']) ? count($table['columns']) : 0;
                }
            }

            return [
                'name' => $diagram->name,
                'description' => $diagram->description,
                // Dados Estruturais
                'tables_count' => $tablesCount,
                'columns_count' => $columnsCount,
                'relations_count' => $relationsCount,
                // Dados Históricos
                'created_at' => $createdAt ? \Carbon\Carbon::parse($createdAt)->timezone('Europe/Lisbon')->format('d/m/Y - H:i') : 'Desconhecida',
                'published_count' => $publishedCount . ' ' . ($publishedCount === 1 ? 'versão' : 'versões'),
                'last_published_at' => $lastPublishedAt ? \Carbon\Carbon::parse($lastPublishedAt)->timezone('Europe/Lisbon')->format('d/m/Y - H:i') : 'Nenhuma',
            ];
        })
            ->action(function (array $data, $livewire, $record = null, $component = null) {

                if (!self::canEdit($livewire, $record)) return;

                $diagramId = $record ? $record->id : $livewire->recordId;

                Diagram::where('id', $diagramId)->update([
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]);

                if ($component) {
                    $component->state($data['name']);
                }

                Notification::make()
                    ->title('Detalhes guardados com sucesso!')
                    ->success()
                    ->send();
            });
    }
    private static function canEdit($livewire, $record): bool
    {
        $diagram = $record ?? Diagram::find($livewire->recordId);
        if (!$diagram) return false;

        $isOwner = auth()->check() && auth()->id() === $diagram->user_id;

        return $isOwner && !$diagram->is_published;
    }
}
