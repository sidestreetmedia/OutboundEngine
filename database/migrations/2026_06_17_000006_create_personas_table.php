<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('role')->nullable();
            $table->string('seniority')->nullable();

            $table->json('okrs')->nullable();  // what they're measured on
            $table->json('pains')->nullable(); // the problems they feel
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
