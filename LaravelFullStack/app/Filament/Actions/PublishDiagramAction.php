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
            //->label(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'Definições de Partilha' : 'Partilhar diagrama')
            ->label('Definições de Partilha')
            //->icon(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'heroicon-m-cog-8-tooth' : 'heroicon-m-share')
            ->icon('heroicon-m-share')

//            ->modalHeading(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'Definições de Partilha' : 'Partilhar Diagrama')
            ->modalHeading('Definições de Partilha')
            ->modalDescription('Define quem pode visualizar este diagrama e as suas versões.')
            //->modalSubmitActionLabel(fn ($record, $livewire) => self::getDiagram($record, $livewire)?->is_published ? 'Guardar' : 'Partilhar')
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth('md')
            ->visible(function ($record, $livewire) {
                $mainDiagram = self::getMainDiagram($record, $livewire);

                $isOwner = auth()->check() && auth()->id() === $mainDiagram?->user_id;

                return $mainDiagram && $mainDiagram->is_published && $isOwner;
            })
            // Preenchemos o formulário com os dados da base de dados e geramos o link real
            ->fillForm(function ($record, $livewire) {
                $mainDiagram = self::getMainDiagram($record, $livewire);
                return [
                    'visibility' => $mainDiagram?->visibility ?? 'private',
                    'share_link' => url('/diagram/' . $mainDiagram?->diagram_id),
                ];
            })
            ->schema([
                Radio::make('visibility')
                    ->label('Visibilidade Geral')
                    ->options([
                        'public' => 'Público',
                        //'link' => 'Apenas com o link',
                        'private' => 'Privado',
                    ])
                    ->descriptions([
                        'public' => 'Qualquer utilizador pode encontrar e visualizar.',
                        //'link' => 'Apenas utilizadores com o link direto podem visualizar.',
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
                $mainDiagram = self::getMainDiagram($record, $livewire);

                if ($mainDiagram) {
                    // Aplica a visibilidade a TODAS as versões deste diagrama
                    Diagram::where('diagram_id', $mainDiagram->diagram_id)->update([
                        'visibility' => $data['visibility'],
                    ]);
                }

                Notification::make()
                    ->title('Definições guardadas com sucesso!')
                    ->success()
                    ->send();
            });
    }

    private static function getMainDiagram($record, $livewire): ?Diagram
    {
        $currentDiagram = null;

        if ($record instanceof Diagram) {
            $currentDiagram = $record;
        } elseif (isset($livewire->recordId)) {
            $currentDiagram = Diagram::find($livewire->recordId);
        }

        if (!$currentDiagram) return null;

        return Diagram::where('diagram_id', $currentDiagram->diagram_id)
            ->where('version', 1)
            ->first();
    }
}
