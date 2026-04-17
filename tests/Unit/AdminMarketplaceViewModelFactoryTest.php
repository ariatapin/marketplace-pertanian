<?php

namespace Tests\Unit;

use App\Support\AdminMarketplaceViewModelFactory;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AdminMarketplaceViewModelFactoryTest extends TestCase
{
    public function test_it_maps_summary_and_market_rows_for_admin_marketplace(): void
    {
        $factory = new AdminMarketplaceViewModelFactory();

        $result = $factory->make(
            summary: [
                'mitra_products' => 10,
                'farmer_harvest_pending' => 3,
            ],
            marketplaceRows: new Collection([
                (object) [
                    'id' => 12,
                    'source' => 'farmer',
                    'name' => 'Jagung Manis',
                    'owner_name' => '',
                    'owner_email' => '',
                    'price' => 12500,
                    'stock_qty' => 3,
                    'status' => 'pending',
                ],
            ]),
            notificationRows: new Collection(),
            announcementRows: new Collection()
        );

        $this->assertSame(10, $result['summary']['mitra_products']);
        $this->assertSame(0, $result['summary']['farmer_harvest_approved']);
        $this->assertSame(1, $result['marketRowsCount']);

        $row = $result['marketRowsView'][0];
        $this->assertSame(12, $row['id']);
        $this->assertSame('Jagung Manis', $row['name']);
        $this->assertSame('-', $row['owner_name']);
        $this->assertSame('-', $row['owner_email']);
        $this->assertSame('Rp12.500', $row['price_label']);
        $this->assertSame('3', $row['stock_label']);
        $this->assertTrue($row['is_farmer']);
        $this->assertSame(['pending', 'approved', 'rejected'], $row['status_options']);
    }
}
