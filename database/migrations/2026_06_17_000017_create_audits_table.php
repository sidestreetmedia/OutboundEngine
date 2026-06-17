<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            $table->string('domain')->nullable();
            $table->string('url')->nullable();
            $table->string('status')->default('pending'); // pending | done | failed | skipped

            // Real, observable signals only — never fabricated.
            $table->json('findings')->nullable();
            $table->text('summary')->nullable(); // short narrative grounded in findings
            $table->text('error')->nullable();

            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index('domain');
            $table->index(['lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
