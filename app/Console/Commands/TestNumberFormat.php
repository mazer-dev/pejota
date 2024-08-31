<?php

namespace App\Console\Commands;

use App\Enums\CompanySettingsEnum;
use App\Models\User;
use Illuminate\Console\Command;

class TestNumberFormat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-number-format';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::first();
        auth()->login($user);
        print CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumberFormated();
    }
}
