<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'wazi:admin-password {email}';
    protected $description = 'Set a new password for an existing administrator.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user || !$user->hasRole('admin')) {
            $this->error('No administrator account was found for that email.');
            return self::FAILURE;
        }

        $password = $this->secret('New administrator password (12+ characters)');
        if (!is_string($password) || strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');
            return self::FAILURE;
        }

        if (!$this->confirm('Set this password and invalidate remember-me sessions?', false)) {
            $this->warn('Password reset cancelled.');
            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => null,
        ])->save();

        $this->info('Administrator password updated.');

        return self::SUCCESS;
    }
}
