<?php

namespace App\Filament\Actions;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\Diagram;
use Filament\Notifications\Notification;

class EditDiagramMetadataAction
{
    public static function configure($action)
    {
        return $action
            ->icon('heroicon-m-pencil')
            ->modalHeading('Editar Detalhes do Diagrama')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Guardar')
            ->form([
                TextInput::make('name')
                    ->label('Nome do Diagrama')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Descrição')
                    ->placeholder('Adicione uma breve descrição sobre este diagrama...')
                    ->rows(4)
                    ->maxLength(1000),
            ])
            ->fillForm(function ($livewire, $record = null) {
                    // Se vier da tabela usa o $record, se vier do editor usa o recordId do componente
                $diagram = $record ?? Diagram::find($livewire->recordId);

                return [
                    'name' => $diagram?->name,
                    'description' => $diagram?->description,
                ];
            })
            ->action(function (array $data, $livewire, $record = null, $component = null) {
                $diagramId = $record ? $record->id : $livewire->recordId;

                Diagram::where('id', $diagramId)->update([
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]);

               // Se houver um componente de formulário atrelado, atualiza o texto no ecrã
                if ($component) {
                    $component->state($data['name']);
                }

                Notification::make()
                    ->title('Detalhes guardados com sucesso!')
                    ->success()
                    ->send();
            });
    }
}
