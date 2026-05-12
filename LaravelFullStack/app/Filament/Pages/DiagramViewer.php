<?php

namespace App\Filament\Pages;

use App\Filament\Actions\PublishDiagramAction;
use App\Filament\Resources\Diagrams\DiagramResource;
use App\Models\Diagram;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
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
    public bool $isOwner = false;
    public $selectedVersionId;
    public string $diagramName;
    public string $schemaJson = '';

    // O Livewire passa o id do url automaticamente para aqui
    public function mount($id = null)
    {
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

        if (!$diagram->is_published && !$this->isOwner) {
            abort(403, 'Este diagrama é privado ou não existe.');
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
                                                ->url(DiagramResource::getUrl('index'))
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

                                    Action::make('save')
                                        ->label('Gravar')
                                        ->icon('heroicon-m-document-check')
                                        ->color('primary')
                                        ->action(fn() => $this->dispatch('trigger-rust-save')),

                                ])
                                    ->visible(fn() => !$this->isPublished)
                                    ->alignEnd()
                                    ->columnStart(4),
                                Actions::make([
                                    PublishDiagramAction::make()
                                ])
                                    ->alignEnd()
                                    ->columnStart(5),
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
