<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('email');
            // Lowercased/trimmed email — the dedup key.
            $table->string('email_normalized')->unique();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();
            $table->string('company')->nullable();
            $table->string('company_domain')->nullable();
            $table->string('industry')->nullable();
            $table->string('location')->nullable();
            $table->string('linkedin_url', 512)->nullable();

            $table->string('status')->default('new');          // new | verified | invalid | risky | suppressed
            $table->string('verification_status')->nullable(); // valid | invalid | risky | unknown
            $table->timestamp('verified_at')->nullable();

            $table->string('source')->default('import');       // import | apollo | manual
            $table->foreignId('lead_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();

            $table->json('enrichment')->nullable();
            $table->json('triggers')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('verification_status');
            $table->index('company_domain');
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
