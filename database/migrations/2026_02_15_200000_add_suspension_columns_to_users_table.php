<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_suspended')) {
                $table->boolean('is_suspended')->default(false)->after('role');
                $table->index('is_suspended');
            }

            if (! Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('is_suspended');
            }

            if (! Schema::hasColumn('users', 'suspension_note')) {
                $table->string('suspension_note', 255)->nullable()->after('suspended_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'suspension_note')) {
                $table->dropColumn('suspension_note');
            }

            if (Schema::hasColumn('users', 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }

            if (Schema::hasColumn('users', 'is_suspended')) {
                $table->dropIndex(['is_suspended']);
                $table->dropColumn('is_suspended');
            }
        });
    }
};
