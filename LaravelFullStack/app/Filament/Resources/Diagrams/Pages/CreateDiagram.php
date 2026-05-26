<?php

namespace App\Filament\Resources\Diagrams\Pages;

use App\Filament\Resources\Diagrams\DiagramResource;
use App\Filament\Resources\Diagrams\Schemas\ExtractForm;
use App\Models\Diagram;
use App\Services\DatabaseExtractorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateDiagram extends Page
{
    protected static string $resource = DiagramResource::class;
    protected string $view = 'filament.resources.diagrams.pages.create';
    protected static ?string $title = 'Gerar Diagrama';

    public ?array $data = [];
    public array $extractedTables = [];

    public function mount(): void
    {
        $this->extractedTables = [];

        $this->form->fill([
            'engine' => 'sqlite',
            'filePath' => null,
            'mysql_host' => '',
            'mysql_port' => '',
            'mysql_database' => '',
            'mysql_username' => '',
            'mysql_password' => '',
            'name' => '',
            'description' => '',
            'selectedTables' => [],
        ]);
    }

    protected function getForms(): array
    {
        return [
            'form',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Dados da Conexão')
                        ->description('Conecar à base de dados')
                        ->schema(ExtractForm::connectionSchema())
                        ->afterValidation(function () {
                            $this->processExtraction();
                        }),

                    Step::make('Detalhes e Tabelas')
                        ->description('Escolha as tabelas para gerar o diagrama')
                        ->schema(ExtractForm::detailsSchema()),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<BLADE
    <x-filament::button
        wire:click="openDiagram"
        size="sm"
        color="success"
    >
        Gerar Diagrama
    </x-filament::button>
BLADE
                    )))
            ])
            ->statePath('data');
    }

    public function processExtraction(): void
    {
        $state = $this->data;

        try {
            $extractor = new DatabaseExtractorService();
            $tablesData = [];

            if ($state['engine'] === 'sqlite') {
                $filePathData = $state['filePath'] ?? null;
                if (!$filePathData) throw new \Exception('Ficheiro SQLite não encontrado.');

                $fileItem = is_array($filePathData) ? array_values($filePathData)[0] : $filePathData;
                $absolutePath = '';

                if ($fileItem instanceof TemporaryUploadedFile) {
                    $absolutePath = $fileItem->getRealPath();
                } elseif (is_string($fileItem)) {
                    if (preg_match('/^([a-zA-Z]:\\\\|\\/)/', $fileItem)) {
                        $absolutePath = $fileItem;
                    } else {
                        $absolutePath = Storage::disk('local')->path($fileItem);
                    }
                }

                if (!file_exists($absolutePath)) {
                    throw new \Exception("Ficheiro não encontrado: " . $absolutePath);
                }

                $tablesData = $extractor->extractTables($absolutePath, 'sqlite');
            } else {
                $tablesData = $extractor->extractTables(null, 'mysql', $state);
            }

            $cleanTableNames = array_column($tablesData, 'name');
            $this->extractedTables = $cleanTableNames;

            $engine = $state['engine'] ?? 'sqlite';
            //$preSelectedTables = $extractor->getDefaultSelectedTables($cleanTableNames, $engine);

            $this->data['selectedTables'] = $this->extractedTables;

            //Notification::make()->title('Extração Concluída')->success()->send();

        } catch (\Exception $e) {
            //$e->getMessage()
            Notification::make()
                ->title('Erro ao extrair tabelas')
                ->body('')
                ->danger()
                ->send();
            throw ValidationException::withMessages([
                'data.engine' => 'Falha na ligação/extração.',
            ]);
        }
    }

    public function openDiagram()
    {
        $state = $this->form->getState();

        $selectedTables = $state['selectedTables'];

        $extractor = new DatabaseExtractorService();
        $finalJsonSchema = '';

        if ($state['engine'] === 'sqlite') {
            $filePathData = $state['filePath'] ?? null;
            $fileItem = is_array($filePathData) ? array_values($filePathData)[0] : $filePathData;
            $absolutePath = '';

            if ($fileItem instanceof TemporaryUploadedFile) {
                $absolutePath = $fileItem->getRealPath();
            } elseif (is_string($fileItem)) {
                if (preg_match('/^([a-zA-Z]:\\\\|\\/)/', $fileItem)) {
                    $absolutePath = $fileItem;
                } else {
                    $absolutePath = Storage::disk('local')->path($fileItem);
                }
            }

            if (!file_exists($absolutePath)) {
                throw new \Exception("Ficheiro não encontrado no disco: " . $absolutePath);
            }

            $finalJsonSchema = $extractor->buildDiagramSchema($absolutePath, $selectedTables, 'sqlite');
        } else {
            $finalJsonSchema = $extractor->buildDiagramSchema(null, $selectedTables, 'mysql', $state);
        }

        $diagramId = Str::uuid()->toString();

        Diagram::create([
            'diagram_id' => $diagramId,
            'diagram' => json_decode($finalJsonSchema, true),
            'name' => $state['name'] ?? 'Novo Diagrama',
            'description' => $state['description'] ?? '',
            'user_id' => auth()->id(),
            'version' => 0,
        ]);

        return $this->redirect('/diagram/' . $diagramId, navigate: true);
    }
}
