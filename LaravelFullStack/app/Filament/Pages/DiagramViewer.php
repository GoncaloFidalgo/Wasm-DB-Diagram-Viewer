<?php

namespace App\Filament\Pages;

use App\Filament\Actions\PublishDiagramAction;
use App\Filament\Resources\Diagrams\DiagramResource;
use App\Filament\Resources\Diagrams\Pages\CreateDiagram;
use App\Models\Diagram;
use App\Services\DatabaseExtractorService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DiagramViewer extends Page
{
    // Rota no URL
    protected static ?string $slug = 'diagram/{id}';
    // Não mostrar na navbar
    protected static bool $shouldRegisterNavigation = false;
    // Sem titulo na pagina
    protected static ?string $title = '';
    protected string $view = 'filament.pages.diagram-viewer';
    // Sem layout
    protected static string $layout = 'filament-panels::components.layout.base';
    //protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public string $diagramId;
    public int $recordId;
    public bool $isPublished = false;
    public bool $isOwner = false;
    public $selectedVersionId;
    public string $diagramName;
    public ?string $schemaJson = null;
    public string $source = 'mine';
    public ?string $currentDiagramJsonStr = null;
    public $extractedTables = [];
    public $fullExtractedData = [];

    // O Livewire passa o id do url automaticamente para aqui
    public function mount($id = null)
    {
        $requestedSource = request('source');

        if (in_array($requestedSource, ['public', 'mine'])) {
            $this->source = $requestedSource;
        }

        $this->diagramId = $id;

        // Obter sempre a versão mais recente do diagrama
        $query = Diagram::where('diagram_id', $this->diagramId);

        // Se houver "?v=2" no URL, carrega essa versão. Senão, carrega a mais recente.
        if (request()->has('v')) {
            $query->where('version', request('v'));
        } else {
            $query->orderByDesc('version');
        }
        $diagram = $query->firstOrFail();

        $this->isOwner = auth()->check() && auth()->id() === $diagram->user_id;

        if (!$this->isOwner) {
            if (!$diagram->is_published || $diagram->visibility === 'private') {
                abort(404, 'Este diagrama é privado ou não existe.');
            }
        }
        $this->recordId = $diagram->id;
        $this->selectedVersionId = $diagram->id;
        $this->diagramName = $diagram->name;
        $this->isPublished = (bool)$diagram->is_published;
        $this->schemaJson = json_encode($diagram->diagram);
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->compact()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Grid::make(8)
                                    ->schema([
                                        Actions::make([
                                            Action::make('back')
                                                ->label('Diagramas')
                                                ->icon('heroicon-m-arrow-left')
                                                ->color('gray')
                                                ->url(function () {
                                                    if ($this->source === 'public') {
                                                        return PublicDiagrams::getUrl();
                                                    }

                                                    return DiagramResource::getUrl('index');
                                                })
                                                ->visible(fn() => auth()->check()),
                                        ])->columnSpan(1),

                                        TextInput::make('diagramName')
                                            ->disabled($this->isPublished)
                                            ->hiddenLabel()
                                            ->suffixIcon('heroicon-m-pencil')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state) {
                                                if ($this->isPublished) return;
                                                if (!empty(trim($state))) {
                                                    Diagram::where('id', $this->recordId)->update([
                                                        'name' => $state,
                                                    ]);

                                                    Notification::make()
                                                        ->title('Nome guardado!')
                                                        ->success()
                                                        ->send();
                                                }
                                            })
                                            ->extraAttributes([
                                                'style' => 'margin: 0 auto; width: 100%; max-width: 400px;'
                                            ])->columnSpan(3),

                                        Select::make('selectedVersionId')
                                            ->hiddenLabel()
                                            ->options(function () {
                                                $query = Diagram::where('diagram_id', $this->diagramId)
                                                    ->orderByDesc('version');

                                                if (!$this->isOwner) {
                                                    $query->where('is_published', true);
                                                }

                                                return $query->get()->mapWithKeys(function ($d) {
                                                    $label = 'Versão ' . $d->version;
                                                    if ($d->is_published) $label .= ' (Publicada)';
                                                    if ($d->id === $this->recordId) $label .= ' - Atual';
                                                    return [$d->id => $label];
                                                });
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, DiagramViewer $livewire) {
                                                $diagram = Diagram::find($state);

                                                $livewire->recordId = $diagram->id;
                                                $livewire->diagramName = $diagram->name;
                                                $livewire->isPublished = (bool)$diagram->is_published;
                                                $livewire->schemaJson = json_encode($diagram->diagram);

                                                // Dispara um evento para o browser apanhar e atualizar o Canvas Rust
                                                $livewire->dispatch('reload-wasm-schema',
                                                    schema: $livewire->schemaJson,
                                                    isReadOnly: $livewire->isPublished
                                                );
                                            })->columnSpan(2),


                                        Actions::make([
                                            Action::make('newVersion')
                                                ->label('Nova Versão')
                                                ->icon('heroicon-m-document-plus')
                                                ->color('primary')
                                                ->action(function () {
                                                    $maxVersion = Diagram::where('diagram_id', $this->diagramId)->max('version');
                                                    $latest = Diagram::where('id', $this->recordId)->first();

                                                    Diagram::create([
                                                        'diagram_id' => $latest->diagram_id,
                                                        'name' => $latest->name,
                                                        'description' => $latest->description,
                                                        'diagram' => $latest->diagram,
                                                        'user_id' => $latest->user_id,
                                                        'version' => $maxVersion + 1,
                                                        'visibility' => 'link',
                                                        'is_published' => false,
                                                    ]);

                                                    Notification::make()
                                                        ->title('Nova versão criada!')
                                                        ->body('')
                                                        ->success()
                                                        ->send();

                                                    return redirect(request()->header('Referer'));
                                                })
                                                ->visible(function () {
                                                    // 1. Se não for o dono ou a versão atual não estiver publicada, esconde.
                                                    if (!$this->isOwner || !$this->isPublished) {
                                                        return false;
                                                    }

                                                    // Verifica se já existe alguma versao ativo para este diagrama UUID
                                                    $hasDraft = Diagram::where('diagram_id', $this->diagramId)
                                                        ->where('is_published', false)
                                                        ->exists();

                                                    // Só mostra o botão se não houver versoes
                                                    return !$hasDraft;
                                                }),
                                        ])->columnSpan(2),


                                    ])->columnSpan(3),

                                Actions::make([
                                    ActionGroup::make([
                                        Action::make('export_png')
                                            ->label('Exportar como PNG')
                                            ->icon('heroicon-m-photo')
                                            ->action(fn() => $this->dispatch('trigger-export-png')), // Dispara evento para o JS


                                    ])
                                        ->label('Exportar')
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->color('gray')
                                        ->button(),

                                    Action::make('start_sync')
                                        ->label('Sincronizar')
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('info')
                                        ->visible(fn() => !$this->isPublished)
                                        ->action(fn() => $this->dispatch('trigger-rust-sync')),


                                    Action::make('save')
                                        ->label('Gravar')
                                        ->icon('heroicon-m-document-check')
                                        ->color('primary')
                                        ->action(fn() => $this->dispatch('trigger-rust-save'))
                                        ->visible(fn() => !$this->isPublished),

                                    PublishDiagramAction::make()
                                        ->visible(fn() => $this->isOwner),

                                ])
                                    ->alignEnd()
                                    ->columnStart(4),
                            ]),

                    ]),

                View::make('filament.resources.diagrams.pages.canvas'),
            ]);
    }

    // Para ocupar a largura inteira da página
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    #[On('save-diagram')]
    public function handleDiagramSave($jsonPayload)
    {
        if ($this->isPublished) return;

        Diagram::where('id', $this->recordId)->update([
            'diagram' => json_decode($jsonPayload, true),
        ]);

        Notification::make()
            ->title('Sucesso!')
            ->body('Diagrama guardado com sucesso.')
            ->success()
            ->send();
    }
    public function processSyncExtraction(Get $get, Set $set): void
    {
        try {
            $extractor = new DatabaseExtractorService();
            $tablesData = [];

            // Ler o motor escolhido no modal através do $get
            $engine = $get('engine');

            if ($engine === 'sqlite') {
                $filePathData = $get('filePath');
                if (!$filePathData) throw new \Exception('Ficheiro SQLite não encontrado.');

                $fileItem = is_array($filePathData) ? array_values($filePathData)[0] : $filePathData;
                $absolutePath = '';

                if ($fileItem instanceof TemporaryUploadedFile) {
                    $absolutePath = $fileItem->getRealPath();
                }
                elseif (is_string($fileItem)) {
                    if (preg_match('/^([a-zA-Z]:\\\\|\\/)/', $fileItem)) {
                        $absolutePath = $fileItem;
                    } else {
                        $absolutePath = Storage::disk('local')->path($fileItem);
                    }
                }

                if (!file_exists($absolutePath)) {
                    throw new \Exception("Ficheiro não encontrado no disco: " . $absolutePath);
                }

                $tablesData = $extractor->extractTables($absolutePath, 'sqlite');
            } else {
                // Para o MySQL, construír o array de credenciais lendo da Modal
                $mysqlState = [
                    'mysql_host' => $get('mysql_host'),
                    'mysql_port' => $get('mysql_port'),
                    'mysql_database' => $get('mysql_database'),
                    'mysql_username' => $get('mysql_username'),
                    'mysql_password' => $get('mysql_password'),
                ];
                $tablesData = $extractor->extractTables(null, 'mysql', $mysqlState);
            }

            // Extraír os nomes e guardar tudo no Livewire para o Merge posterior
            $cleanTableNames = array_column($tablesData, 'name');
            $this->extractedTables = $cleanTableNames;
            $this->fullExtractedData = $tablesData;

            // Descobrir quais as tabelas que já estão no diagrama Wasm atual
            $alreadyInDiagram = [];
            if (!empty($this->schemaJson)) {
                $current = json_decode($this->schemaJson, true);
                $alreadyInDiagram = array_column($current['tables'] ?? [], 'name');
            }


            // tabelas que existiam no diagrama mas que entretanto foram apagadas da BD.
            $preSelectedTables = array_intersect($alreadyInDiagram, $cleanTableNames);
            // atualizar o select das tabelas
            $set('selectedTables', array_values($preSelectedTables));

            Notification::make()->title('Extração Concluída')->success()->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao extrair tabelas')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    public function performSyncMerge(array $selectedTableNames, array $formData): void
    {
        $currentDiagram = json_decode($this->currentDiagramJsonStr, true);

        if (!$currentDiagram) {
            $currentDiagram = json_decode($this->schemaJson, true);
        }

        $oldTables = $currentDiagram['tables'] ?? [];
        $oldRelations = $currentDiagram['relations'] ?? [];

        $newTablesList = [];
        $indexMapping = [];

        // Percorrer as tabelas atuais no diagrama e comparar com as tabelas selecionadas
        // para filtrar tabelas existentes (Manter posições e descrições) e remover tabelas que extavam no diagrama mas que não estão selecionadas
        foreach ($oldTables as $oldIndex => $table) {
            if (in_array($table['name'], $selectedTableNames)) {
                $newIndex = count($newTablesList);
                $newTablesList[] = $table;
                $indexMapping[$oldIndex] = $newIndex; // Ex: A tabela 3 passou a ser a tabela 2
            } else {
                $indexMapping[$oldIndex] = null; // Tabela foi apagada
            }
        }

        // Adicionar tabelas novas
        $existingNames = array_column($newTablesList, 'name');
        $tablesToAdd = array_diff($selectedTableNames, $existingNames);

        foreach ($this->fullExtractedData as $extractedTable) {
            if (in_array($extractedTable['name'], $tablesToAdd)) {
                // Definir uma posição padrão central para as tabelas novas
                $extractedTable['pos'] = ['x' => 100.0, 'y' => 100.0];
                $newTablesList[] = $extractedTable;
            }
        }

        $tableIndices = [];
        $columnIndices = [];
        foreach ($newTablesList as $tIndex => $table) {
            $tableName = $table['name'];
            $tableIndices[$tableName] = $tIndex;
            foreach ($table['columns'] as $cIndex => $column) {
                $columnIndices[$tableName][$column['name']] = $cIndex;
            }
        }

        // guardar os "relation_segments" (as linhas visuais do Rust) das relações antigas
        $oldSegmentsMap = [];
        foreach ($oldRelations as $rel) {
            if (isset($rel['name']) && isset($rel['relation_segments'])) {
                $oldSegmentsMap[$rel['name']] = $rel['relation_segments'];
            }
        }
        $absolutePath = null;

        if (($formData['engine'] ?? 'sqlite') === 'sqlite') {
            $filePathData = $formData['filePath'] ?? null;

            if (!$filePathData) {
                throw new \Exception('Ficheiro SQLite não encontrado.');
            }

            $fileItem = is_array($filePathData) ? array_values($filePathData)[0] : $filePathData;

            if ($fileItem instanceof TemporaryUploadedFile) {
                $absolutePath = $fileItem->getRealPath();
            } elseif (is_string($fileItem)) {
                // Verifica se já é um caminho absoluto (Windows ou Unix)
                if (preg_match('/^([a-zA-Z]:\\\\|\\/)/', $fileItem)) {
                    $absolutePath = $fileItem;
                } else {
                    $absolutePath = Storage::disk('local')->path($fileItem);
                }
            }

            if (!$absolutePath || !file_exists($absolutePath)) {
                throw new \Exception("Ficheiro não encontrado: " . $absolutePath);
            }
        }

        $extractorService = new DatabaseExtractorService();
        $extractorService->setupConnection(
            $absolutePath,
            $formData['engine'] ?? 'sqlite',
            [
                'mysql_host' => $formData['mysql_host'] ?? null,
                'mysql_port' => $formData['mysql_port'] ?? null,
                'mysql_database' => $formData['mysql_database'] ?? null,
                'mysql_username' => $formData['mysql_username'] ?? null,
                'mysql_password' => $formData['mysql_password'] ?? null,
            ]
        );

        // Recriar todas as relações a partir da Base de Dados (apanha as velhas e as novas)
        $newRelations = [];
        foreach ($newTablesList as $table) {
            $tableName = $table['name'];

            $foreignKeys = $extractorService->fetchForeignKeys($tableName);

            foreach ($foreignKeys as $fk) {
                $fromTableIdx = $tableIndices[$tableName];
                $toTableIdx   = $tableIndices[$fk->table] ?? null;

                $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                $toColIdx   = $columnIndices[$fk->table][$fk->to] ?? null;

                // Só cria a relação se ambas as tabelas estiverem presentes no novo diagrama
                if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                    $relationName = "{$tableName}_{$fk->table}";

                    // Se a relação já existia no diagrama antigo, recupera as linhas desenhadas.
                    // Se for uma relação totalmente nova, começa com um array vazio [].
                    $segments = $oldSegmentsMap[$relationName] ?? [];

                    $newRelations[] = [
                        'name' => $relationName,
                        'relation_segments' => $segments,
                        'tables' => [$fromTableIdx, $toTableIdx],
                        'columns' => [$fromColIdx, $toColIdx],
                        'description' => "FK: {$tableName}.{$fk->from} -> {$fk->table}.{$fk->to}"
                    ];
                }
            }
        }

        // Montar o JSON final
        $currentDiagram['tables'] = $newTablesList;
        $currentDiagram['relations'] = $newRelations;

        $updatedJsonStr = json_encode($currentDiagram);
        $this->schemaJson = $updatedJsonStr;
        // Enviar para o browser reconstruir o Rust
        $this->dispatch('reload-wasm-schema',
            schema: $updatedJsonStr,
            isReadOnly: $this->isPublished
        );
        $this->dispatch('close-modal', id: 'sync-diagram-modal');
    }

    #[On('open-sync-modal')]
    public function handleOpenSyncModal($jsonString)
    {
        $this->extractedTables = [];
        if (property_exists($this, 'fullExtractedData')) {
            $this->fullExtractedData = [];
        }

        $this->currentDiagramJsonStr = $jsonString;

        $this->mountAction('sync');
    }
    public function syncAction(): Action
    {
        return Action::make('sync')
            ->modalHeading('Sincronizar Base de Dados')
            ->modalDescription('As novas tabelas serão adicionadas ao diagrama atual. As tabelas desmarcadas serão apagadas.')
            ->modalSubmitActionLabel('Aplicar Sincronização')
            ->schema([ // <-- VOLTOU PARA SCHEMA()!
                Select::make('engine')
                    ->label('Motor de Base de Dados')
                    ->options([
                        'sqlite' => 'SQLite',
                        'mysql' => 'MySQL',
                    ])
                    ->default('sqlite')
                    ->live()
                    ->afterStateUpdated(fn ($livewire) => $livewire->extractedTables = []),

                ViewField::make('filePath')
                    ->label('Ficheiro SQLite (.sqlite, .db)')
                    ->view('filament.forms.components.custom-sqlite-upload')
                    ->visible(fn(Get $get) => $get('engine') === 'sqlite')
                    ->live(),

                Grid::make(2)
                    ->visible(fn(Get $get) => $get('engine') === 'mysql')
                    ->schema([
                        TextInput::make('mysql_host')->label('Host')->default(''),
                        TextInput::make('mysql_port')->label('Porta')->default(''),
                        TextInput::make('mysql_database')->label('Base de Dados')->columnSpan(2),
                        TextInput::make('mysql_username')->label('Utilizador'),
                        TextInput::make('mysql_password')->label('Password')->password(),
                    ]),

                Actions::make([
                    Action::make('extractForSync')
                        ->label('Extrair Tabelas')
                        ->action(fn ($livewire, Get $get, Set $set) => $livewire->processSyncExtraction($get, $set))
                ])->fullWidth(),

                Section::make('Tabelas Extraídas')
                    ->visible(fn ($livewire) => !empty($livewire->extractedTables))
                    ->schema([
                        CheckboxList::make('selectedTables')
                            ->hiddenLabel()
                            ->options(fn($livewire) => empty($livewire->extractedTables) ? [] : array_combine($livewire->extractedTables, $livewire->extractedTables))
                            ->columns(3)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->searchable()
                    ]),
            ])
            ->action(function (array $data) {
                $this->performSyncMerge($data['selectedTables'], $data);
            });
    }
}
