<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Apollo person id — maps a sourced lead back to Apollo for enrichment.
            $table->string('apollo_id')->nullable()->unique()->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('apollo_id');
        });
    }
};
