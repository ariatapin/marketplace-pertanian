<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderShipmentService
{
    public function ensurePending(int $orderId): void
    {
        if ($orderId <= 0 || ! Schema::hasTable('shipments')) {
            return;
        }

        DB::table('shipments')->insertOrIgnore([
            'order_id' => $orderId,
            'courier' => null,
            'service' => null,
            'tracking_number' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function markShipped(int $orderId, ?string $trackingNumber = null): void
    {
        if ($orderId <= 0 || ! Schema::hasTable('shipments')) {
            return;
        }

        $shipment = $this->lockShipmentForOrder($orderId);
        if (! $shipment) {
            return;
        }

        $currentStatus = strtolower(trim((string) ($shipment->status ?? 'pending')));
        if ($currentStatus === 'delivered') {
            return;
        }

        $incomingTracking = trim((string) $trackingNumber);
        $existingTracking = trim((string) ($shipment->tracking_number ?? ''));
        $resolvedTracking = $incomingTracking !== ''
            ? $incomingTracking
            : ($existingTracking !== '' ? $existingTracking : null);

        $payload = [
            'status' => 'shipped',
            'tracking_number' => $resolvedTracking,
            'updated_at' => now(),
        ];

        if (empty($shipment->shipped_at)) {
            $payload['shipped_at'] = now();
        }

        DB::table('shipments')
            ->where('id', (int) $shipment->id)
            ->update($payload);
    }

    public function markDelivered(int $orderId): void
    {
        if ($orderId <= 0 || ! Schema::hasTable('shipments')) {
            return;
        }

        $shipment = $this->lockShipmentForOrder($orderId);
        if (! $shipment) {
            return;
        }

        $currentStatus = strtolower(trim((string) ($shipment->status ?? 'pending')));
        if ($currentStatus === 'delivered') {
            return;
        }

        $payload = [
            'status' => 'delivered',
            'updated_at' => now(),
        ];

        if (empty($shipment->shipped_at)) {
            $payload['shipped_at'] = now();
        }

        if (empty($shipment->delivered_at)) {
            $payload['delivered_at'] = now();
        }

        DB::table('shipments')
            ->where('id', (int) $shipment->id)
            ->update($payload);
    }

    private function lockShipmentForOrder(int $orderId): ?object
    {
        $this->ensurePending($orderId);

        return DB::table('shipments')
            ->where('order_id', $orderId)
            ->lockForUpdate()
            ->first([
                'id',
                'order_id',
                'tracking_number',
                'status',
                'shipped_at',
                'delivered_at',
            ]);
    }
}
