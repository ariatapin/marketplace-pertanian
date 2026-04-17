<?php

namespace Tests\Unit;

use App\Support\AdminDashboardViewModelFactory;
use PHPUnit\Framework\TestCase;

class AdminDashboardViewModelFactoryTest extends TestCase
{
    public function test_order_aktif_card_uses_active_mitra_orders_metric(): void
    {
        $factory = new AdminDashboardViewModelFactory();

        $viewModel = $factory->make([
            'pending_affiliate_applications' => 0,
            'pending_farmer_seller_applications' => 0,
            'active_mitra_orders' => 7,
            'active_orders' => 99,
            'total_users' => 10,
            'total_mitra' => 3,
            'active_affiliates' => 2,
            'active_sellers' => 4,
            'total_store_products' => 11,
            'total_pengajuan' => 1,
        ]);

        $orderCard = collect($viewModel['metricCards'] ?? [])
            ->first(fn (array $card): bool => ($card['label'] ?? '') === 'Order Aktif');

        $this->assertNotNull($orderCard);
        $this->assertSame(7, (int) ($orderCard['value'] ?? 0));
        $this->assertSame(7, (int) ($viewModel['totalActiveMitraOrders'] ?? 0));
        $this->assertSame(99, (int) ($viewModel['totalActiveOrders'] ?? 0));
    }
}

