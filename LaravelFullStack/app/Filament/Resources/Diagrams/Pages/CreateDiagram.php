<?php

namespace App\Filament\Resources\Diagrams\Pages;

use App\Filament\Resources\Diagrams\DiagramResource;
use App\Filament\Resources\Diagrams\Schemas\ExtractForm;
use App\Models\Diagram;
use App\Services\DatabaseExtractorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateDiagram extends Page
{
    protected static string $resource = DiagramResource::class;

    protected string $view = 'filament.resources.diagrams.pages.create';

    protected static  ?string $title = 'Gerar Diagrama';

    public ?array $connectionData = [];
    public ?array $detailsData = [];
    public array $extractedTables = [];
    public ?string $rawJsonSchema = null;

    public function mount(): void
    {
        $this->extractedTables = [];

        $this->connectionForm->fill([
            'engine' => 'sqlite',
            'filePath' => null,
            'mysql_host' => '',
            'mysql_port' => '',
            'mysql_database' => '',
            'mysql_username' => '',
            'mysql_password' => '',
        ]);

        $this->detailsForm->fill([
            'name' => '',
            'description' => '',
            'selectedTables' => [],
        ]);
    }
    protected function getForms(): array
    {
        return [
            'connectionForm',
            'detailsForm',
        ];
    }
    public function connectionForm(Schema $schema): Schema
    {
        return $schema
            ->components(ExtractForm::connectionSchema())
            ->statePath('connectionData');
    }
    public function detailsForm(Schema $schema): Schema
    {
        return $schema
            ->components(ExtractForm::detailsSchema())
            ->statePath('detailsData');
    }
    public function processExtraction(): void
    {
        $state = $this->connectionForm->getState();

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
                $tablesData = $extractor->extractTables(null, 'mysql', $state);
            }

            $cleanTableNames = array_column($tablesData, 'name');
            $this->extractedTables = $cleanTableNames;

            $this->detailsForm->fill([
                'selectedTables' => $this->extractedTables,
                'name' => $this->detailsData['name'] ?? '',
                'description' => $this->detailsData['description'] ?? '',
            ]);

            Notification::make()->title('Extração Concluída')->success()->send();

        } catch (\Exception $e) {
            //$e->getMessage()
            Notification::make()
                ->title('Erro ao extrair tabelas')
                ->body()
                ->danger()
                ->send();
        }
    }

    public function openDiagram()
    {
        $detailsState = $this->detailsForm->getState();

        $connState = $this->connectionForm->getState();

        $selectedTables = $detailsState['selectedTables'];

        $extractor = new DatabaseExtractorService();
        $finalJsonSchema = '';

        if ($connState['engine'] === 'sqlite') {
            $filePath = $connState['filePath'] ?? null;
            $relativePath = is_array($filePath) ? array_values($filePath)[0] : $filePath;
            $absolutePath = Storage::disk('local')->path($relativePath);

            $finalJsonSchema = $extractor->buildDiagramSchema($absolutePath, $selectedTables, 'sqlite');
        } else {
            $finalJsonSchema = $extractor->buildDiagramSchema(null, $selectedTables, 'mysql', $connState);
        }

        $diagramId = Str::uuid()->toString();

        Diagram::create([
            'diagram_id'  => $diagramId,
            'diagram'     => json_decode($finalJsonSchema, true),
            'name'        => $detailsState['name'] ?? 'Novo Diagrama',
            'description' => $detailsState['description'] ?? '',
            'user_id'     => auth()->id(),
            'version'     => 0,
        ]);

        return $this->redirect('/diagram/' . $diagramId, navigate: true);
    }
}
