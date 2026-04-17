<?php

use App\Http\Controllers\Admin\DashboardPageController as AdminDashboardPageController;
use App\Http\Controllers\Admin\FinancePageController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\MarketplacePageController;
use App\Http\Controllers\Admin\ModeRequestsPageController;
use App\Http\Controllers\Admin\OrdersPageController;
use App\Http\Controllers\Admin\ProcurementPageController;
use App\Http\Controllers\Admin\RecommendationRulePageController;
use App\Http\Controllers\Admin\ReportsPageController;
use App\Http\Controllers\Admin\SettingsPageController;
use App\Http\Controllers\Admin\UsersPageController;
use App\Http\Controllers\Admin\WarehousePageController;
use App\Http\Controllers\Admin\WeatherPageController;
use App\Http\Controllers\Admin\MitraApplicationReviewController;
use App\Http\Controllers\AdminModeApprovalController;
use App\Http\Controllers\AdminProcurementController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminDisputeController;
use App\Http\Controllers\AdminWithdrawController;
use App\Http\Controllers\AppPageController;
use App\Http\Controllers\BuyerOrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConsumerModeDashboardController;
use App\Http\Controllers\DemoWalletTopupController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\MitraApplicationController;
use App\Http\Controllers\Mitra\OrderController as MitraOrderController;
use App\Http\Controllers\Mitra\PageController as MitraPageController;
use App\Http\Controllers\Mitra\StoreProductController;
use App\Http\Controllers\MarketplaceProductController;
use App\Http\Controllers\MitraProcurementController;
use App\Http\Controllers\ProfileModeController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerOrderStatusController;
use App\Http\Controllers\P2PSellerPaymentController;
use App\Http\Controllers\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::get('/dashboard', [AppPageController::class, 'dashboard'])->name('dashboard');
    Route::get('/affiliate/dashboard', [ConsumerModeDashboardController::class, 'affiliate'])
        ->middleware('can:access-affiliate-api')
        ->name('affiliate.dashboard');
    Route::get('/affiliate/dipasarkan', [ConsumerModeDashboardController::class, 'marketedProducts'])
        ->middleware('can:access-affiliate-api')
        ->name('affiliate.marketings');
    Route::post('/affiliate/dipasarkan/pilih-produk', [ConsumerModeDashboardController::class, 'promoteProduct'])
        ->middleware('can:access-affiliate-api')
        ->name('affiliate.marketings.promote');
    Route::get('/affiliate/performa', [ConsumerModeDashboardController::class, 'performance'])
        ->middleware('can:access-affiliate-api')
        ->name('affiliate.performance');
    Route::get('/affiliate/dompet', [ConsumerModeDashboardController::class, 'walletPage'])
        ->middleware('can:access-affiliate-api')
        ->name('affiliate.wallet');
    Route::get('/seller/dashboard', [ConsumerModeDashboardController::class, 'seller'])
        ->middleware('can:access-seller-api')
        ->name('seller.dashboard');
    Route::get('/seller/orders', [ConsumerModeDashboardController::class, 'sellerOrders'])
        ->middleware('can:access-seller-api')
        ->name('seller.orders.index');
    Route::get('/seller/products', [SellerProductController::class, 'index'])
        ->middleware('can:access-seller-api')
        ->name('seller.products.index');
    Route::get('/seller/products/create', [SellerProductController::class, 'create'])
        ->middleware('can:access-seller-api')
        ->name('seller.products.create');
    Route::post('/seller/products', [SellerProductController::class, 'store'])
        ->middleware('can:access-seller-api')
        ->name('seller.products.store');
    Route::patch('/seller/products/{harvestId}', [SellerProductController::class, 'update'])
        ->middleware('can:access-seller-api')
        ->whereNumber('harvestId')
        ->name('seller.products.update');
    Route::delete('/seller/products/{harvestId}', [SellerProductController::class, 'destroy'])
        ->middleware('can:access-seller-api')
        ->whereNumber('harvestId')
        ->name('seller.products.destroy');
    Route::post('/seller/orders/{orderId}/mark-packed', [SellerOrderStatusController::class, 'markPacked'])
        ->middleware('can:access-seller-api')
        ->whereNumber('orderId')
        ->name('seller.orders.markPacked');
    Route::post('/seller/orders/{orderId}/mark-shipped', [SellerOrderStatusController::class, 'markShipped'])
        ->middleware('can:access-seller-api')
        ->whereNumber('orderId')
        ->name('seller.orders.markShipped');
    Route::post('/seller/orders/{orderId}/confirm-cash', [P2PSellerPaymentController::class, 'confirmCash'])
        ->middleware('can:access-seller-api')
        ->whereNumber('orderId')
        ->name('seller.orders.confirmCash');
    Route::get('/akun', [AppPageController::class, 'account'])
        ->middleware('block.mitra.marketplace')
        ->name('account.show');
    Route::patch('/akun/rekening', [AppPageController::class, 'updateAccountBank'])
        ->middleware('block.mitra.marketplace')
        ->name('account.bank.update');

    Route::middleware(['role:admin'])->group(function () {
        // CATATAN-AUDIT: Dashboard + modul utama admin.
        Route::get('/admin/dashboard', AdminDashboardPageController::class)->name('admin.dashboard');
        Route::get('/admin/profile', [AdminProfileController::class, 'show'])->name('admin.profile');
        Route::patch('/admin/profile/account', [AdminProfileController::class, 'updateAccount'])->name('admin.profile.account.update');
        Route::post('/admin/profile/avatar', [AdminProfileController::class, 'updateAvatar'])->name('admin.profile.avatar.update');
        Route::delete('/admin/profile/avatar', [AdminProfileController::class, 'destroyAvatar'])->name('admin.profile.avatar.destroy');
        Route::patch('/admin/profile/ops', [AdminProfileController::class, 'updateOps'])->name('admin.profile.ops.update');
        Route::get('/admin/settings', SettingsPageController::class)->name('admin.settings');
        Route::get('/admin/mode-requests', ModeRequestsPageController::class)->name('admin.modeRequests.index');
        Route::get('/admin/procurement', ProcurementPageController::class)->name('admin.modules.procurement');
        Route::get('/admin/marketplace', MarketplacePageController::class)->name('admin.modules.marketplace');
        Route::get('/admin/users', UsersPageController::class)->name('admin.modules.users');
        Route::get('/admin/orders', OrdersPageController::class)->name('admin.modules.orders');
        Route::get('/admin/finance', FinancePageController::class)->name('admin.modules.finance');
        Route::get('/admin/finance/withdraw/{role}', [FinancePageController::class, 'withdrawByRole'])
            ->whereIn('role', ['mitra', 'affiliate', 'farmer_seller'])
            ->name('admin.modules.finance.withdraw.role');
        Route::get('/admin/weather', WeatherPageController::class)->name('admin.modules.weather');
        // CATATAN-AUDIT: Rule recommendation management terpisah dari modul cuaca agar governance lebih jelas.
        Route::get('/admin/recommendation-rules', RecommendationRulePageController::class)->name('admin.modules.recommendationRules');
        Route::get('/admin/warehouse', WarehousePageController::class)->name('admin.modules.warehouse');
        Route::post('/admin/warehouse', [WarehousePageController::class, 'store'])->name('admin.modules.warehouse.store');
        Route::patch('/admin/warehouse/{warehouseId}', [WarehousePageController::class, 'update'])
            ->whereNumber('warehouseId')
            ->name('admin.modules.warehouse.update');
        Route::post('/admin/warehouse/{warehouseId}/toggle-active', [WarehousePageController::class, 'toggleActive'])
            ->whereNumber('warehouseId')
            ->name('admin.modules.warehouse.toggleActive');
        Route::get('/admin/reports', ReportsPageController::class)->name('admin.modules.reports');
        Route::get('/admin/reports/{reportId}', [ReportsPageController::class, 'show'])
            ->whereNumber('reportId')
            ->name('admin.modules.reports.show');
        Route::get('/admin/reports/products/{productReportId}', [ReportsPageController::class, 'showProductReport'])
            ->whereNumber('productReportId')
            ->name('admin.modules.reports.products.show');
    });

    Route::middleware(['role:mitra'])->group(function () {
        Route::get('/mitra/dashboard', [MitraPageController::class, 'dashboard'])->name('mitra.dashboard');
        Route::get('/mitra/procurement', [MitraPageController::class, 'procurement'])->name('mitra.procurement.index');
        Route::get('/mitra/orders', [MitraOrderController::class, 'index'])->name('mitra.orders.index');
        Route::get('/mitra/orders/{orderId}', [MitraOrderController::class, 'show'])->name('mitra.orders.show');
        Route::get('/mitra/finance', [MitraPageController::class, 'finance'])->name('mitra.finance');
        Route::patch('/mitra/finance/bank', [MitraPageController::class, 'updateFinanceBank'])->name('mitra.finance.bank.update');
        Route::get('/mitra/affiliates', [MitraPageController::class, 'affiliates'])->name('mitra.affiliates');

        Route::resource('/mitra/products', StoreProductController::class)
            ->except(['show'])
            ->names('mitra.products');
        Route::post('/mitra/products/{product}/toggle-active', [StoreProductController::class, 'toggleActive'])
            ->name('mitra.products.toggleActive');
        Route::post('/mitra/products/{product}/activate-listing', [StoreProductController::class, 'activateListing'])
            ->name('mitra.products.activateListing');
        Route::post('/mitra/products/{product}/marketplace-settings', [StoreProductController::class, 'updateMarketplaceSettings'])
            ->name('mitra.products.marketplaceSettings');
        Route::get('/mitra/products/{product}/stock-history', [StoreProductController::class, 'stockHistory'])
            ->name('mitra.products.stockHistory');
        Route::post('/mitra/products/{product}/stock-adjust', [StoreProductController::class, 'adjustStock'])
            ->name('mitra.products.adjustStock');

        Route::post('/mitra/procurement/orders', [MitraProcurementController::class, 'createOrder'])
            ->name('mitra.procurement.createOrder');
        Route::get('/mitra/procurement/orders/{orderId}', [MitraProcurementController::class, 'show'])
            ->name('mitra.procurement.show');
        Route::post('/mitra/procurement/orders/{orderId}/cancel', [MitraProcurementController::class, 'cancelOrder'])
            ->name('mitra.procurement.cancel');
        Route::post('/mitra/procurement/orders/{orderId}/submit-payment', [MitraProcurementController::class, 'submitPayment'])
            ->name('mitra.procurement.submitPayment');
        Route::post('/mitra/procurement/orders/{orderId}/confirm-received', [MitraProcurementController::class, 'confirmReceived'])
            ->name('mitra.procurement.confirmReceived');
        Route::post('/mitra/orders/{orderId}/mark-packed', [MitraOrderController::class, 'markPacked'])
            ->name('mitra.orders.markPacked');
        Route::post('/mitra/orders/{orderId}/mark-paid', [MitraOrderController::class, 'markPaid'])
            ->name('mitra.orders.markPaid');
        Route::post('/mitra/orders/{orderId}/mark-shipped', [MitraOrderController::class, 'markShipped'])
            ->name('mitra.orders.markShipped');
    });

    Route::middleware(['role:consumer'])->group(function () {
        Route::post('/profile/request-affiliate', [ProfileModeController::class, 'requestAffiliate'])
            ->name('profile.requestAffiliate');

        Route::post('/profile/request-farmer-seller', [ProfileModeController::class, 'requestFarmerSeller'])
            ->name('profile.requestFarmerSeller');

        Route::get('/program/mitra-b2b/banner-access', [MitraApplicationController::class, 'entryFromBanner'])
            ->middleware('signed')
            ->name('program.mitra.entry');

        Route::get('/program/mitra-b2b', [MitraApplicationController::class, 'form'])
            ->name('program.mitra.form');

        Route::post('/program/mitra-b2b', [MitraApplicationController::class, 'storeOrSubmit'])
            ->name('program.mitra.storeOrSubmit');
    });

    Route::get('/profile/location', [AppPageController::class, 'locationForm'])->name('profile.location');
    Route::post('/profile/location', [AppPageController::class, 'saveLocation'])->name('profile.location.save');

    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::post('/mode/{userId}/approve', [AdminModeApprovalController::class, 'approve'])
            ->name('admin.mode.approve');

        Route::post('/mode/{userId}/reject', [AdminModeApprovalController::class, 'reject'])
            ->name('admin.mode.reject');

        Route::post('/users/{userId}/suspend', [UsersPageController::class, 'suspend'])
            ->whereNumber('userId')
            ->name('admin.modules.users.suspend');
        Route::post('/users/{userId}/block', [UsersPageController::class, 'block'])
            ->whereNumber('userId')
            ->name('admin.modules.users.block');
        Route::post('/users/{userId}/activate', [UsersPageController::class, 'activate'])
            ->whereNumber('userId')
            ->name('admin.modules.users.activate');

        Route::post('/withdraws/{withdrawId}/approve', [AdminWithdrawController::class, 'approve'])
            ->whereNumber('withdrawId')
            ->name('admin.withdraws.approve');

        Route::post('/withdraws/{withdrawId}/paid', [AdminWithdrawController::class, 'markPaid'])
            ->whereNumber('withdrawId')
            ->name('admin.withdraws.paid');

        Route::post('/withdraws/{withdrawId}/reject', [AdminWithdrawController::class, 'reject'])
            ->whereNumber('withdrawId')
            ->name('admin.withdraws.reject');

        Route::post('/reports/{reportId}/review', [AdminDisputeController::class, 'review'])
            ->whereNumber('reportId')
            ->name('admin.modules.reports.review');
        Route::post('/reports/products/{productReportId}/review', [ReportsPageController::class, 'reviewProductReport'])
            ->whereNumber('productReportId')
            ->name('admin.modules.reports.products.review');

        Route::post('/refunds/{refundId}/paid', [AdminDisputeController::class, 'markRefundPaid'])
            ->whereNumber('refundId')
            ->name('admin.modules.refunds.paid');

        Route::post('/finance/affiliate-commission-range', [FinancePageController::class, 'updateAffiliateCommissionRange'])
            ->name('admin.modules.finance.affiliateCommissionRange.update');

        Route::post('/admin-products', [AdminProductController::class, 'store'])
            ->name('admin.adminProducts.store');
        Route::patch('/admin-products/{adminProductId}', [AdminProductController::class, 'update'])
            ->whereNumber('adminProductId')
            ->name('admin.adminProducts.update');
        Route::delete('/admin-products/{adminProductId}', [AdminProductController::class, 'destroy'])
            ->whereNumber('adminProductId')
            ->name('admin.adminProducts.destroy');

        Route::post('/procurement/orders/{adminOrderId}/status', [AdminProcurementController::class, 'setOrderStatus'])
            ->name('admin.procurement.orders.status');
        Route::post('/procurement/orders/{adminOrderId}/payment-status', [AdminProcurementController::class, 'setPaymentStatus'])
            ->name('admin.procurement.orders.paymentStatus');
        Route::get('/procurement/snapshot', [AdminProcurementController::class, 'snapshot'])
            ->name('admin.procurement.snapshot');
        Route::get('/procurement/orders/{adminOrderId}', [AdminProcurementController::class, 'show'])
            ->name('admin.procurement.orders.show');

        Route::post('/settings/mitra-submission', [SettingsPageController::class, 'updateMitraSubmission'])
            ->name('admin.settings.mitraSubmission.update');
        Route::post('/settings/role-automation', [SettingsPageController::class, 'updateRoleAutomation'])
            ->name('admin.settings.automation.update');
        Route::post('/settings/announcements', [SettingsPageController::class, 'storeAnnouncement'])
            ->name('admin.settings.announcements.store');
        Route::patch('/settings/announcements/{announcementId}', [SettingsPageController::class, 'updateAnnouncement'])
            ->whereNumber('announcementId')
            ->name('admin.settings.announcements.update');
        Route::delete('/settings/announcements/{announcementId}', [SettingsPageController::class, 'destroyAnnouncement'])
            ->whereNumber('announcementId')
            ->name('admin.settings.announcements.destroy');

        Route::post('/mitra-applications/{applicationId}/review', [MitraApplicationReviewController::class, 'review'])
            ->whereNumber('applicationId')
            ->name('admin.mitraApplications.review');

        Route::post('/weather/notices', [WeatherPageController::class, 'storeNotice'])
            ->name('admin.modules.weather.notices.store');
        Route::patch('/weather/notices/{noticeId}', [WeatherPageController::class, 'updateNotice'])
            ->whereNumber('noticeId')
            ->name('admin.modules.weather.notices.update');
        Route::post('/weather/notices/{noticeId}/toggle', [WeatherPageController::class, 'toggleNotice'])
            ->whereNumber('noticeId')
            ->name('admin.modules.weather.notices.toggle');
        Route::delete('/weather/notices/{noticeId}', [WeatherPageController::class, 'destroyNotice'])
            ->whereNumber('noticeId')
            ->name('admin.modules.weather.notices.destroy');
        Route::post('/weather/notices/actions/deactivate-expired', [WeatherPageController::class, 'deactivateExpiredNotices'])
            ->name('admin.modules.weather.notices.deactivateExpired');
        Route::delete('/weather/notices/actions/prune-inactive', [WeatherPageController::class, 'pruneInactiveNotices'])
            ->name('admin.modules.weather.notices.pruneInactive');

        Route::post('/marketplace/affiliate-lock-policy', [MarketplacePageController::class, 'updateAffiliateLockPolicy'])
            ->name('admin.modules.marketplace.affiliateLockPolicy.update');
        Route::post('/marketplace/affiliate-commission-range', [MarketplacePageController::class, 'updateAffiliateCommissionRange'])
            ->name('admin.modules.marketplace.affiliateCommissionRange.update');

        // CATATAN-AUDIT: Endpoint mutasi rule rekomendasi + trigger sync manual.
        Route::patch('/recommendation-rules/{ruleId}', [RecommendationRulePageController::class, 'update'])
            ->whereNumber('ruleId')
            ->name('admin.modules.recommendationRules.update');
        Route::post('/recommendation-rules/sync-now', [RecommendationRulePageController::class, 'syncNow'])
            ->name('admin.modules.recommendationRules.syncNow');
    });

    Route::middleware(['role:consumer', 'block.mitra.marketplace'])->group(function () {
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
        Route::patch('/cart/{cartItemId}', [CartController::class, 'update'])
            ->whereNumber('cartItemId')
            ->name('cart.update');
        Route::delete('/cart/{cartItemId}', [CartController::class, 'destroy'])
            ->whereNumber('cartItemId')
            ->name('cart.destroy');
        Route::post('/produk/{productType}/{productId}/laporkan', [MarketplaceProductController::class, 'report'])
            ->whereIn('productType', ['store', 'farmer', 'mitra', 'seller', 'petani'])
            ->whereNumber('productId')
            ->name('marketplace.product.report');
        Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('checkout');
        Route::get('/orders/mine', [BuyerOrderController::class, 'index'])->name('orders.mine');
        Route::post('/orders/{orderId}/transfer-proof', [BuyerOrderController::class, 'submitTransferProof'])
            ->name('orders.transfer-proof');
        Route::post('/orders/{orderId}/confirm-received', [BuyerOrderController::class, 'confirmReceived'])
            ->name('orders.confirm-received');
        Route::post('/orders/{orderId}/disputes', [DisputeController::class, 'store'])
            ->name('orders.disputes.store');
        Route::post('/orders/{orderId}/rating', [BuyerOrderController::class, 'storeRating'])
            ->name('orders.rating.store');
    });

    Route::post('/wallet/withdraw', [WithdrawController::class, 'requestWithdraw'])
        ->name('wallet.withdraw.request');
    Route::post('/wallet/demo-topup', [DemoWalletTopupController::class, 'store'])
        ->name('wallet.demo-topup');
});
