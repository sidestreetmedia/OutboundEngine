<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('value');                    // the email address or domain
            $table->string('type')->default('email');   // email | domain
            $table->string('reason')->nullable();       // unsubscribe | bounce | complaint | manual
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressions');
    }
};
