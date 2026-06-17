<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sequence_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            // The single value prop this message is built around (the "one thing" rule).
            $table->foreignId('value_prop_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('position')->default(1); // step position, denormalized for ordering
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('status')->default('draft'); // draft|approved|rejected|queued|sent|skipped

            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            // What went into this message: the proof + trigger used, model, guardrail flags.
            $table->json('generation')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['lead_id', 'position']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
