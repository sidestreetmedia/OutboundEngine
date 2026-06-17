<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable(); // dedup key from the platform

            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();

            // Set by the classifier in a later step.
            $table->string('classification')->nullable(); // interested|objection|not_now|ooo|unsubscribe|auto_reply|other
            $table->boolean('is_bounce')->default(false);
            $table->boolean('is_auto_reply')->default(false);

            $table->timestamp('received_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_message_id']);
            $table->index(['campaign_id', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};
