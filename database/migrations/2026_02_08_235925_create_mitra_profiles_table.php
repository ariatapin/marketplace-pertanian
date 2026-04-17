<?php
/* =========================================================
 | 3) create_mitra_profiles_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mitra_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->primary()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('store_name');
            $table->text('store_address');
            $table->integer('region_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('wallet_balance', 15, 2)->default(0);

            $table->timestamps();

            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_profiles');
    }
};
