<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite can't add a foreign key to an existing table without a full
        // table rebuild, and the local validation harness runs on SQLite. On
        // MySQL (the real stack) the constraint is added properly. The column
        // and its index already exist from the Phase 1 campaigns migration.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
    }
};
