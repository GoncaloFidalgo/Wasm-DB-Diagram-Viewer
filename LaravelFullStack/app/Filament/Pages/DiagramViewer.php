<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Diagrams\DiagramResource;
use App\Models\Diagram;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
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
    public string $diagramName;
    public string $schemaJson = '';
    // O Livewire passa o id do url automaticamente para aqui
    public function mount($id = null)
    {
        $this->diagramId = $id;

        $latestDiagram = Diagram::where('diagram_id', $this->diagramId)
            ->orderByDesc('version')
            ->firstOrFail();

        $this->recordId = $latestDiagram->id;

        $this->diagramName = $latestDiagram->name;
        $this->schemaJson = json_encode($latestDiagram->diagram);
        $this->isPublished = (bool) $latestDiagram->is_published;

//
//        Notification::make()
//            ->title('Atenção')
//            ->body('O diagrama não foi encontrado.')
//            ->danger()
//            ->send();
//
//        return redirect('/');
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->compact()
                    ->schema([
                        Grid::make(3)
                            ->schema([

                                Actions::make([
                                    Action::make('back')
                                        ->label('Diagramas')
                                        ->icon('heroicon-m-arrow-left')
                                        ->color('gray')
                                        ->url(DiagramResource::getUrl('index')),
                                ]),

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
                                    ]),

                                Actions::make([
                                    Action::make('publish')
                                        ->label('Publicar')
                                        ->icon('heroicon-m-share')
                                        ->color('gray')
                                        ->modalHeading('Partilhar Diagrama')
                                        ->modalDescription('Define quem tem acesso para visualizar este diagrama.')
                                        ->modalSubmitActionLabel('Guardar Alterações')
                                        ->modalWidth('md')
                                        ->schema([
                                            Radio::make('visibility')
                                                ->label('Visibilidade')
                                                ->options([
                                                    'public' => 'Público',
                                                    'link' => 'Apenas com o link',
                                                ])
                                                ->descriptions([
                                                    'public' => 'O diagrama aparecerá na lista pública para todos os utilizadores.',
                                                    'link' => 'Oculto da lista pública. Só quem tiver o link direto poderá aceder.',
                                                ])

                                                ->default(fn () => Diagram::where('id', $this->recordId)->value('visibility') ?? 'link')
                                                ->required(),
                                        ])
                                        ->action(function (array $data) {
                                            Diagram::where('id', $this->recordId)->update([
                                                'visibility' => $data['visibility'],
                                                'is_published' => true,
                                            ]);

                                            return redirect(request()->header('Referer'));
                                        }),

                                    Action::make('save')
                                        ->label('Gravar')
                                        ->icon('heroicon-m-document-check')
                                        ->color('primary')
                                        ->action(fn() => $this->dispatch('trigger-rust-save')),

                                ])
                                    ->visible(! $this->isPublished)
                                    ->alignEnd(),

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
}
