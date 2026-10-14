<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetDashboardPassword extends Command
{
    protected $signature = 'arcflow:dashboard-password {email? : Email address of an existing user}';
    protected $description = 'Set or reset the dashboard sign-in password for an existing user (run without an email to list users)';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (!is_string($email) || trim($email) === '') {
            $rows = User::query()->with('merchants:id,user_id,name')->orderBy('id')->get()
                ->map(fn (User $user) => [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->merchants->pluck('name')->implode(', '),
                ])->all();

            $this->table(['ID', 'Name', 'Email', 'Merchants'], $rows);
            $this->line('Run again with an email address to set that user\'s password.');

            return self::SUCCESS;
        }

        $user = User::where('email', trim($email))->first();

        if (!$user) {
            $this->error('No user with that email address. Run without an argument to list users.');

            return self::FAILURE;
        }

        $password = $this->secret('New password (min 12 characters)');
        $confirmation = $this->secret('Repeat password');

        if (!is_string($password) || strlen($password) < 12) {
            $this->error('Password must be at least 12 characters. Nothing was changed.');

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('Passwords do not match. Nothing was changed.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        // Sign out any sessions that were open with the old password.
        if (config('session.driver') === 'database' && DB::getSchemaBuilder()->hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->info('Dashboard password updated for ' . $user->email . '.');

        return self::SUCCESS;
    }
}
