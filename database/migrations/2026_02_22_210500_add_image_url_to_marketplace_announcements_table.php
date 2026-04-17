<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_announcements')) {
            return;
        }

        Schema::table('marketplace_announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_announcements', 'image_url')) {
                $table->string('image_url')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_announcements')) {
            return;
        }

        Schema::table('marketplace_announcements', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_announcements', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};

