<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class AdminDashboardViewModelFactory
{
    public function make(array $metrics, ?string $adminName = null): array
    {
        $totalPendingMode = (int) (($metrics['pending_affiliate_applications'] ?? 0) + ($metrics['pending_farmer_seller_applications'] ?? 0));
        $totalPendingProcurement = (int) ($metrics['active_mitra_orders'] ?? 0);
        $totalUsers = (int) ($metrics['total_users'] ?? 0);
        $totalMitra = (int) ($metrics['total_mitra'] ?? 0);
        $totalActiveAffiliates = (int) ($metrics['active_affiliates'] ?? 0);
        $totalActiveSellers = (int) ($metrics['active_sellers'] ?? 0);
        $totalMarketplaceActiveOrders = (int) ($metrics['active_orders'] ?? 0);
        $totalActiveMitraOrders = (int) ($metrics['active_mitra_orders'] ?? 0);
        $totalStoreProducts = (int) ($metrics['total_store_products'] ?? 0);

        $chartBars = [
            ['label' => 'Pengajuan Pending', 'short' => 'Pengajuan', 'value' => $totalPendingMode, 'fill_class' => 'admin-fill-amber'],
            ['label' => 'Order Mitra Aktif', 'short' => 'Order Mitra', 'value' => $totalPendingProcurement, 'fill_class' => 'admin-fill-indigo'],
            ['label' => 'Order Marketplace Aktif', 'short' => 'Order Shop', 'value' => $totalMarketplaceActiveOrders, 'fill_class' => 'admin-fill-cyan'],
            ['label' => 'Mitra Aktif', 'short' => 'Mitra', 'value' => $totalMitra, 'fill_class' => 'admin-fill-emerald'],
        ];

        $chartMax = max(1, (int) collect($chartBars)->max('value'));
        $chartTotal = max(1, (int) collect($chartBars)->sum('value'));
        $chartBars = array_map(function (array $bar) use ($chartMax, $chartTotal): array {
            $height = max(16, (int) round(((int) $bar['value'] / $chartMax) * 100));
            $ratio = (int) round(((int) $bar['value'] / $chartTotal) * 100);

            $bar['height_class'] = PercentClassResolver::prefixed('admin-h-', $height, 5, 15, 100);
            $bar['ratio'] = $ratio;

            return $bar;
        }, $chartBars);

        $statusRows = [
            ['label' => 'Pengajuan consumer', 'value' => $totalPendingMode, 'suffix' => ' pending', 'fill_class' => 'admin-fill-amber', 'chip_class' => 'admin-chip-amber'],
            ['label' => 'Order mitra aktif', 'value' => $totalActiveMitraOrders, 'suffix' => '', 'fill_class' => 'admin-fill-indigo', 'chip_class' => 'admin-chip-indigo'],
            ['label' => 'Mitra aktif', 'value' => $totalMitra, 'suffix' => '', 'fill_class' => 'admin-fill-emerald', 'chip_class' => 'admin-chip-emerald'],
            ['label' => 'Affiliate aktif', 'value' => $totalActiveAffiliates, 'suffix' => '', 'fill_class' => 'admin-fill-cyan', 'chip_class' => 'admin-chip-cyan'],
            ['label' => 'Penjual aktif', 'value' => $totalActiveSellers, 'suffix' => '', 'fill_class' => 'admin-fill-indigo', 'chip_class' => 'admin-chip-indigo'],
            ['label' => 'Produk toko aktif', 'value' => $totalStoreProducts, 'suffix' => '', 'fill_class' => 'admin-fill-cyan', 'chip_class' => 'admin-chip-cyan'],
        ];
        $statusMax = max(1, (int) collect($statusRows)->max('value'));
        $statusRows = array_map(function (array $row) use ($statusMax): array {
            $width = min(100, (int) round(((int) $row['value'] / $statusMax) * 100));
            $row['width_class'] = PercentClassResolver::prefixed('admin-w-', $width, 5, 0, 100);

            return $row;
        }, $statusRows);

        $metricCards = [
            [
                'label' => 'Total Pengajuan Pending',
                'value' => (int) ($metrics['total_pengajuan'] ?? 0),
                'value_class' => 'admin-value-amber',
                'helper' => 'Butuh approval/reject dari admin',
                'accent_class' => 'admin-accent-amber',
            ],
            [
                'label' => 'Total Users',
                'value' => $totalUsers,
                'value_class' => 'admin-value-slate',
                'helper' => 'Akun terdaftar pada sistem',
                'accent_class' => 'admin-accent-slate',
            ],
            [
                'label' => 'Mitra Aktif',
                'value' => $totalMitra,
                'value_class' => 'admin-value-emerald',
                'helper' => 'Mitra aktif untuk procurement',
                'accent_class' => 'admin-accent-emerald',
            ],
            [
                'label' => 'Order Aktif',
                'value' => $totalActiveMitraOrders,
                'value_class' => 'admin-value-indigo',
                'helper' => 'Order pengadaan Mitra yang masih berjalan',
                'accent_class' => 'admin-accent-indigo',
            ],
        ];

        $topPriority = collect($chartBars)->sortByDesc('value')->first()
            ?? ['label' => '-', 'value' => 0];

        return [
            'totalPendingMode' => $totalPendingMode,
            'totalPendingProcurement' => $totalPendingProcurement,
            'totalUsers' => $totalUsers,
            'totalMitra' => $totalMitra,
            'totalActiveAffiliates' => $totalActiveAffiliates,
            'totalActiveSellers' => $totalActiveSellers,
            'totalActiveOrders' => $totalMarketplaceActiveOrders,
            'totalActiveMitraOrders' => $totalActiveMitraOrders,
            'totalStoreProducts' => $totalStoreProducts,
            'loggedInUserName' => $adminName ?: 'Admin',
            'todayLabel' => Carbon::now()->format('d M Y'),
            'adminChartBars' => $chartBars,
            'adminChartTotal' => $chartTotal,
            'topPriority' => $topPriority,
            'metricCards' => $metricCards,
            'statusRows' => $statusRows,
        ];
    }
}
