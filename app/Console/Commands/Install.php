<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class Install extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pj:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute the initial configurations for the project: create default company, create default user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->alert('Before starting the installation, make sure you have the database configured and running, and .env file configured');

        $userName = $this->ask('Please enter the user name, enter for "Admin"', 'Admin');
        $userEmail = $this->ask('Please enter the user email or enter for admin@admin.com', 'admin@admin.com');
        $userPassword = $this->ask('Please enter the user password or enter for "123456"', '123456');

        $user = User::create([
            'name' => $userName,
            'email' => $userEmail,
            'email_verified_at' => now(),
            'password' => Hash::make($userPassword),
        ]);

        $this->info('User created successfully');

        $companyName = $this->ask('Please enter the company name, enter for "My Company"', 'My Company');
        $companyEmail = $this->ask('Please enter the company email or enter for empty');

        Company::create([
            'name' => $companyName,
            'email' => $companyEmail,
            'user_id' => $user->id,
        ]);

        $this->info('Company created successfully');

        $this->info('Installation completed successfully');
    }
}
