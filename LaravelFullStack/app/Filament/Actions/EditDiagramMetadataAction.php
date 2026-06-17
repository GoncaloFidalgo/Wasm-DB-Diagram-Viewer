<?php

namespace App\Filament\Actions;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Models\Diagram;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;

class EditDiagramMetadataAction
{
    public static function configure($action)
    {
        return $action
            ->icon(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'heroicon-m-pencil-square' : 'heroicon-m-information-circle')
            ->tooltip(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'Editar Detalhes' : 'Ver Detalhes')
            ->modalHeading(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'Editar Detalhes do Diagrama' : 'Detalhes do Diagrama')
            ->modalWidth('md')

            ->modalSubmitAction(fn ($action, $livewire, $record = null) => self::canEdit($livewire, $record) ? $action->label('Guardar') : false)
            ->modalCancelActionLabel(fn ($livewire, $record = null) => self::canEdit($livewire, $record) ? 'Cancelar' : 'Fechar')
            ->form([
//                Placeholder::make('created_at')
//                    ->label('Data de Criação')
//                    ->inlineLabel()
//                    ->content(fn ($get) => $get('created_at')),
//
//                Placeholder::make('published_count')
//                    ->label('Total de Publicações')
//                    ->inlineLabel()
//                    ->content(fn ($get) => $get('published_count')),
//
//                Placeholder::make('last_published_at')
//                    ->label('Última Publicação')
//                    ->inlineLabel()
//                    ->content(fn ($get) => $get('last_published_at')),

                TextInput::make('name')
                    ->label('Nome do Diagrama')
                    ->required()
                    ->maxLength(255)
            ->disabled(fn ($livewire, $record = null) => !self::canEdit($livewire, $record)),

                Textarea::make('description')
                    ->label('Descrição')
                    ->placeholder('Adicione uma breve descrição sobre este diagrama...')
                    ->rows(4)
                    ->maxLength(1000)
                    ->disabled(fn ($livewire, $record = null) => !self::canEdit($livewire, $record)),

            ])
            ->fillForm(function ($livewire, $record = null) {
                $diagram = $record ?? Diagram::find($livewire->recordId);
                if (!$diagram) return [];

                $baseQuery = Diagram::where('diagram_id', $diagram->diagram_id);

                $createdAt = (clone $baseQuery)->min('created_at');
                $publishedCount = (clone $baseQuery)->where('is_published', 'true')->count();
                $lastPublishedAt = (clone $baseQuery)->where('is_published', 'true')->max('published_at');

                return [
                    'name' => $diagram->name,
                    'description' => $diagram->description,
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
    private static function canEdit($livewire, $record): bool
    {
        $diagram = $record ?? Diagram::find($livewire->recordId);
        if (!$diagram) return false;

        $isOwner = auth()->check() && auth()->id() === $diagram->user_id;

        return $isOwner && !$diagram->is_published;
    }
}
