<?php

namespace App\Filament\Pages;

use App\Models\Diagram;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Livewire\Attributes\On;

class DiagramViewer extends Page
{
    // 1. URL routing with the ID parameter
    protected static ?string $slug = 'diagram/{id}';

    // 2. Hide it from the navbar
    protected static bool $shouldRegisterNavigation = false;

    // 3. Remove the page title (so the canvas gets more room)
    protected static ?string $title = '';

    protected string $view = 'filament.pages.diagram-viewer';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
    // ========================================================
    // Livewire State & Logic
    // ========================================================
    public string $diagramId;
    public string $schemaJson = '';

    // Filament automatically passes the {id} from the URL into the mount method!
    public function mount($id)
    {
        $this->diagramId = $id;

        $latestDiagram = Diagram::where('diagram_id', $this->diagramId)
            ->orderByDesc('version')
            ->first();

        if ($latestDiagram) {
            $this->schemaJson = json_encode($latestDiagram->diagram);
            return;
        }

        $cachedSchema = Cache::get('diagram_' . $this->diagramId);

        if ($cachedSchema) {
            $this->schemaJson = $cachedSchema;
            return;
        }

        Notification::make()
            ->title('Atenção')
            ->body('O diagrama não foi encontrado.')
            ->danger()
            ->send();

        return redirect('/');
    }

    // 4. Force the Filament page to be 100% wide (no margins!)
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    #[On('save-diagram')]
    public function handleDiagramSave($jsonPayload)
    {
        Diagram::updateOrCreate(
            ['diagram_id' => $this->diagramId],
            [
                'diagram' => json_decode($jsonPayload, true),
                'user_id' => auth()->id(),
                'version' => 0,
            ]
        );

        Notification::make()
            ->title('Sucesso!')
            ->body('Diagrama guardado com sucesso.')
            ->success()
            ->send();
    }
}
