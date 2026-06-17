<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Set once a positive-reply lead is pushed into HubSpot.
            $table->string('hubspot_contact_id')->nullable()->after('apollo_id');
            $table->timestamp('hubspot_synced_at')->nullable()->after('hubspot_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['hubspot_contact_id', 'hubspot_synced_at']);
        });
    }
};
