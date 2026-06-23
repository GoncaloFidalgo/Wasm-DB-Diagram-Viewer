<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\Diagrams\DiagramResource;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CustomProfile extends EditProfile
{
    protected string|Width|null $maxWidth = Width::TwoExtraLarge;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Tabs::make('Menu de Perfil')
                    ->tabs([

                        Tabs\Tab::make('Informação Pessoal')
                            ->icon('heroicon-o-user')
                            ->schema([
                                $this->getNameFormComponent(),
                                $this->getEmailFormComponent(),
                            ]),

                        Tabs\Tab::make('Segurança')
                            ->icon('heroicon-o-lock-closed')
                            ->schema([
                                TextInput::make('current_password')
                                    ->label('Palavra-passe atual')
                                    ->password()
                                    ->revealable()
                                    ->currentPassword()
                                    ->requiredWith('password')
                                    ->dehydrated(false),

                                TextInput::make('password')
                                    ->label('Nova palavra-passe')
                                    ->password()
                                    ->revealable()
                                    ->required(fn ($get) => filled($get('current_password')))
                                    ->rules(['confirmed'])
                                    ->validationAttribute('palavra-passe')

                                    ->dehydrated(fn ($state) => filled($state))

                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                                TextInput::make('password_confirmation')
                                    ->label('Confirmar nova palavra-passe')
                                    ->password()
                                    ->revealable()
                                    ->requiredWith('password')
                                    ->dehydrated(false)
                                    ->validationAttribute('confirmar palavra-passe'),
                            ]),

                        // OPÇÃO 3: APAGAR CONTA
                        Tab::make('Apagar Conta')
                            ->icon('heroicon-o-trash')
                            ->schema([

                                Placeholder::make('aviso')
                                    ->label('')
                                    ->content('Atenção: Esta zona destina-se à eliminação da tua conta. Todos os teus diagramas e dados serão apagados permanentemente.'),

                                Actions::make([
                                    Action::make('deleteAccount')
                                        ->label('Apagar a minha conta definitivamente')
                                        ->color('danger')
                                        ->icon('heroicon-o-trash')
                                        ->requiresConfirmation()
                                        ->modalHeading('Tens a certeza que queres apagar a tua conta?')
                                        ->modalDescription('Esta ação irá apagar permanentemente o teu utilizador e todos os teus diagramas guardados no sistema. Para confirmar, introduz a tua palavra-passe atual.')
                                        ->form([
                                            TextInput::make('password_confirmation_delete')
                                                ->label('Introduz a tua palavra-passe')
                                                ->password()
                                                ->required()
                                                ->currentPassword(),
                                        ])
                                        ->action(function () {
                                            $user = Auth::user();

                                            $user->delete();

                                            Auth::logout();
                                            request()->session()->invalidate();
                                            request()->session()->regenerateToken();

                                            return redirect(DiagramResource::getUrl('index'));
                                        }),
                                ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ])
            ->operation('edit')
            ->model($this->getUser())
            ->statePath('data');
    }

    protected function getRedirectUrl(): ?string
    {
        return filament()->getCurrentPanel()->getUrl();
    }
}
