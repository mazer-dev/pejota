<?php

use App\Enums\CompanySettingsEnum;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('status');
            $table->decimal('exchange_rate', 20, 10)->nullable()->after('currency');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('phone');
        });

        foreach (Company::all() as $company) {
            $base = $company->settings()->get(CompanySettingsEnum::FINANCE_CURRENCY->value) ?? 'USD';

            DB::table('invoices')
                ->where('company_id', $company->id)
                ->whereNull('currency')
                ->update(['currency' => $base]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
