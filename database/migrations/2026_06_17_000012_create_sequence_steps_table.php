<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('position');                // 1-based order
            $table->unsignedInteger('delay_days')->default(0);  // days after the previous step
            $table->string('channel')->default('email');

            $table->string('angle')->nullable();        // strategic angle for this step
            $table->string('subject_hint')->nullable(); // optional subject guidance
            $table->text('instructions')->nullable();   // what this step should accomplish

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sequence_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_steps');
    }
};
