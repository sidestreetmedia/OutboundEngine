<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Seed the default notify address as a real saved setting so it shows on the
    // settings page and can be edited or cleared (blank = notifications off).
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'hubspot_notify_email')->exists()) {
            DB::table('settings')->insert([
                'key' => 'hubspot_notify_email',
                'value' => 'craft@joshkuhn.com',
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only remove it if it's still the untouched default.
        DB::table('settings')
            ->where('key', 'hubspot_notify_email')
            ->where('value', 'craft@joshkuhn.com')
            ->delete();
    }
};
