<?php

namespace App\Http\Controllers;

use App\Services\ConsumerPurchasePolicyService;
use App\Services\CheckoutSplitService;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutSplitService $service,
        protected PaymentMethodService $paymentMethods,
        protected ConsumerPurchasePolicyService $consumerPurchasePolicy
    ) {}

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string'],
            'cart_item_ids' => ['nullable', 'array'],
            'cart_item_ids.*' => ['integer', 'min:1'],
            'selection_required' => ['nullable', 'boolean'],
        ]);

        $selectedMethod = $this->consumerPurchasePolicy->assertCheckoutMethod(
            $request->user(),
            $validated['payment_method'] ?? null
        );
        $selectedCartItemIds = collect($validated['cart_item_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (($validated['selection_required'] ?? false) && empty($selectedCartItemIds)) {
            return back()->withErrors([
                'cart_item_ids' => 'Pilih minimal satu produk dari keranjang untuk checkout.',
            ]);
        }

        $orderIds = $this->service->checkout(
            (int) $request->user()->id,
            $selectedMethod,
            $selectedCartItemIds
        );

        $paymentKind = $this->paymentMethods->kind($selectedMethod);
        $statusMessage = $paymentKind === 'wallet'
            ? 'Checkout berhasil. Pembayaran saldo langsung diproses untuk order: #' . implode(', #', $orderIds)
            : 'Checkout sukses. Lanjutkan pembayaran transfer untuk order: #' . implode(', #', $orderIds);

        return redirect()
            ->route('orders.mine')
            ->with('status', $statusMessage);
    }
}
