<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('status');             // instantly | lemlist
            $table->string('provider_campaign_id')->nullable()->after('provider'); // the campaign id on that platform
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('provider_lead_id')->nullable()->after('source'); // id returned by the platform on push
            $table->timestamp('pushed_at')->nullable()->after('provider_lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_campaign_id']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['provider_lead_id', 'pushed_at']);
        });
    }
};
