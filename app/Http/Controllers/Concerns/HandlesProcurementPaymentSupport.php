<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Concerns\GuardsFinanceDemoMode;
use App\Support\Concerns\HandlesWalletLedgerMutation;
use Illuminate\Support\Facades\Schema;

trait HandlesProcurementPaymentSupport
{
    use GuardsFinanceDemoMode;
    use HandlesWalletLedgerMutation;

    private function hasPaymentColumns(): bool
    {
        return Schema::hasTable('admin_orders')
            && Schema::hasColumn('admin_orders', 'payment_status')
            && Schema::hasColumn('admin_orders', 'payment_method')
            && Schema::hasColumn('admin_orders', 'paid_amount')
            && Schema::hasColumn('admin_orders', 'payment_proof_url')
            && Schema::hasColumn('admin_orders', 'payment_submitted_at')
            && Schema::hasColumn('admin_orders', 'payment_verified_at')
            && Schema::hasColumn('admin_orders', 'payment_verified_by')
            && Schema::hasColumn('admin_orders', 'payment_note');
    }

    private function canUseWalletLedger(): bool
    {
        return $this->isFinanceDemoModeEnabled() && $this->hasWalletLedgerColumns();
    }

    private function canRecordProcurementIncomeWallet(): bool
    {
        return $this->isFinanceDemoModeEnabled() && $this->hasWalletLedgerColumns();
    }
}
