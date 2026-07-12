<?php

use App\Services\BackfillUserSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new BackfillUserSettings)();
    }

    public function down(): void
    {
        // Backfill não-destrutivo: as cópias na empresa foram preservadas, nada a reverter.
    }
};
