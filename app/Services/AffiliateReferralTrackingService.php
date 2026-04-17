<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AffiliateReferralTrackingService
{
    private const EVENT_CLICK = 'click';
    private const EVENT_ADD_TO_CART = 'add_to_cart';
    private const EVENT_CHECKOUT_CREATED = 'checkout_created';

    private const CLICK_DEDUP_SECONDS = 300;
    private const SESSION_CLICK_KEY_PREFIX = 'marketplace.affiliate_click_seen.';

    /**
     * Catat klik referral dari landing dan dedupe per sesi.
     */
    public function trackClick(Request $request, int $affiliateUserId, ?int $actorUserId = null): void
    {
        if (! $this->trackingTableReady() || $affiliateUserId <= 0) {
            return;
        }

        $actorId = (int) ($actorUserId ?? 0);
        if ($actorId > 0 && $actorId === $affiliateUserId) {
            return;
        }

        $session = $request->session();
        $sessionKey = self::SESSION_CLICK_KEY_PREFIX . $affiliateUserId;
        $lastSeen = (int) $session->get($sessionKey, 0);
        $nowTs = now()->timestamp;

        if ($lastSeen > 0 && ($nowTs - $lastSeen) < self::CLICK_DEDUP_SECONDS) {
            return;
        }

        $session->put($sessionKey, $nowTs);

        $this->insertEvent(
            affiliateUserId: $affiliateUserId,
            actorUserId: $actorId > 0 ? $actorId : null,
            eventType: self::EVENT_CLICK,
            sessionId: $session->getId(),
            productId: null,
            orderId: null,
            meta: ['entry' => 'landing_ref']
        );
    }

    /**
     * Catat produk referral yang ditambahkan ke keranjang.
     */
    public function trackAddToCart(int $affiliateUserId, int $actorUserId, int $productId, ?string $sessionId = null): void
    {
        if (! $this->trackingTableReady() || $affiliateUserId <= 0 || $actorUserId <= 0 || $productId <= 0) {
            return;
        }

        if ($affiliateUserId === $actorUserId) {
            return;
        }

        $this->insertEvent(
            affiliateUserId: $affiliateUserId,
            actorUserId: $actorUserId,
            eventType: self::EVENT_ADD_TO_CART,
            sessionId: $sessionId,
            productId: $productId,
            orderId: null,
            meta: null
        );
    }

    /**
     * Catat checkout yang membawa atribusi referral affiliate.
     */
    public function trackCheckoutCreated(int $affiliateUserId, int $actorUserId, int $orderId, ?string $sessionId = null): void
    {
        if (! $this->trackingTableReady() || $affiliateUserId <= 0 || $actorUserId <= 0 || $orderId <= 0) {
            return;
        }

        if ($affiliateUserId === $actorUserId) {
            return;
        }

        $this->insertEvent(
            affiliateUserId: $affiliateUserId,
            actorUserId: $actorUserId,
            eventType: self::EVENT_CHECKOUT_CREATED,
            sessionId: $sessionId,
            productId: null,
            orderId: $orderId,
            meta: null
        );
    }

    /**
     * Ringkasan performa link affiliate untuk dashboard.
     *
     * @return array<string, float|int>
     */
    public function summaryForAffiliate(int $affiliateUserId): array
    {
        $summary = [
            'clicks' => 0,
            'add_to_cart' => 0,
            'checkout_created' => 0,
            'completed_orders' => 0,
            'conversion_checkout_percent' => 0.0,
            'conversion_completed_percent' => 0.0,
        ];

        if ($affiliateUserId <= 0) {
            return $summary;
        }

        if ($this->trackingTableReady()) {
            $base = DB::table('affiliate_referral_events')
                ->where('affiliate_user_id', $affiliateUserId);

            $summary['clicks'] = (int) (clone $base)->where('event_type', self::EVENT_CLICK)->count();
            $summary['add_to_cart'] = (int) (clone $base)->where('event_type', self::EVENT_ADD_TO_CART)->count();
            $summary['checkout_created'] = (int) (clone $base)
                ->where('event_type', self::EVENT_CHECKOUT_CREATED)
                ->distinct('order_id')
                ->count('order_id');
        }

        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $summary['completed_orders'] = (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.affiliate_id', $affiliateUserId)
                ->where('orders.order_source', 'store_online')
                ->where('orders.order_status', 'completed')
                ->where('orders.payment_status', 'paid')
                ->distinct('order_items.order_id')
                ->count('order_items.order_id');
        }

        $clicks = (int) $summary['clicks'];
        if ($clicks > 0) {
            $summary['conversion_checkout_percent'] = round(((int) $summary['checkout_created'] / $clicks) * 100, 2);
            $summary['conversion_completed_percent'] = round(((int) $summary['completed_orders'] / $clicks) * 100, 2);
        }

        return $summary;
    }

    /**
     * Ringkasan performa affiliate level admin.
     *
     * @return array<string, float|int>
     */
    public function summaryForAdmin(): array
    {
        $summary = [
            'total_clicks' => 0,
            'total_add_to_cart' => 0,
            'total_checkout_created' => 0,
            'total_completed_orders' => 0,
            'conversion_checkout_percent' => 0.0,
            'conversion_completed_percent' => 0.0,
        ];

        if ($this->trackingTableReady()) {
            $summary['total_clicks'] = (int) DB::table('affiliate_referral_events')
                ->where('event_type', self::EVENT_CLICK)
                ->count();
            $summary['total_add_to_cart'] = (int) DB::table('affiliate_referral_events')
                ->where('event_type', self::EVENT_ADD_TO_CART)
                ->count();
            $summary['total_checkout_created'] = (int) DB::table('affiliate_referral_events')
                ->where('event_type', self::EVENT_CHECKOUT_CREATED)
                ->distinct('order_id')
                ->count('order_id');
        }

        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $summary['total_completed_orders'] = (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNotNull('order_items.affiliate_id')
                ->where('orders.order_source', 'store_online')
                ->where('orders.order_status', 'completed')
                ->where('orders.payment_status', 'paid')
                ->distinct('order_items.order_id')
                ->count('order_items.order_id');
        }

        $clicks = (int) $summary['total_clicks'];
        if ($clicks > 0) {
            $summary['conversion_checkout_percent'] = round(((int) $summary['total_checkout_created'] / $clicks) * 100, 2);
            $summary['conversion_completed_percent'] = round(((int) $summary['total_completed_orders'] / $clicks) * 100, 2);
        }

        return $summary;
    }

    /**
     * Top affiliate berdasarkan jumlah order selesai.
     */
    public function topAffiliatesForAdmin(int $limit = 5)
    {
        if ($limit <= 0 || ! Schema::hasTable('order_items') || ! Schema::hasTable('orders') || ! Schema::hasTable('users')) {
            return collect();
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('users as affiliate', 'affiliate.id', '=', 'order_items.affiliate_id')
            ->whereNotNull('order_items.affiliate_id')
            ->where('orders.order_source', 'store_online')
            ->where('orders.order_status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->groupBy('order_items.affiliate_id', 'affiliate.name', 'affiliate.email')
            ->orderByDesc(DB::raw('COUNT(DISTINCT order_items.order_id)'))
            ->limit($limit)
            ->get([
                'order_items.affiliate_id',
                'affiliate.name',
                'affiliate.email',
                DB::raw('COUNT(DISTINCT order_items.order_id) as completed_orders'),
                DB::raw('SUM(order_items.commission_amount) as total_commission'),
            ]);
    }

    /**
     * Simpan event tracking dengan fallback logging jika gagal.
     *
     * @param  array<string,mixed>|null  $meta
     */
    private function insertEvent(
        int $affiliateUserId,
        ?int $actorUserId,
        string $eventType,
        ?string $sessionId,
        ?int $productId,
        ?int $orderId,
        ?array $meta
    ): void {
        if (! $this->trackingTableReady()) {
            return;
        }

        try {
            DB::table('affiliate_referral_events')->insert([
                'affiliate_user_id' => $affiliateUserId,
                'actor_user_id' => $actorUserId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'event_type' => $eventType,
                'session_id' => $sessionId !== null ? trim($sessionId) : null,
                'meta' => $meta !== null ? json_encode($meta) : null,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal menyimpan affiliate referral event.', [
                'affiliate_user_id' => $affiliateUserId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function trackingTableReady(): bool
    {
        return Schema::hasTable('affiliate_referral_events');
    }
}

