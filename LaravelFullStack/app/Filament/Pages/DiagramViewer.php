<?php

namespace App\Filament\Pages;

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
        $query = \App\Models\Diagram::where('diagram_id', $this->diagramId);

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
        $this->selectedVersionId = $diagram->id; // <-- Preenche o Dropdown com a versão atual
        $this->diagramName = $diagram->name;
        $this->isPublished = (bool) $diagram->is_published;
        $this->schemaJson = json_encode($diagram->diagram);
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->compact()
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                Grid::make(7)
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
                                                $query = \App\Models\Diagram::where('diagram_id', $this->diagramId)
                                                    ->orderByDesc('version');

                                                // Se for um visitante anónimo, só vê as versões que foram publicadas!
                                                if (!$this->isOwner) {
                                                    $query->where('is_published', true);
                                                }

                                                return $query->get()->mapWithKeys(function ($d) {
                                                    $label = 'Versão ' . $d->version;
                                                    if ($d->is_published) $label .= ' (Publicada)';
                                                    if ($d->id === $this->recordId) $label .= ' - Atual'; // Marca a que estamos a ver
                                                    return [$d->id => $label];
                                                });
                                            })
                                            ->live() // Dispara assim que escolhes outra opção
                                            ->afterStateUpdated(function ($state) {
                                                // Encontra o número da versão escolhida
                                                $novaVersao = \App\Models\Diagram::find($state)->version;
                                                // Redireciona para o mesmo URL mas com ?v=X (recarrega o Rust limpo)
                                                return redirect(request()->fullUrlWithQuery(['v' => $novaVersao]));
                                            }),

                                        // 4. Botão Nova Versão (Apenas visível se estiver publicado e for o dono)
//                                        Actions::make([
//                                            Action::make('newVersion')
//                                                ->label('Nova Versão')
//                                                ->icon('heroicon-m-document-plus')
//                                                ->color('primary')
//                                                ->action(function () {
//                                                    $latest = \App\Models\Diagram::where('id', $this->recordId)->first();
//
//                                                    \App\Models\Diagram::create([
//                                                        'diagram_id' => $latest->diagram_id,
//                                                        'name' => $latest->name,
//                                                        'description' => $latest->description,
//                                                        'diagram' => $latest->diagram,
//                                                        'user_id' => $latest->user_id,
//                                                        'version' => $latest->version + 1,
//                                                        'visibility' => 'link',
//                                                        'is_published' => false,
//                                                    ]);
//
//                                                    \Filament\Notifications\Notification::make()
//                                                        ->title('Nova versão criada!')
//                                                        ->body('Já podes voltar a editar o teu diagrama.')
//                                                        ->success()
//                                                        ->send();
//
//                                                    // Remove o "?v=" do link para garantir que carrega a versão mais recente que acabámos de criar
//                                                    return redirect(request()->url());
//                                                })
//                                                ->visible(fn() => $this->isPublished && $this->isOwner),
//                                        ]),
                                        Actions::make([
                                            Action::make('newVersion')
                                                ->label('Nova Versão')
                                                ->icon('heroicon-m-document-plus')
                                                ->color('primary') // Fica azul para chamar a atenção
                                                ->action(function () {
                                                    // Vai buscar o diagrama atual
                                                    $latest = Diagram::where('id', $this->recordId)->first();

                                                    // Cria uma cópia exata, mas com versão + 1 e desbloqueada!
                                                    Diagram::create([
                                                        'diagram_id' => $latest->diagram_id,
                                                        'name' => $latest->name,
                                                        'description' => $latest->description,
                                                        'diagram' => $latest->diagram,
                                                        'user_id' => $latest->user_id,
                                                        'version' => $latest->version + 1,
                                                        'visibility' => 'link', // Volta a ser "privado" até decidires publicar
                                                        'is_published' => false, // DESTRANCA O CANVAS!
                                                    ]);

                                                    Notification::make()
                                                        ->title('Nova versão criada!')
                                                        ->body('Já podes voltar a editar o teu diagrama.')
                                                        ->success()
                                                        ->send();

                                                    // Recarrega a página. O mount() vai puxar a versão nova automaticamente.
                                                    return redirect(request()->header('Referer'));
                                                })
                                                ->visible(fn() => $this->isPublished && $this->isOwner), // Só mostra ao dono quando está trancado
                                        ]),


                            ])->columnSpan(3),

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
                                        ->default(fn() => Diagram::where('id', $this->recordId)->value('visibility') ?? 'link')
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
                            ->visible(!$this->isPublished)
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
