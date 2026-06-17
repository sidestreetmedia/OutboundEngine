<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active | archived

            $table->string('one_liner')->nullable();   // short human description
            $table->text('description')->nullable();    // longer human description

            // The structured brain — filled by the Phase 2 brain builder from
            // ingested decks/site. What you do, who it's for, real differentiators.
            $table->json('profile')->nullable();
            $table->timestamp('brain_built_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
