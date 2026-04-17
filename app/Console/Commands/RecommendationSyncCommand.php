<?php

namespace App\Console\Commands;

use App\Services\Recommendation\RuleBasedRecommendationService;
use Illuminate\Console\Command;

class RecommendationSyncCommand extends Command
{
    protected $signature = 'recommendations:sync
        {--role=* : Role target (consumer/mitra/seller), default semua role}
        {--chunk= : Ukuran chunk user per batch}';

    protected $description = 'Sinkronkan notifikasi rekomendasi berbasis perilaku (time-trigger) untuk consumer, mitra, dan seller.';

    public function handle(RuleBasedRecommendationService $service): int
    {
        if (! (bool) config('recommendation.enabled', true)) {
            $this->warn('Recommendation engine nonaktif. Aktifkan RECOMMENDATION_ENABLED=true.');
            return self::SUCCESS;
        }

        if (! (bool) config('recommendation.sync.enabled', true)) {
            $this->warn('Sinkronisasi recommendation dinonaktifkan (RECOMMENDATION_SYNC_ENABLED=false).');
            return self::SUCCESS;
        }

        $roles = collect((array) $this->option('role'))
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn ($role) => in_array($role, ['consumer', 'mitra', 'seller'], true))
            ->unique()
            ->values()
            ->all();

        if (count($roles) === 0) {
            $roles = ['consumer', 'mitra', 'seller'];
        }

        $chunk = (int) ($this->option('chunk') ?: config('recommendation.sync.chunk', 200));
        $chunk = max(20, min(1000, $chunk));

        $this->line('Mulai sinkronisasi recommendation...');
        $this->line('Role: ' . implode(', ', $roles));
        $this->line('Chunk: ' . number_format($chunk));

        $result = $service->syncForRoles($roles, $chunk);

        $this->info(
            'Selesai. Processed: '
            . number_format((int) ($result['processed'] ?? 0))
            . ', Dispatched: '
            . number_format((int) ($result['dispatched'] ?? 0))
        );

        return self::SUCCESS;
    }
}
