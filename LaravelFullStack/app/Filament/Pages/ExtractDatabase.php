<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExtractorService;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\CheckboxList; // <-- NEW IMPORT

class ExtractDatabase extends Page implements HasForms
{
    use InteractsWithForms;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Extrair Base de Dados';
    protected static ?string $slug = 'extract';
    protected string $view = 'filament.pages.extract-database';

    public ?array $data = [];
    public Collection $extractedTables;
    public ?string $rawJsonSchema = null;
    public function mount()
    {
        $this->extractedTables = collect();

        $this->form->fill([
            'filePath' => database_path('chinook.db'),
            'selectedTables' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Grid::make()
                        ->schema([
                            Section::make('Dados da Conexão')
                                ->columnSpan(1)
                                ->schema([
                                    TextInput::make('filePath')
                                        ->label('Caminho do ficheiro SQLite')
                                        ->helperText('Insira o caminho absoluto para o ficheiro .sqlite ou .db no servidor.')
                                        ->required(),

                                    Actions::make([
                                        Action::make('extract')
                                            ->label('Extrair Tabelas')
                                            ->size('lg')
                                            ->action(function (Get $get, Set $set) {
                                                $path = $get('filePath');
                                                $this->processExtraction($path, $set);
                                            })
                                    ])->fullWidth(),
                                ]),

                            Section::make('Selecione as Tabelas')
                                ->columnSpan(1)
                                ->schema([

                                    Placeholder::make('empty_state')
                                        ->hiddenLabel()
                                        ->content(new HtmlString('
                                            <div class="flex flex-col items-center justify-center text-center py-8 opacity-50">
                                                <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="w-12 h-12 mb-4" />
                                                <h3 class="text-lg font-medium">Nenhum dado extraído</h3>
                                            </div>
                                        '))
                                        ->visible(fn () => $this->extractedTables->isEmpty()),

                                    CheckboxList::make('selectedTables')
                                        ->hiddenLabel()
                                        ->options(fn () => $this->extractedTables->mapWithKeys(fn($table) => [$table => $table])->toArray())
                                        ->columns()
                                        ->gridDirection('row')
                                        ->bulkToggleable()
                                        ->searchable()
                                        ->visible(fn () => $this->extractedTables->isNotEmpty()),

                                    Actions::make([
                                        Action::make('open')
                                            ->label('Abrir Visualizador do Diagrama')
                                            ->color('success')
                                            ->size('lg')
                                            ->icon('heroicon-m-arrow-right-circle')
                                            ->action(function (Get $get) {
                                                $tables = $get('selectedTables');
                                                $path = $get('filePath');
                                                $this->openDiagram($tables, $path);
                                            })
                                    ])
                                        ->fullWidth()
                                        ->visible(fn () => $this->extractedTables->isNotEmpty()),
                                ]),
                        ]),
                ])
            ])
            ->statePath('data');
    }

    public function processExtraction(?string $path, Set $set)
    {
        if (empty($path)) return;

        try {
            $extractor = new DatabaseExtractorService();

            $tablesData = $extractor->extractAllTables($path);

            $cleanTableNames = array_column($tablesData, 'name');

            $this->extractedTables = collect($cleanTableNames)->values();

            $set('selectedTables', $this->extractedTables->toArray());

            Notification::make()->title('Extração Concluída')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Erro ao extrair')->body($e->getMessage())->danger()->send();
        }
    }

    public function openDiagram(array $selectedTables, ?string $path)
    {
        if (empty($selectedTables)) {
            Notification::make()->title('Erro')->body('Selecione pelo menos uma tabela!')->warning()->send();
            return;
        }

        $extractor = new DatabaseExtractorService();
        $finalJsonSchema = $extractor->buildDiagramSchema($path, $selectedTables);

        $diagramId = Str::uuid()->toString();
        Cache::put('diagram_' . $diagramId, $finalJsonSchema, now()->addHours(2));

        return $this->redirect('/diagram/' . $diagramId, navigate: true);
    }
}
