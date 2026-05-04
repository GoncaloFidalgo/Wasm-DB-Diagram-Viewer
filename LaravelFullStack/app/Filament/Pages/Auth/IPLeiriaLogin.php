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
                TextInput::make('user_number')
                    ->label('Número de Utilizador ')
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
        $userNumber = $data['user_number'];
        $password = $data['password'];

        // Regex: 'docente' ou 'aluno' seguido de 01 até 99
        if (preg_match('/^(docente|aluno)(0[1-9]|[1-9][0-9])$/i', $userNumber)) {

            if ($password === '1234') {
                $user = User::firstOrCreate(
                    ['name' => $userNumber],
                    [
                        'user_number' => null,
                        'email' => "{$userNumber}@teste.local",
                        'password' => Hash::make(Str::random(32)),
                    ]
                );

                Auth::login($user, $data['remember'] ?? false);
                session()->regenerate();

                return app(LoginResponse::class);
            }
            throw ValidationException::withMessages([
                'data.password' => __('Password incorreta'),
            ]);
        }
        // Validar login
        $authRequest = Http::asMultipart()->post('https://www.dei.estg.ipleiria.pt/servicos/projetos/validateLogin.php', [
            ['name' => 'a', 'contents' => $userNumber],
            ['name' => 'b', 'contents' => $password],
        ]);

        if (trim($authRequest->body()) !== 'true') {
            throw ValidationException::withMessages([
                'data.user_number' => __('Credenciais inválidas'),
            ]);
        }

        // Obter dados após login valido
        $dataRequest = Http::asMultipart()->post('https://www.dei.estg.ipleiria.pt/servicos/projetos/validateLoginDados.php', [
            ['name' => 'a', 'contents' => $userNumber],
            ['name' => 'b', 'contents' => $password],
        ]);

        $rawLdapText = $dataRequest->body();

        if (str_contains($rawLdapText, '[name]')) {

            $name = $this->extractFromPrintR($rawLdapText, 'name') ?? 'Utilizador Desconhecido';

            $email = $this->extractFromPrintR($rawLdapText, 'mail')
                ?? $this->extractFromPrintR($rawLdapText, 'userprincipalname')
                ?? "{$userNumber}@my.ipleiria.pt";

            $user = User::firstOrCreate(
                ['user_number' => $userNumber],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            if (empty($user->email)) {
                $user->update(['email' => $email]);
            }

            Auth::login($user, $data['remember'] ?? false);
            session()->regenerate();

            return app(LoginResponse::class);
        }

        throw ValidationException::withMessages([
            'data.user_number' => 'Credenciais inválidas.',
        ]);
    }
    private function extractFromPrintR(string $rawText, string $key): ?string
    {
        // Procura a key, ignora o Array e o count, obtem o valor em [0] => xxxx.
        $pattern = '/\[' . preg_quote($key, '/') . '\]\s*=>\s*Array\s*\(\s*\[count\]\s*=>\s*\d+\s*\[0\]\s*=>\s*([^\n\r]+)/is';

        if (preg_match($pattern, $rawText, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
