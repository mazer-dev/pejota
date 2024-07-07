<?php

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->date('start_at');
            $table->date('end_at')->nullable();
            $table->json('signatures');

            $table->foreignIdFor(Client::class, 'client_id');
            $table->foreignIdFor(Project::class, 'project_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
