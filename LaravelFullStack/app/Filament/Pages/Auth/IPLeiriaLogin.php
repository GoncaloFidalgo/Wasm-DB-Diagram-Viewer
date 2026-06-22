<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Filament\Forms\Components\TextInput;

class IPLeiriaLogin extends Login
{

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('login')
                    ->label('Email ou Nome de Utilizador')
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes(['tabindex' => 1]),

                $this->getPasswordFormComponent(),
                //$this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    /**
     * OVERRIDE
     */
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $login = $data['login'];
        $password = $data['password'];

        if (preg_match('/^(docente|aluno)(0[1-9]|[1-9][0-9])$/i', $login)) {

            if ($password === '1234') {
                $user = User::firstOrCreate(
                    ['name' => $login],
                    [
                        'user_number' => null,
                        'email' => "{$login}@teste.local",
                        'password' => Hash::make(Str::random(32)),
                    ]
                );

                Auth::login($user, $data['remember'] ?? false);
                session()->regenerate();

                return app(LoginResponse::class);
            }

            throw ValidationException::withMessages([
                'data.password' => __('Password incorreta.'),
            ]);
        }

        $loginType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        if (Auth::attempt([$loginType => $login, 'password' => $password], $data['remember'] ?? false)) {
            session()->regenerate();

            return app(LoginResponse::class);
        }

        // Se falhar a regex E falhar a base de dados
        throw ValidationException::withMessages([
            'data.login' => __('Credenciais inválidas.'),
        ]);
    }

}
