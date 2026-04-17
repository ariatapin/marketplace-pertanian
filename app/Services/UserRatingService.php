<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRatingService
{
    private ?bool $tableExistsCache = null;

    public function isAvailable(): bool
    {
        if ($this->tableExistsCache === null) {
            $this->tableExistsCache = Schema::hasTable('user_ratings');
        }

        return $this->tableExistsCache;
    }

    /**
     * @return array{
     *   average_score: float,
     *   total_reviews: int,
     *   score_1: int,
     *   score_2: int,
     *   score_3: int,
     *   score_4: int,
     *   score_5: int
     * }
     */
    public function summaryForUser(int $ratedUserId): array
    {
        $default = $this->emptySummary();
        if ($ratedUserId < 1 || ! $this->isAvailable()) {
            return $default;
        }

        $base = DB::table('user_ratings')->where('rated_user_id', $ratedUserId);
        $row = (clone $base)->selectRaw(
            'COUNT(*) as total_reviews, COALESCE(AVG(score), 0) as average_score'
        )->first();
        $distribution = (clone $base)
            ->selectRaw('score, COUNT(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');

        return [
            'average_score' => round((float) ($row->average_score ?? 0), 2),
            'total_reviews' => (int) ($row->total_reviews ?? 0),
            'score_1' => (int) ($distribution[1] ?? 0),
            'score_2' => (int) ($distribution[2] ?? 0),
            'score_3' => (int) ($distribution[3] ?? 0),
            'score_4' => (int) ($distribution[4] ?? 0),
            'score_5' => (int) ($distribution[5] ?? 0),
        ];
    }

    /**
     * @param  iterable<int, int|string>  $userIds
     * @return Collection<int, array{average_score: float,total_reviews: int}>
     */
    public function summariesForUsers(iterable $userIds): Collection
    {
        $normalizedIds = collect($userIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return collect();
        }

        $fallback = $normalizedIds->mapWithKeys(fn (int $id): array => [
            $id => [
                'average_score' => 0.0,
                'total_reviews' => 0,
            ],
        ]);

        if (! $this->isAvailable()) {
            return $fallback;
        }

        $rows = DB::table('user_ratings')
            ->whereIn('rated_user_id', $normalizedIds)
            ->groupBy('rated_user_id')
            ->get([
                'rated_user_id',
                DB::raw('COUNT(*) as total_reviews'),
                DB::raw('COALESCE(AVG(score), 0) as average_score'),
            ]);

        $mapped = $rows->mapWithKeys(function ($row): array {
            $ratedUserId = (int) ($row->rated_user_id ?? 0);

            return [
                $ratedUserId => [
                    'average_score' => round((float) ($row->average_score ?? 0), 2),
                    'total_reviews' => (int) ($row->total_reviews ?? 0),
                ],
            ];
        });

        return $fallback->merge($mapped);
    }

    /**
     * @param  iterable<int, int|string>  $orderIds
     * @return Collection<int, object>
     */
    public function ratingsForBuyerOrders(int $buyerId, iterable $orderIds): Collection
    {
        $normalizedOrderIds = collect($orderIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($buyerId < 1 || $normalizedOrderIds->isEmpty() || ! $this->isAvailable()) {
            return collect();
        }

        return DB::table('user_ratings')
            ->where('buyer_id', $buyerId)
            ->whereIn('order_id', $normalizedOrderIds)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'order_id',
                'buyer_id',
                'rated_user_id',
                'score',
                'review',
                'updated_at',
            ])
            ->keyBy('order_id');
    }

    public function hasRatingForOrder(int $orderId, int $buyerId, ?int $ratedUserId = null): bool
    {
        if ($orderId < 1 || $buyerId < 1 || ! $this->isAvailable()) {
            return false;
        }

        $query = DB::table('user_ratings')
            ->where('order_id', $orderId)
            ->where('buyer_id', $buyerId);

        if ($ratedUserId !== null && $ratedUserId > 0) {
            $query->where('rated_user_id', $ratedUserId);
        }

        return $query->exists();
    }

    public function storeOnceForOrder(
        int $orderId,
        int $buyerId,
        int $ratedUserId,
        int $score,
        ?string $review = null
    ): bool {
        if ($orderId < 1 || $buyerId < 1 || $ratedUserId < 1 || ! $this->isAvailable()) {
            return false;
        }

        $inserted = DB::table('user_ratings')->insertOrIgnore([
            'order_id' => $orderId,
            'buyer_id' => $buyerId,
            'rated_user_id' => $ratedUserId,
            'score' => max(1, min(5, $score)),
            'review' => $this->normalizeReview($review),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted > 0;
    }

    public function upsertForOrder(
        int $orderId,
        int $buyerId,
        int $ratedUserId,
        int $score,
        ?string $review = null
    ): void {
        $this->storeOnceForOrder($orderId, $buyerId, $ratedUserId, $score, $review);
    }

    /**
     * @return array{
     *   average_score: float,
     *   total_reviews: int,
     *   score_1: int,
     *   score_2: int,
     *   score_3: int,
     *   score_4: int,
     *   score_5: int
     * }
     */
    private function emptySummary(): array
    {
        return [
            'average_score' => 0.0,
            'total_reviews' => 0,
            'score_1' => 0,
            'score_2' => 0,
            'score_3' => 0,
            'score_4' => 0,
            'score_5' => 0,
        ];
    }

    private function normalizeReview(?string $review): ?string
    {
        $review = trim((string) $review);

        return $review !== '' ? $review : null;
    }
}
