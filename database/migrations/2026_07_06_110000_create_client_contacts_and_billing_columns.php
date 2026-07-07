<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignIdFor(Client::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->boolean('receives_billing')->default(false);
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('bill_by_email')->default(true);
            $table->boolean('bill_by_whatsapp')->default(false);
            $table->string('billing_email_subject')->nullable();
            $table->longText('billing_email_body')->nullable();
            $table->longText('billing_email_signature')->nullable();
            $table->text('billing_whatsapp_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'bill_by_email',
                'bill_by_whatsapp',
                'billing_email_subject',
                'billing_email_body',
                'billing_email_signature',
                'billing_whatsapp_template',
            ]);
        });
    }
};
