<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('type');                  // upload | url
            $table->string('label')->nullable();     // human label for the dashboard

            // Uploads
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->string('path')->nullable();      // storage path of the stored file
            $table->unsignedBigInteger('bytes')->nullable();

            // URLs
            $table->string('url', 2048)->nullable();

            // Extraction
            $table->string('status')->default('pending'); // pending | extracted | failed
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('char_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('extracted_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sources');
    }
};
