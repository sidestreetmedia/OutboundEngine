<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_events', function (Blueprint $table) {
            $table->id();

            // Optional campaign scope, for fast roll-ups on the dashboard.
            $table->foreignId('campaign_id')->nullable()
                  ->constrained('campaigns')->nullOnDelete();

            // Optional source object (lead, sequence, audit, ...). Later phases
            // attach the thing that incurred the cost so every dollar is traceable.
            $table->nullableMorphs('costable');

            $table->string('category');             // llm | verification | enrichment | hosting | other
            $table->string('provider')->nullable(); // anthropic | instantly | lemlist | apollo | ...
            $table->string('description')->nullable();

            $table->decimal('quantity', 14, 4)->default(0); // tokens / credits / emails
            $table->string('unit')->nullable();             // tokens | credits | emails | ...
            $table->decimal('amount_usd', 12, 4)->default(0);
            $table->boolean('billable')->default(true);

            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index('category');
            $table->index('provider');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_events');
    }
};
