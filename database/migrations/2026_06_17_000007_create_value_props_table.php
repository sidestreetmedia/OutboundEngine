<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('value_props', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Persona-agnostic (company-level) value props are allowed → nullable.
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();

            $table->string('headline');
            $table->text('body')->nullable();
            $table->string('problem')->nullable();      // the buyer problem it addresses
            $table->string('proof_point')->nullable();  // real evidence from the profile
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('product_id');
            $table->index('persona_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('value_props');
    }
};
