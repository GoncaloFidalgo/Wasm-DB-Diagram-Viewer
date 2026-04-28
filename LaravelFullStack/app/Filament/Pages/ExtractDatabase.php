<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use App\Services\DatabaseExtractorService;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
use Filament\Forms\Components\CheckboxList;

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
            'engine' => 'sqlite',
            'filePath' => null,
            'mysql_host' => '',
            'mysql_port' => '',
            'mysql_database' => '',
            'mysql_username' => '',
            'mysql_password' => '',
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
                                    Select::make('engine')
                                        ->label('Motor de Base de Dados')
                                        ->options([
                                            'sqlite' => 'SQLite',
                                            'mysql' => 'MySQL',
                                        ])
                                        ->default('sqlite')
                                        ->live() // Manda a UI atualizar quando o item selecionado muda
                                        ->required(),

                                    FileUpload::make('filePath')
                                        ->label('Ficheiro SQLite (.sqlite, .db)')
                                        ->disk('local')
                                        ->directory('sqlite-uploads')
                                        ->preserveFilenames()
                                        ->required(fn (Get $get) => $get('engine') === 'sqlite')
                                        ->visible(fn (Get $get) => $get('engine') === 'sqlite'),

                                    Grid::make(2)
                                        ->visible(fn (Get $get) => $get('engine') === 'mysql')
                                        ->schema([
                                            TextInput::make('mysql_host')
                                                ->label('Host')
                                                ->default('')
                                                ->required(fn (Get $get) => $get('engine') === 'mysql'),
                                            TextInput::make('mysql_port')
                                                ->label('Porta')
                                                ->default('')
                                                ->required(fn (Get $get) => $get('engine') === 'mysql'),
                                            TextInput::make('mysql_database')
                                                ->label('Base de Dados')
                                                ->columnSpan(2)
                                                ->required(fn (Get $get) => $get('engine') === 'mysql'),
                                            TextInput::make('mysql_username')
                                                ->label('Utilizador')
                                                ->required(fn (Get $get) => $get('engine') === 'mysql'),
                                            TextInput::make('mysql_password')
                                                ->label('Password')
                                                ->password(),
                                        ]),
                                    Actions::make([
                                        Action::make('extract')
                                            ->label('Extrair Tabelas')
                                            ->size('lg')
                                            ->action(function (Set $set) {
                                                $state = $this->form->getState();
                                                $this->processExtraction($state, $set);
                                            })
                                    ])->fullWidth(),
                                ]),

                            Section::make('Selecione as Tabelas')
                                ->columnSpan(1)
                                ->schema([
                                    TextEntry::make('empty_state')
                                        ->hiddenLabel()
                                        ->state(new HtmlString('
                                                <div class="flex flex-col items-center justify-center text-center py-8 opacity-50">
                                                <!-- Blade::render(<x-filament::icon icon="heroicon-o-document-magnifying-glass" class="w-12 h-12 mb-4 text-gray-500" />) -->
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
                                            ->label('Abrir Diagrama')
                                            ->color('success')
                                            ->size('lg')
                                            ->icon('heroicon-m-arrow-right-circle')
                                            ->action(function () {
                                                $state = $this->form->getState();
                                                $this->openDiagram($state);
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

    public function processExtraction(array $state, Set $set): void
    {
        try {
            $extractor = new DatabaseExtractorService();
            $tablesData = [];

            if ($state['engine'] === 'sqlite') {
                $filePath = $state['filePath'] ?? null;
                if (!$filePath) throw new \Exception('Ficheiro SQLite não encontrado.');

                $relativePath = is_array($filePath) ? array_values($filePath)[0] : $filePath;
                $absolutePath = Storage::disk('local')->path($relativePath);

                $tablesData = $extractor->extractTables($absolutePath);
            } else {
                $tablesData = $extractor->extractTables(null, 'mysql', $state);
            }

            $cleanTableNames = array_column($tablesData, 'name');

            $this->extractedTables = collect($cleanTableNames)->values();
            $set('selectedTables', $this->extractedTables->toArray());

            Notification::make()->title('Extração Concluída')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Erro ao extrair')->body($e->getMessage())->danger()->send();
        }
    }

    public function openDiagram(array $state)
    {
        $selectedTables = $state['selectedTables'] ?? [];

        if (empty($selectedTables)) {
            Notification::make()->title('Erro')->body('Selecione pelo menos uma tabela!')->warning()->send();
            return;
        }

        $extractor = new DatabaseExtractorService();
        $finalJsonSchema = '';

        if ($state['engine'] === 'sqlite') {
            $filePath = $state['filePath'] ?? null;
            $relativePath = is_array($filePath) ? array_values($filePath)[0] : $filePath;
            $absolutePath = Storage::disk('local')->path($relativePath);

            $finalJsonSchema = $extractor->buildDiagramSchema($absolutePath, $selectedTables, 'sqlite');
        } else {
            $finalJsonSchema = $extractor->buildDiagramSchema(null, $selectedTables, 'mysql', $state);
        }

        $diagramId = Str::uuid()->toString();
        Cache::put('diagram_' . $diagramId, $finalJsonSchema, now()->addHours(2));

        return $this->redirect('/diagram/' . $diagramId, navigate: true);
    }

}
