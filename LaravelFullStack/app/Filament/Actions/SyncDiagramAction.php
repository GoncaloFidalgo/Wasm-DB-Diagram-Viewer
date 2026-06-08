<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Diagrams\Schemas\ExtractForm;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class SyncDiagramAction
{
    public static function make(): Action
    {
        return Action::make('sync')
            ->label('Sincronizar')
            ->icon('heroicon-m-arrow-path')
            ->color('info')
            ->visible(fn ($livewire) => !$livewire->isPublished)
            ->extraAttributes([
                'x-on:click' => '$dispatch("trigger-rust-sync")',
            ])
            ->mountUsing(function ($livewire, $form) {
                $livewire->extractedTables = [];
                if (property_exists($livewire, 'fullExtractedData')) {
                    $livewire->fullExtractedData = [];
                }
                // Para selecionar logo a opção sqlite, o default não funciona pois o form é carregado na modal e não quando a página carrega
                $form->fill([
                    'engine' => 'sqlite',
                ]);
            })

            ->modalHeading('Sincronizar Base de Dados')
            ->modalDescription('As novas tabelas serão adicionadas ao diagrama atual. As tabelas desmarcadas serão apagadas.')
            ->modalSubmitActionLabel('Aplicar Sincronização')
            ->schema([
                ...ExtractForm::connectionSchema(),

                Actions::make([
                    Action::make('extractForSync')
                        ->label('Extrair Tabelas')
                        ->action(fn ($livewire, Get $get, Set $set, $component) => $livewire->processSyncExtraction($get, $set, $component))
                ])->fullWidth(),

                Section::make('Tabelas Extraídas')
                    ->visible(fn ($livewire) => !empty($livewire->extractedTables))
                    ->schema([
                        CheckboxList::make('selectedTables')
                            ->hiddenLabel()
                            ->options(fn($livewire) => empty($livewire->extractedTables) ? [] : array_combine($livewire->extractedTables, $livewire->extractedTables))
                            ->columns(3)
                            ->gridDirection('column')
                            ->bulkToggleable()
                            ->searchable()
                    ]),
            ])
            ->action(function (array $data, $livewire) {

                $livewire->performSyncMerge($data['selectedTables'], $data);
            });
    }
}
