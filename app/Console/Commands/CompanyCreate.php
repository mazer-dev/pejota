<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Console\Command;

class CompanyCreate extends Command
{
    protected $signature = 'pj:company:create {email} {--name=}';

    protected $description = 'Create a new company owned by the given user (by email)';

    public function handle(CompanyService $companyService): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("User not found: {$this->argument('email')}");

            return self::FAILURE;
        }

        $company = $companyService->create($user, $this->option('name'));

        $this->info("Company '{$company->name}' (id {$company->id}) created for {$user->email}.");

        return self::SUCCESS;
    }
}
