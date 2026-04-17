<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PaymentMethodService
{
    public function defaultMethod(): string
    {
        $configured = (string) config('payments.default_method', 'bank_transfer');

        return $this->isSupported($configured) ? $configured : 'bank_transfer';
    }

    public function methods(): array
    {
        $methods = config('payments.methods', []);
        if (! is_array($methods)) {
            return [];
        }

        return collect($methods)
            ->map(function ($meta, $method) {
                $safeMeta = is_array($meta) ? $meta : [];

                return [
                    'method' => (string) $method,
                    'label' => trim((string) Arr::get($safeMeta, 'label', strtoupper((string) $method))),
                    'kind' => trim((string) Arr::get($safeMeta, 'kind', 'wallet')),
                    'is_active' => (bool) Arr::get($safeMeta, 'is_active', true),
                    'account_name' => trim((string) Arr::get($safeMeta, 'account_name', '')),
                    'account_number' => trim((string) Arr::get($safeMeta, 'account_number', '')),
                    'provider' => trim((string) Arr::get($safeMeta, 'provider', '')),
                ];
            })
            ->filter(fn ($item) => $item['method'] !== '')
            ->values()
            ->all();
    }

    public function activeMethods(): array
    {
        return collect($this->methods())
            ->filter(fn ($item) => (bool) ($item['is_active'] ?? false))
            ->values()
            ->all();
    }

    public function activeMethodKeys(): array
    {
        return collect($this->activeMethods())
            ->pluck('method')
            ->values()
            ->all();
    }

    public function checkoutOptions(): array
    {
        return collect($this->activeMethods())
            ->map(function (array $item): array {
                return [
                    'method' => $item['method'],
                    'label' => $item['label'],
                    'helper' => $item['kind'] === 'bank'
                        ? 'Upload bukti transfer bank'
                        : 'Upload bukti pembayaran e-wallet',
                ];
            })
            ->values()
            ->all();
    }

    public function methodsForConsumerMode(string $mode): array
    {
        $normalizedMode = strtolower(trim($mode));
        $active = collect($this->activeMethods());

        if ($normalizedMode === 'affiliate') {
            $walletOnly = $active
                ->filter(fn (array $item) => ($item['kind'] ?? 'wallet') === 'wallet')
                ->values();

            if ($walletOnly->isNotEmpty()) {
                return $walletOnly->all();
            }
        }

        if ($normalizedMode === 'farmer_seller') {
            $bankOnly = $active
                ->filter(fn (array $item) => ($item['method'] ?? '') === 'bank_transfer')
                ->values();

            if ($bankOnly->isNotEmpty()) {
                return $bankOnly->all();
            }
        }

        return $active->values()->all();
    }

    public function checkoutOptionsForConsumerMode(string $mode): array
    {
        return collect($this->methodsForConsumerMode($mode))
            ->map(function (array $item): array {
                return [
                    'method' => $item['method'],
                    'label' => $item['label'],
                    'helper' => $item['kind'] === 'bank'
                        ? 'Upload bukti transfer bank'
                        : 'Upload bukti pembayaran e-wallet',
                ];
            })
            ->values()
            ->all();
    }

    public function defaultMethodForConsumerMode(string $mode): string
    {
        $allowed = collect($this->methodsForConsumerMode($mode));
        $default = $this->defaultMethod();

        if ($allowed->firstWhere('method', $default)) {
            return $default;
        }

        return (string) ($allowed->first()['method'] ?? $default);
    }

    public function instructionCards(): array
    {
        return collect($this->activeMethods())
            ->map(function (array $item): array {
                return [
                    'method' => $item['method'],
                    'label' => $item['label'],
                    'provider' => $item['provider'] !== '' ? $item['provider'] : $item['label'],
                    'account_name' => $item['account_name'] !== '' ? $item['account_name'] : '-',
                    'account_number' => $item['account_number'] !== '' ? $item['account_number'] : '-',
                ];
            })
            ->values()
            ->all();
    }

    public function isSupported(?string $method): bool
    {
        if ($method === null || trim($method) === '') {
            return false;
        }

        return in_array($method, $this->activeMethodKeys(), true);
    }

    public function normalize(?string $method): string
    {
        $candidate = trim((string) $method);
        if ($candidate === '') {
            return $this->defaultMethod();
        }

        return $this->isSupported($candidate) ? $candidate : $this->defaultMethod();
    }

    public function assertSupported(?string $method): string
    {
        $normalized = $this->normalize($method);
        if (! $this->isSupported($normalized)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak didukung.',
            ]);
        }

        return $normalized;
    }

    public function assertSupportedForConsumerMode(?string $method, string $mode): string
    {
        $normalizedMode = strtolower(trim($mode));
        $resolved = $this->normalize($method);
        $allowedMethods = collect($this->methodsForConsumerMode($normalizedMode))
            ->pluck('method')
            ->values()
            ->all();

        if (! in_array($resolved, $allowedMethods, true)) {
            throw ValidationException::withMessages([
                'payment_method' => $this->unsupportedMessageForMode($normalizedMode),
            ]);
        }

        return $resolved;
    }

    public function label(?string $method): string
    {
        $normalized = $this->normalize($method);
        $match = collect($this->activeMethods())->firstWhere('method', $normalized);

        return (string) ($match['label'] ?? strtoupper(str_replace('_', ' ', $normalized)));
    }

    /**
     * Mengambil jenis metode pembayaran aktif (bank/wallet).
     */
    public function kind(?string $method): string
    {
        $normalized = $this->normalize($method);
        $match = collect($this->activeMethods())->firstWhere('method', $normalized);
        $kind = strtolower(trim((string) ($match['kind'] ?? 'wallet')));

        return in_array($kind, ['bank', 'wallet'], true) ? $kind : 'wallet';
    }

    public function labelMap(): array
    {
        return collect($this->activeMethods())
            ->mapWithKeys(fn (array $item) => [$item['method'] => $item['label']])
            ->all();
    }

    private function unsupportedMessageForMode(string $mode): string
    {
        return match ($mode) {
            'affiliate' => 'Mode affiliate hanya dapat memakai pembayaran e-wallet.',
            'farmer_seller' => 'Mode penjual hanya dapat memakai transfer bank untuk pembelian.',
            default => 'Metode pembayaran tidak didukung.',
        };
    }
}
