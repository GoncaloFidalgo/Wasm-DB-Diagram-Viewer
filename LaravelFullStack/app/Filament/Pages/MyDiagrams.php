<?php
namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use App\Models\Diagram;
use Illuminate\Support\Facades\Auth;

// ==========================================
// FILAMENT TABLE BUILDER IMPORTS
// ==========================================
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;


class MyDiagrams extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Os Meus Diagramas';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Meus Diagramas';
    protected static ?string $slug = 'my-diagrams';

    protected string $view = 'filament.pages.my-diagrams';
    protected function getHeaderActions(): array
    {
        return [
            Action::make('extract')
                ->label('Gerar Diagrama')
                ->icon('heroicon-o-plus')
                ->url('/extract')
                ->color('primary'),
        ];
    }
    public function table(Table $table): Table
    {
        return $table
            ->query(
                //Obter os diagramas do utilizador logado
                Diagram::query()->where('user_id', Auth::id())
            )
            ->columns([
                TextColumn::make('diagram_id')
                    ->label('ID do Diagrama')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                    //->limit(12),  // Primeiros 12 carateres

                TextColumn::make('version')
                    ->label('Versão')
                    ->formatStateUsing(fn(string $state): string => 'v' . $state)
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data de Gravação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([

                Action::make('open')
                    ->label('Abrir Diagrama')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->button()
                    ->url(fn(Diagram $record): string => '/diagram/' . $record->diagram_id)
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Ainda não tem diagramas')
            ->emptyStateDescription('Extraia uma base de dados e grave o seu primeiro diagrama para o ver aqui.');
    }
}
