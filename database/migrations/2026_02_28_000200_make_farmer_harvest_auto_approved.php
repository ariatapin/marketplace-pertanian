<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return;
        }

        DB::table('farmer_harvests')
            ->where('status', '<>', 'approved')
            ->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);

        DB::statement("ALTER TABLE farmer_harvests DROP CONSTRAINT IF EXISTS chk_farmer_harvests_status");
        DB::statement("ALTER TABLE farmer_harvests ALTER COLUMN status SET DEFAULT 'approved'");
        DB::statement("ALTER TABLE farmer_harvests
            ADD CONSTRAINT chk_farmer_harvests_status
            CHECK (status IN ('approved'))");
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return;
        }

        DB::statement("ALTER TABLE farmer_harvests DROP CONSTRAINT IF EXISTS chk_farmer_harvests_status");
        DB::statement("ALTER TABLE farmer_harvests ALTER COLUMN status SET DEFAULT 'pending'");
        DB::statement("ALTER TABLE farmer_harvests
            ADD CONSTRAINT chk_farmer_harvests_status
            CHECK (status IN ('pending','approved','rejected'))");
    }
};
