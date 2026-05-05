<?php

namespace App\Filament\Resources\Diagrams\Schemas;

use App\Filament\Resources\Diagrams\Pages\CreateDiagram;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\CheckboxList;

class ExtractForm
{
    /**
     * Schema para a conexão e extração da BD
     */
    public static function connectionSchema(): array
    {
        return [
            Section::make('1. Dados da Conexão')
                ->schema([
                    Select::make('engine')
                        ->label('Motor de Base de Dados')
                        ->options([
                            'sqlite' => 'SQLite',
                            'mysql' => 'MySQL',
                        ])
                        ->default('sqlite')
                        ->live()
                        ->afterStateUpdated(function (CreateDiagram $livewire) {
                            $livewire->extractedTables = [];
                        })
                        ->required(),

                    FileUpload::make('filePath')
                        ->label('Ficheiro SQLite (.sqlite, .db)')
                        ->disk('local')
                        ->directory('sqlite-uploads')
                        ->preserveFilenames()
                        ->required(fn(Get $get) => $get('engine') === 'sqlite')
                        ->visible(fn(Get $get) => $get('engine') === 'sqlite')
                        ->live()
                        ->afterStateUpdated(fn(CreateDiagram $livewire) => $livewire->extractedTables = []),

                    Grid::make(2)
                        ->visible(fn(Get $get) => $get('engine') === 'mysql')
                        ->schema([
                            TextInput::make('mysql_host')
                                ->label('Host')
                                ->default('')
                                ->required(fn(Get $get) => $get('engine') === 'mysql'),
                            TextInput::make('mysql_port')
                                ->label('Porta')
                                ->default('')
                                ->required(fn(Get $get) => $get('engine') === 'mysql'),
                            TextInput::make('mysql_database')
                                ->label('Base de Dados')
                                ->columnSpan(2)
                                ->required(fn(Get $get) => $get('engine') === 'mysql'),
                            TextInput::make('mysql_username')
                                ->label('Utilizador')
                                ->required(fn(Get $get) => $get('engine') === 'mysql'),
                            TextInput::make('mysql_password')
                                ->label('Password')
                                ->password(),
                        ]),

                    Actions::make([
                        Action::make('extract')
                            ->label('Extrair Tabelas')
                            ->size('lg')
                            ->action(fn(CreateDiagram $livewire) => $livewire->processExtraction())
                    ])->fullWidth(),
                ]),
        ];
    }

    /**
     * Schema para os detalhes do diagrama e as tabelas
     */
    public static function detailsSchema(): array
    {
        return [
            Section::make('2. Detalhes e Tabelas')
                ->visible(fn (CreateDiagram $livewire) => !empty($livewire->extractedTables))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Nome do Diagrama')
                            ->placeholder('Ex: Base de Dados Chinook')
                            ->required()
                            ->validationMessages([
                                'required' => 'O nome do diagrama é obrigatório.',
                            ])
                            ->maxLength(255)
                            ->columnSpan(1),

                        Textarea::make('description')
                            ->label('Descrição (Opcional)')
                            ->placeholder('Breve descrição sobre o propósito deste diagrama...')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpan(1),
                    ]),

                    CheckboxList::make('selectedTables')
                        ->label('Selecione as Tabelas para o Diagrama')
                        ->options(fn(CreateDiagram $livewire) => empty($livewire->extractedTables) ? [] : array_combine($livewire->extractedTables, $livewire->extractedTables))
                        ->columns(3)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->searchable()
                        ->required()
                        ->validationMessages([
                            'required' => 'Tem de selecionar pelo menos uma tabela.',
                        ]),

                    Actions::make([
                        Action::make('open')
                            ->label('Gerar Diagrama')
                            ->color('success')
                            ->size('lg')
                            //->icon('heroicon-m-arrow-right-circle')
                            ->action(fn(CreateDiagram $livewire) => $livewire->openDiagram())
                    ])->fullWidth(),
                ]),
        ];
    }
}
