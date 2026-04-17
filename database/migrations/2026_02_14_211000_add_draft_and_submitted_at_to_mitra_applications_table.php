<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mitra_applications')) {
            return;
        }

        Schema::table('mitra_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('mitra_applications', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }
        });

        if ($this->supportsNamedCheckConstraint()) {
            DB::statement("ALTER TABLE mitra_applications DROP CONSTRAINT IF EXISTS chk_mitra_applications_status");
            DB::statement("ALTER TABLE mitra_applications
                ADD CONSTRAINT chk_mitra_applications_status
                CHECK (status IN ('draft','pending','approved','rejected'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mitra_applications')) {
            return;
        }

        if ($this->supportsNamedCheckConstraint()) {
            DB::statement("ALTER TABLE mitra_applications DROP CONSTRAINT IF EXISTS chk_mitra_applications_status");
            DB::statement("ALTER TABLE mitra_applications
                ADD CONSTRAINT chk_mitra_applications_status
                CHECK (status IN ('pending','approved','rejected'))");
        }

        Schema::table('mitra_applications', function (Blueprint $table) {
            if (Schema::hasColumn('mitra_applications', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
        });
    }

    private function supportsNamedCheckConstraint(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'pgsql', 'sqlsrv'], true);
    }
};
