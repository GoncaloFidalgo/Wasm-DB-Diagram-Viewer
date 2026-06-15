<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use App\Models\Diagram;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

class PublishDiagramAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->label(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'Definições de Partilha' : 'Publicar')
            ->icon(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'heroicon-m-cog-8-tooth' : 'heroicon-m-share')
           //->color(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'info' : 'success')

            ->modalHeading(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'Definições de Partilha' : 'Publicar Diagrama')
            ->modalDescription('Define quem pode visualizar este diagrama e as suas versões.')
            ->modalSubmitActionLabel(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'Guardar' : 'Publicar')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth('md')

            // Preenchemos o formulário com os dados da base de dados e geramos o link real
            ->fillForm(function ($record, $livewire) {
                $diagram = self::getDiagram($record, $livewire);
                return [
                    'visibility' => $diagram?->visibility ?? 'link',
                    'share_link' => url('/diagram/' . $diagram?->diagram_id), // Gera o link completo
                ];
            })
            ->schema([
                Radio::make('visibility')
                    ->label('Visibilidade Geral')
                    ->options([
                        'public' => 'Público',
                        'link' => 'Apenas com o link',
                        'private' => 'Privado',
                    ])
                    ->descriptions([
                        'public' => 'Qualquer utilizador pode encontrar e visualizar.',
                        'link' => 'Apenas utilizadores com o link direto podem visualizar.',
                        'private' => 'Ninguém além de ti pode ver este diagrama.',
                    ])
                    ->required()
                    ->live(), // Essencial para o TextInput abaixo detetar as mudanças em tempo real

                TextInput::make('share_link')
                    ->label('Link de Partilha')
                    ->readOnly()
                    ->visible(fn (Get $get) => $get('visibility') !== 'private')
                    ->suffixAction(
                        Action::make('copy')
                            ->icon('heroicon-m-clipboard-document')
                            ->tooltip('Copiar link')
                            ->action(function ($livewire, $state) {
                                // O Livewire executa JavaScript no browser para copiar para o Clipboard
                                $livewire->js('navigator.clipboard.writeText("' . addslashes($state) . '");');

                                Notification::make()
                                    ->title('Link copiado para a área de transferência!')
                                    ->success()
                                    ->send();
                            })
                    ),
            ])
            ->action(function (array $data, $record, $livewire) {
                $diagram = self::getDiagram($record, $livewire);
                $isNewlyPublished = false;
                if ($diagram) {
                    // Aplica a visibilidade a todas as versões deste diagrama (mesmo diagram_id)
                    Diagram::where('diagram_id', $diagram->diagram_id)->update([
                        'visibility' => $data['visibility'],
                    ]);

                    // Se a versão que estamos a mexer ainda NÃO for publicada, publicamos e bloqueamos a edição
                    if (!$diagram->is_published) {
                        $diagram->update(['is_published' => true]);
                        $isNewlyPublished = true;
                    }
                }

                Notification::make()
                    ->title(self::getDiagram($record, $livewire)?->is_published ? 'Definições guardadas' : 'Diagrama publicado com sucesso')
                    ->success()
                    ->send();

                if (isset($livewire->recordId)) {
                    $livewire->isPublished = true;

                    if ($isNewlyPublished && $diagram) {
                        $livewire->dispatch('reload-wasm-schema',
                            schema: json_encode($diagram->diagram),
                            isReadOnly: true,
                            hasUnsavedChanges: false
                        );
                    }
                }
            });
    }

    // Helper interno blindado para apanhar o Diagrama, venha ele da Tabela ou do Visualizador Livewire
    private static function getDiagram($record, $livewire): ?Diagram
    {
        if ($record instanceof Diagram) {
            return $record;
        }
        if (isset($livewire->recordId)) {
            return Diagram::find($livewire->recordId);
        }
        return null;
    }
}
