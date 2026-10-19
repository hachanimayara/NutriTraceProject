<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'nutritrace:admin {email} {--name=} {--reset-password : Set a new password for this administrator}';

    protected $description = 'Créer ou promouvoir de manière interactive un compte administrateur NutriTrace';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }
        $user = User::withTrashed()->where('email', $email)->first();
        if ($user?->trashed()) {
            $this->error('Cet e-mail appartient à un compte supprimé.');

            return self::FAILURE;
        }
        if (! $user) {
            $name = $this->option('name') ?: $this->ask('Nom complet');
            $password = $this->validatedPassword();
            if ($password === null) {
                return self::FAILURE;
            }
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'admin', 'status' => 'approved', 'approved_at' => now(), 'is_protected_admin' => true]);
        } else {
            $user->forceFill(['role' => 'admin', 'status' => 'approved', 'approved_at' => $user->approved_at ?: now(), 'is_protected_admin' => true])->save();

            if ($this->option('reset-password')) {
                $password = $this->validatedPassword();
                if ($password === null) {
                    return self::FAILURE;
                }
                $user->forceFill([
                    'password' => $password,
                    'session_version' => $user->session_version + 1,
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();
            }
        }
        $this->info("Administrateur protégé configuré : {$user->email}");

        if ($this->option('reset-password')) {
            $this->info('Mot de passe administrateur réinitialisé.');
        }

        return self::SUCCESS;
    }

    private function validatedPassword(): ?string
    {
        $password = $this->secret('Nouveau mot de passe administrateur (12 caractères minimum)');
        $confirmation = $this->secret('Confirmez le nouveau mot de passe');

        if (! is_string($password) || $password !== $confirmation || validator(
            ['password' => $password],
            ['password' => [Password::min(12)->mixedCase()->numbers()->symbols()]],
        )->fails()) {
            $this->error('Mot de passe invalide ou confirmation différente. Aucun changement effectué.');

            return null;
        }

        return $password;
    }
}
