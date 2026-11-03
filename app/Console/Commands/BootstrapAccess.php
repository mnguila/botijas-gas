<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BootstrapAccess extends Command
{
    protected $signature = 'access:bootstrap {username=admin} {--generate : Gerar e mostrar uma password inicial}';
    protected $description = 'Criar o primeiro administrador do sistema';

    public function handle(): int
    {
        if (User::where('is_super_admin', true)->exists()) {
            $this->error('Já existe um administrador principal.');
            return self::FAILURE;
        }

        $username = mb_strtolower(trim((string) $this->argument('username')));
        if (! preg_match('/^[a-z0-9._-]{3,50}$/', $username) || User::where('username', $username)->exists()) {
            $this->error('Username inválido ou já utilizado. Use 3 a 50 letras minúsculas, números, ponto, hífen ou sublinhado.');
            return self::FAILURE;
        }

        if ($this->option('generate')) {
            $password = bin2hex(random_bytes(16));
        } else {
            $password = $this->secret('Password (mínimo 10 caracteres)');
            $confirmation = $this->secret('Confirmar password');
            if (! is_string($password) || strlen($password) < 10 || $password !== $confirmation) {
                $this->error('As passwords não coincidem ou são demasiado curtas.');
                return self::FAILURE;
            }
        }

        $user = new User(['name' => 'Administrador', 'username' => $username, 'is_active' => true, 'password' => $password]);
        $user->is_super_admin = true;
        $user->save();

        $this->info("Administrador criado: {$username}");
        if ($this->option('generate')) $this->warn("Password inicial: {$password}");
        $this->line('Altere a password na página Utilizadores após entrar.');
        return self::SUCCESS;
    }
}
