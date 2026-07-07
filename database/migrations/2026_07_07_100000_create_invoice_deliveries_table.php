<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignIdFor(Invoice::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel')->default('email');
            $table->string('status')->default('queued');
            $table->json('to');
            $table->json('cc')->nullable();
            $table->string('subject');
            $table->longText('body')->nullable();
            $table->longText('signature')->nullable();
            $table->boolean('attach_invoice_pdf')->default(true);
            $table->json('timesheet_params')->nullable();
            $table->string('external_file_path')->nullable();
            $table->json('attachments_meta')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_deliveries');
    }
};
