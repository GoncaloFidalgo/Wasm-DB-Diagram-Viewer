<?php

namespace App\Filament\Resources\Diagrams\Schemas;

use App\Filament\Resources\Diagrams\Pages\CreateDiagram;
use Closure;
use Filament\Forms\Components\ViewField;
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
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ExtractForm
{
    /**
     * Schema para a conexão e extração da BD
     */
    public static function connectionSchema(): array
    {
        return [
            Select::make('engine')
                ->label('Motor de Base de Dados')
                ->options([
                    'sqlite' => 'SQLite',
                    'mysql' => 'MySQL',
                ])
                ->default('sqlite')
                ->live()
                ->afterStateUpdated(function ($livewire) {
                    $livewire->extractedTables = [];
                })
                ->required(),

            ViewField::make('filePath')
                ->label('Ficheiro SQLite (.sqlite, .db)')
                ->view('filament.forms.components.custom-sqlite-upload')
                ->required(fn(Get $get) => $get('engine') === 'sqlite')
                ->validationMessages([
                    'required' => 'Carregue um ficheiro .sqlite ou .db para prosseguir.',
                ])
                ->visible(fn(Get $get) => $get('engine') === 'sqlite')
                ->live()
                ->afterStateUpdated(function ($state, $livewire) {
                    if (!$state) {
                        $livewire->extractedTables = [];
                        return;
                    }

                    $livewire->extractedTables = [];
                }),
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
                            ->autocomplete('off')
                            ->required(fn(Get $get) => $get('engine') === 'mysql'),

                        TextInput::make('mysql_password')
                            ->label('Password')
                            ->password()
                            ->autocomplete('new-password'),
                ]),
        ];
    }

    /**
     * Schema para os detalhes do diagrama e as tabelas
     */
    public static function detailsSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->label('Nome do Diagrama')
                    ->placeholder('Ex: Base de dados loja de tshirts')
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
                ->label('Selecione as tabelas para o diagrama')
                ->options(fn($livewire) => empty($livewire->extractedTables) ? [] : array_combine($livewire->extractedTables, $livewire->extractedTables))
                ->columns(3)
                ->gridDirection('column')
                ->bulkToggleable()
                ->searchable()
                ->required()
                ->validationMessages([
                    'required' => 'Tem de selecionar pelo menos uma tabela.',
                ]),

        ];
    }

}
