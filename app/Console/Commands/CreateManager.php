<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateManager extends Command
{
    protected $signature = 'user:create-manager
                            {--name=Administrador : Nome completo do gerente}
                            {--email= : E-mail de acesso do gerente}';

    protected $description = 'Cria uma conta de gerente solicitando a senha de forma oculta';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('E-mail'))));
        $password = (string) $this->secret('Senha (mínimo de 12 caracteres)');
        $passwordConfirmation = (string) $this->secret('Confirme a senha');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->mixedCase()->numbers()->symbols(),
                ],
            ],
            attributes: [
                'name' => 'nome',
                'email' => 'e-mail',
                'password' => 'senha',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        $hashed = Hash::make($password);
        unset($password, $passwordConfirmation);

        $user = DB::transaction(fn (): User => User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $hashed,
            'role' => UserRole::Manager,
            'category_id' => null,
            'sector_id' => null,
        ]));
        unset($hashed);

        $this->info("Gerente criado com sucesso: {$user->email}");

        return self::SUCCESS;
    }
}
