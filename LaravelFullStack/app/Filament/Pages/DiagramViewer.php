<?php

namespace App\Filament\Pages;

use App\Filament\Actions\EditDiagramMetadataAction;
use App\Filament\Actions\PublishDiagramAction;
use App\Filament\Actions\SyncDiagramAction;
use App\Filament\Resources\Diagrams\DiagramResource;
use App\Models\Diagram;
use App\Services\DatabaseExtractorService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

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
        $query = Diagram::where('diagram_id', $this->diagramId)->orderByDesc('version');

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
                        Flex::make([
                            // LEFT
                            Flex::make([
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
                                    ->visible(fn() => auth()->check())
                                    ->extraAttributes([
                                        'x-on:click.prevent' => 'if (window.hasUnsavedChanges) { if (confirm(`Tem alterações não guardadas. Quer mesmo sair e perder o progresso?`)) { window.hasUnsavedChanges = false; window.location.href = $el.href; } } else { window.location.href = $el.href; }'
                                    ]),

                                TextInput::make('diagramName')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->suffixActions([
                                        EditDiagramMetadataAction::configure(
                                            Action::make('edit_metadata')->visible(fn() => !$this->isPublished)
                                        ),
                                        Action::make('is_readonly')
                                            ->icon('heroicon-m-lock-closed')
                                            ->color('warning')
                                            ->disabled()
                                            ->tooltip('Versão publicada (Apenas leitura)')
                                            ->visible(fn() => $this->isPublished),
                                    ]),
                            ])->alignStart()->grow(false),

                            //CENTER
                            Flex::make([
                                Select::make('selectedVersionId')
                                    ->hiddenLabel()
                                    ->live()
                                    ->selectablePlaceholder(false)
                                    ->extraAttributes([
                                        'style' => 'max-width: 130px; width: 100%;'
                                    ])
                                    ->extraInputAttributes([
                                        'x-data' => '{ previousValue: null }',
                                        'x-init' => 'previousValue = $el.value',
                                        'x-on:change.capture' => 'previousValue = window.handleVersionChange($event, $el, previousValue)',
                                    ])
                                    ->options(function () {
                                        $query = Diagram::where('diagram_id', $this->diagramId)
                                            ->orderByDesc('version');

                                        if (!$this->isOwner) {
                                            $query->where('is_published', true);
                                        }

                                        return $query->get()->mapWithKeys(function ($d) {
                                            $dateString = $d->published_at ? ' (' . $d->published_at->format('d/m/Y - H:i') . ')' : '';
                                            $label = 'Publicação ' . $d->version . $dateString;
                                            if (!$d->is_published) $label = 'Editável';
                                            //if ($d->id === $this->recordId) $label .= ' - Atual';
                                            return [$d->id => $label];
                                        });
                                    })
                                    ->afterStateUpdated(function ($state, DiagramViewer $livewire) {
                                        $diagram = Diagram::find($state);

                                        $livewire->recordId = $diagram->id;
                                        $livewire->diagramName = $diagram->name;
                                        $livewire->isPublished = (bool)$diagram->is_published;
                                        $livewire->schemaJson = json_encode($diagram->diagram);

                                        // Dispara um evento para o browser apanhar e atualizar o Canvas Rust
                                        $livewire->dispatch('reload-wasm-schema',
                                            schema: $livewire->schemaJson,
                                            isReadOnly: $livewire->isPublished,
                                            hasUnsavedChanges: false
                                        );
                                    })->visible(function () {
                                        $hasPublishedVersion = Diagram::where('diagram_id', $this->diagramId)
                                            ->where('is_published', true)
                                            ->exists();

                                        // Só mostra o botão se houver versoes
                                        return $hasPublishedVersion;
                                    }),

                                Actions::make([
                                    Action::make('newVersion')
                                        ->label('Nova Versão')
                                        ->icon('heroicon-m-document-plus')
                                        ->color('primary')
                                        ->requiresConfirmation()
                                        ->modalIcon('')
                                        ->modalHeading('Criar versão')
                                        ->modalDescription('Quer criar uma nova versão a partir da mais recente?')
                                        ->modalSubmitActionLabel('Sim')
                                        ->modalCancelActionLabel('Cancelar')
                                        ->modalCloseButton(false)
                                        ->closeModalByClickingAway(false)
                                        ->closeModalByEscaping(false)
                                        ->action(fn () => $this->createNewVersion())
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
                                ])->extraAttributes([
                                    'style' => 'white-space: nowrap; min-width: max-content;'
                                ]),
                            ])
                                ->alignBetween()
                                ->gap(4)
                                ->grow(false)
                            ->extraAttributes([
                                'style' => 'flex-wrap: nowrap; min-width: max-content;'
                            ]),

                            //RIGHT
                            Flex::make([
                                Actions::make([
                                    SyncDiagramAction::make(),
                                    ActionGroup::make([
                                        Action::make('export_png')
                                            ->label('Exportar como PNG')
                                            ->icon('heroicon-m-photo')
                                            ->url('#')
                                            ->extraAttributes([
                                                'x-on:click.prevent' => '$dispatch(\'trigger-export-png\')',
                                            ]),

                                        Action::make('export_txt')
                                            ->label('Exportar como TXT')
                                            ->icon('heroicon-m-document-text')
                                            ->url('#')
                                            ->extraAttributes([
                                                'x-on:click.prevent' => "\$dispatch('trigger-export-txt', { name: '" . addslashes($this->diagramName) . "' })",
                                            ]),
                                    ])
                                        ->label('Exportar')
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->color('gray')
                                        ->button(),


                                    Action::make('save')
                                        ->label('Gravar')
                                        ->icon('heroicon-m-document-check')
                                        ->color('primary')
                                        ->extraAttributes([
                                            'x-on:click.prevent' => '$dispatch(\'trigger-rust-save\')',
                                        ])
                                        ->visible(fn() => !$this->isPublished),

                                    PublishDiagramAction::make(),

                                    Action::make('publish_version')
                                        ->label('Publicar Versão')
                                        ->icon('heroicon-m-check-badge')
                                        ->color('success')
                                        ->visible(fn() => $this->isOwner && !$this->isPublished)
                                        ->requiresConfirmation()
                                        ->modalHeading('Publicar esta versão?')
                                        ->modalDescription('Ao publicar, esta versão ficará trancada (apenas leitura) e visível para quem tiver acesso ao diagrama.')
                                        ->modalSubmitActionLabel('Sim, publicar')
                                        ->action(function () {
                                            // 1. Marca a versão atual como publicada na BD
                                            Diagram::where('id', $this->recordId)->update([
                                                'is_published' => true,
                                                'published_at' => now(),
                                                'visibility' => 'private'
                                            ]);

                                            // 2. Atualiza o estado do Livewire
                                            $this->isPublished = true;


                                            // 3. Informa o Rust para bloquear a edição na hora (Read-Only)
                                            $this->dispatch('reload-wasm-schema',
                                                schema: $this->schemaJson,
                                                isReadOnly: true,
                                                hasUnsavedChanges: false
                                            );

                                            Notification::make()->title('Versão publicada e trancada!')->success()->send();
                                        }),
                                ])->alignEnd(),
                            ])
                                ->alignEnd()
                                ->grow(false)
                        ])
                            ->alignBetween()
                            ->gap(4)
                            ->grow()
                            ->extraAttributes([
                                'class' => 'custom-toolbar',
                                'style' => 'flex-wrap: wrap !important; width: 100%;'
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

//        Notification::make()
//            ->title('Sucesso!')
//            ->body('Diagrama guardado com sucesso.')
//            ->success()
//            ->send();
    }

    #[On('update-sync-json')]
    public function handleUpdateSyncJson($jsonString)
    {
        $this->currentDiagramJsonStr = $jsonString;
    }
    public function createNewVersion(): void
    {
        $latest = Diagram::where('diagram_id', $this->diagramId)
            ->orderByDesc('version')
            ->first();

        $newVersionNumber = $latest->version + 1;

        $newDiagram = Diagram::create([
            'diagram_id' => $latest->diagram_id,
            'name' => $latest->name,
            'description' => $latest->description,
            'diagram' => $latest->diagram,
            'user_id' => $latest->user_id,
            'version' => $newVersionNumber,
            'visibility' => 'link',
            'is_published' => false,
        ]);

        $this->recordId = $newDiagram->id;
        $this->selectedVersionId = $newDiagram->id;
        $this->isPublished = false;

        $this->dispatch('reload-wasm-schema',
            schema: json_encode($newDiagram->diagram),
            isReadOnly: false,
            hasUnsavedChanges: false
        );

        Notification::make()
            ->title('Nova versão criada!')
            ->success()
            ->send();
    }
    public function processSyncExtraction(Get $get, Set $set, $component): void
    {
        $engine = $get('engine');
        try {
            $extractor = new DatabaseExtractorService();
            if ($engine === 'sqlite') {
                $absolutePath = $extractor->resolveSqlitePath($get('filePath'));
                $tablesData = $extractor->extractTables($absolutePath, 'sqlite');
            } else {
                $mysqlState = [
                    'mysql_host' => $get('mysql_host'),
                    'mysql_port' => $get('mysql_port'),
                    'mysql_database' => $get('mysql_database'),
                    'mysql_username' => $get('mysql_username'),
                    'mysql_password' => $get('mysql_password'),
                ];
                $tablesData = $extractor->extractTables(null, 'mysql', $mysqlState);
            }

            // Extract table names only
            $cleanTableNames = array_column($tablesData, 'name');
            $this->extractedTables = $cleanTableNames;
            // Save table and columns
            $this->fullExtractedData = $tablesData;

            // Discover which tables are already in the diagram
            $alreadyInDiagram = [];
            if (!empty($this->schemaJson)) {
                $current = json_decode($this->schemaJson, true);
                $alreadyInDiagram = array_column($current['tables'] ?? [], 'name');
            }

            // Gets the tables that are in the diagram and in the extracted tables
            // so that they can be pre-selected
            $tablesToSelect = array_intersect($alreadyInDiagram, $cleanTableNames);

            // Unselects Laravel system tables
            $preSelectedTables = $extractor->getDefaultSelectedTables($tablesToSelect, $engine);

            $set('selectedTables', array_values($preSelectedTables));

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $title = 'Erro Desconhecido';

            $formPrefix = $component->getContainer()->getStatePath();

            $errorField = $formPrefix . '.engine';
            $validationMessage = 'A operação falhou. Verifique a notificação para mais detalhes.';

            if (str_starts_with($errorMessage, '0')) {
                $title = 'Falha na ligação à base de dados';
                $validationMessage = 'Verifique os dados de ligação e tente novamente.';
            } elseif (str_starts_with($errorMessage, '1')) {
                $title = 'Falha na leitura das tabelas';
                $validationMessage = 'Ocorreu um erro ao ler o esquema da base de dados.';
            } elseif (str_contains($errorMessage, 'Ficheiro')) {
                $title = 'Erro ao ler o ficheiro';
                $errorField = $formPrefix . '.filePath';
                $validationMessage = 'Carregue um ficheiro .sqlite ou .db válido para prosseguir.';
            }

            \Filament\Notifications\Notification::make()
                ->title($title)
                ->danger()
                ->persistent()
                ->send();

            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorField => $validationMessage,
            ]);
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

        $extractedTablesMap = [];
        foreach ($this->fullExtractedData as $extractedTable) {
            $extractedTablesMap[$extractedTable['name']] = $extractedTable;
        }
        foreach ($oldTables as $table) {
            if (in_array($table['name'], $selectedTableNames)) {

                if (isset($extractedTablesMap[$table['name']])) {
                    $freshColumns = $extractedTablesMap[$table['name']]['columns'];

                    // Mapear as colunas antigas para não perder as descrições
                    $oldColumnsMap = [];
                    foreach ($table['columns'] as $oldCol) {
                        $oldColumnsMap[$oldCol['name']] = $oldCol;
                    }

                    $mergedColumns = [];
                    foreach ($freshColumns as $freshCol) {
                        // Se a coluna já existia, preserva a descrição antiga
                        if (isset($oldColumnsMap[$freshCol['name']])) {
                            $freshCol['description'] = $oldColumnsMap[$freshCol['name']]['description'] ?? $freshCol['description'];
                        }
                        $mergedColumns[] = $freshCol;
                    }

                    // Substitui as colunas velhas da tabela pelas colunas da DB
                    $table['columns'] = $mergedColumns;
                }

                $newTablesList[] = $table;
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
        $extractorService = new DatabaseExtractorService();
        $absolutePath = null;

        if (($formData['engine'] ?? 'sqlite') === 'sqlite') {
            $absolutePath = $extractorService->resolveSqlitePath($formData['filePath'] ?? null);
        }

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
                $toTableIdx = $tableIndices[$fk->table] ?? null;

                $fromColIdx = $columnIndices[$tableName][$fk->from] ?? null;
                $toColIdx = $columnIndices[$fk->table][$fk->to] ?? null;

                // Só cria a relação se ambas as tabelas estiverem presentes no novo diagrama
                if (isset($fromTableIdx, $toTableIdx, $fromColIdx, $toColIdx)) {
                    $relationName = "{$tableName}_{$fk->from}_{$fk->table}";

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
            isReadOnly: $this->isPublished,
            hasUnsavedChanges: true
        );
        //$this->dispatch('close-modal', id: 'sync-diagram-modal');
    }
}
