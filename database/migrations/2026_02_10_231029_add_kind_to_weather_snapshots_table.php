<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('weather_snapshots', 'kind')) {
            Schema::table('weather_snapshots', function (Blueprint $table) {
                $table->string('kind', 20)->default('current')->after('provider');
                $table->index(['provider', 'kind', 'location_type', 'location_id'], 'ws_provider_kind_loc_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('weather_snapshots', 'kind')) {
            Schema::table('weather_snapshots', function (Blueprint $table) {
                $table->dropIndex('ws_provider_kind_loc_idx');
                $table->dropColumn('kind');
            });
        }
    }
};
