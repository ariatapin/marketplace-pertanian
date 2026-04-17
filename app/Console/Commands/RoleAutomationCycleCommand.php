<?php

namespace App\Console\Commands;

use App\Services\Automation\RoleAutomationCycleService;
use Illuminate\Console\Command;

class RoleAutomationCycleCommand extends Command
{
    protected $signature = 'automation:role-cycle
        {--force : Jalankan meski feature flag automation_role_cycle OFF}';

    protected $description = 'Jalankan otomatisasi aktivitas role (consumer/mitra/seller/admin) berbasis lokasi, cuaca, dan rekomendasi.';

    public function handle(RoleAutomationCycleService $service): int
    {
        $summary = $service->run((bool) $this->option('force'));

        if ((bool) ($summary['skipped'] ?? false)) {
            $this->warn((string) ($summary['reason'] ?? 'Siklus otomatis dilewati.'));
            return self::SUCCESS;
        }

        $this->info('Siklus otomatis role selesai dijalankan.');
        $this->line('Cycle: ' . (string) ($summary['cycle_key'] ?? '-'));
        $this->line('Recommendation dispatched: ' . number_format((int) ($summary['recommendation']['dispatched'] ?? 0)));
        $this->line('Consumer orders: ' . number_format((int) ($summary['consumer']['created_orders'] ?? 0)));
        $this->line('Affiliate withdraw requests: ' . number_format((int) ($summary['consumer']['affiliate_withdraw_requests'] ?? 0)));
        $this->line('Mitra procurement created: ' . number_format((int) ($summary['mitra']['created_procurements'] ?? 0)));
        $this->line('Mitra withdraw requests: ' . number_format((int) ($summary['mitra']['withdraw_requests'] ?? 0)));
        $this->line('Seller withdraw requests: ' . number_format((int) ($summary['seller']['withdraw_requests'] ?? 0)));
        $this->line('Admin procurement actions: ' . number_format((int) ($summary['admin']['procurement_actions'] ?? 0)));
        $this->line('Admin withdraw paid: ' . number_format((int) ($summary['admin']['withdraw_paid'] ?? 0)));
        $this->line('Admin weather notifications: ' . number_format((int) ($summary['admin']['weather_notifications_sent'] ?? 0)));

        return self::SUCCESS;
    }
}
