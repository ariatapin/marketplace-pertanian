<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConsumerPurchasePolicyService
{
    public function __construct(
        protected PaymentMethodService $paymentMethods
    ) {}

    public function resolveMode(User $user): string
    {
        if (! $user->isConsumer()) {
            return 'buyer';
        }

        if (! Schema::hasTable('consumer_profiles')) {
            return 'buyer';
        }

        $profile = DB::table('consumer_profiles')
            ->select('mode', 'mode_status')
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return 'buyer';
        }

        $mode = strtolower(trim((string) ($profile->mode ?? 'buyer')));
        $status = strtolower(trim((string) ($profile->mode_status ?? 'none')));

        if ($status !== 'approved') {
            return 'buyer';
        }

        return in_array($mode, ['affiliate', 'farmer_seller'], true) ? $mode : 'buyer';
    }

    public function checkoutOptions(User $user): array
    {
        if (! $this->canCheckout($user)) {
            return [];
        }

        $mode = $this->resolveMode($user);

        return $this->paymentMethods->checkoutOptionsForConsumerMode($mode);
    }

    public function defaultCheckoutMethod(User $user): string
    {
        if (! $this->canCheckout($user)) {
            return $this->paymentMethods->defaultMethod();
        }

        return $this->paymentMethods->defaultMethodForConsumerMode($this->resolveMode($user));
    }

    public function assertCheckoutMethod(User $user, ?string $paymentMethod): string
    {
        if (! $this->canCheckout($user)) {
            throw ValidationException::withMessages([
                'payment_method' => $this->checkoutUnavailableMessage($user),
            ]);
        }

        return $this->paymentMethods->assertSupportedForConsumerMode(
            $paymentMethod,
            $this->resolveMode($user)
        );
    }

    public function modeMeta(User $user): array
    {
        $mode = $this->resolveMode($user);

        return match ($mode) {
            'affiliate' => [
                'mode' => 'affiliate',
                'label' => 'Affiliate',
                'can_checkout' => true,
                'helper' => 'Mode affiliate: checkout dibatasi ke e-wallet agar proses lebih cepat.',
            ],
            'farmer_seller' => [
                'mode' => 'farmer_seller',
                'label' => 'Penjual',
                'can_checkout' => true,
                'helper' => 'Mode penjual: checkout dibatasi ke transfer bank untuk pencatatan usaha.',
            ],
            default => [
                'mode' => 'buyer',
                'label' => 'Buyer',
                'can_checkout' => true,
                'helper' => 'Mode buyer: checkout tersedia untuk semua metode pembayaran aktif.',
            ],
        };
    }

    public function canCheckout(User $user): bool
    {
        return $user->isConsumer();
    }

    public function checkoutUnavailableMessage(User $user): string
    {
        return 'Checkout hanya tersedia untuk akun consumer.';
    }
}
