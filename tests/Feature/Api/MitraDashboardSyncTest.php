<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Location\LocationResolver;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class MitraDashboardSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_dashboard_api_uses_same_procurement_active_rules_as_web_dashboard(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        DB::table('admin_orders')->insert([
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 100000,
                'status' => 'pending',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 100000,
                'status' => 'approved',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 100000,
                'status' => 'processing',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 100000,
                'status' => 'shipped',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 100000,
                'status' => 'delivered',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 100000,
                'status' => 'cancelled',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('orders')->insert([
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $mitra->id,
                'order_source' => 'store_online',
                'total_amount' => 90000,
                'payment_status' => 'paid',
                'order_status' => 'paid',
                'payment_proof_url' => null,
                'shipping_status' => 'pending',
                'resi_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $mitra->id,
                'order_source' => 'store_online',
                'total_amount' => 95000,
                'payment_status' => 'paid',
                'order_status' => 'shipped',
                'payment_proof_url' => null,
                'shipping_status' => 'shipped',
                'resi_number' => 'RESI-11',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $mitra->id,
                'order_source' => 'store_online',
                'total_amount' => 98000,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'payment_proof_url' => null,
                'shipping_status' => 'delivered',
                'resi_number' => 'RESI-12',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->mock(LocationResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forUser')->once()->andReturn([
                'type' => 'custom',
                'id' => 1,
                'lat' => -6.2,
                'lng' => 106.816,
                'label' => 'Jakarta',
            ]);
        });

        $this->mock(WeatherService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('current')->once()->andReturn([
                'main' => ['temp' => 30, 'humidity' => 80],
                'wind' => ['speed' => 2],
            ]);
            $mock->shouldReceive('forecast')->once()->andReturn(['list' => []]);
        });

        $this->mock(WeatherAlertEngine::class, function (MockInterface $mock): void {
            $mock->shouldReceive('evaluateForecast')->once()->andReturn([
                'severity' => 'green',
                'message' => 'Cuaca aman',
            ]);
        });

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.counts.procurement_orders', 4)
            ->assertJsonPath('data.counts.procurement_orders_total', 6)
            ->assertJsonPath('data.counts.customer_orders_active', 2)
            ->assertJsonPath('data.counts.customer_orders_completed', 1);
    }
}

