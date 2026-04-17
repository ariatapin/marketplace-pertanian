<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AffiliateReferralService
{
    private const SESSION_KEY_ID = 'marketplace.affiliate_referral_id';
    private const SESSION_KEY_CODE = 'marketplace.affiliate_referral_code';

    public function __construct(
        protected RoleAccessService $roleAccess
    ) {}

    /**
     * Menangkap parameter referral dari query string dan menyimpannya ke session.
     */
    public function captureFromRequest(Request $request): void
    {
        if ($request->boolean('clear_ref')) {
            $this->forget($request);
            return;
        }

        $rawCode = trim((string) $request->query('ref', ''));
        if ($rawCode === '') {
            return;
        }

        $affiliateId = $this->decodeReferralCode($rawCode);
        if (($affiliateId ?? 0) <= 0 || ! $this->isEligibleAffiliate($affiliateId)) {
            return;
        }

        $request->session()->put(self::SESSION_KEY_ID, $affiliateId);
        $request->session()->put(self::SESSION_KEY_CODE, $this->encodeReferralCode($affiliateId));
    }

    /**
     * Mengambil referral aktif dari session untuk ditampilkan di UI.
     *
     * @return array{id:int,name:string,email:string,code:string,url:string}|null
     */
    public function currentReferral(Request $request, ?User $buyer = null): ?array
    {
        $affiliateId = (int) $request->session()->get(self::SESSION_KEY_ID, 0);
        if ($affiliateId <= 0) {
            return null;
        }

        $affiliate = User::query()->find($affiliateId, ['id', 'name', 'email', 'role']);
        if (! $affiliate || ! $this->roleAccess->canAccessAffiliate($affiliate)) {
            $this->forget($request);
            return null;
        }

        if ($buyer && (int) $buyer->id === (int) $affiliate->id) {
            return null;
        }

        $displayName = trim((string) ($affiliate->name ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($affiliate->email ?? 'Affiliate'));
        }

        $code = trim((string) $request->session()->get(self::SESSION_KEY_CODE, ''));
        if ($code === '') {
            $code = $this->encodeReferralCode((int) $affiliate->id);
            $request->session()->put(self::SESSION_KEY_CODE, $code);
        }

        return [
            'id' => (int) $affiliate->id,
            'name' => $displayName,
            'email' => (string) ($affiliate->email ?? ''),
            'code' => $code,
            'url' => route('landing', ['ref' => $code]),
        ];
    }

    /**
     * Referral yang akan ditulis ke cart item store setelah validasi buyer.
     */
    public function referralIdForStoreProduct(Request $request, User $buyer, object $storeProduct): ?int
    {
        if (! (bool) ($storeProduct->is_affiliate_enabled ?? false)) {
            return null;
        }

        $expiry = $storeProduct->affiliate_expire_date ?? null;
        if (! empty($expiry)) {
            try {
                $expiresAt = Carbon::parse((string) $expiry)->endOfDay();
            } catch (\Throwable) {
                return null;
            }

            if ($expiresAt->isPast()) {
                return null;
            }
        }

        $referral = $this->currentReferral($request, $buyer);
        return $referral ? (int) $referral['id'] : null;
    }

    /**
     * URL referral pribadi untuk user affiliate aktif.
     */
    public function buildLandingUrlForUser(User $user): ?string
    {
        if (! $this->roleAccess->canAccessAffiliate($user)) {
            return null;
        }

        return route('landing', ['ref' => $this->encodeReferralCode((int) $user->id)]);
    }

    /**
     * Menghasilkan kode referral bertanda tangan (anti manipulasi id sederhana).
     */
    public function encodeReferralCode(int $affiliateId): string
    {
        $id = max(1, (int) $affiliateId);
        $signature = substr(hash_hmac('sha256', (string) $id, $this->signingKey()), 0, 24);

        return $id . '.' . strtolower($signature);
    }

    /**
     * Menghapus referral aktif dari session.
     */
    public function forget(Request $request): void
    {
        $request->session()->forget([self::SESSION_KEY_ID, self::SESSION_KEY_CODE]);
    }

    private function decodeReferralCode(string $code): ?int
    {
        $normalized = strtolower(trim($code));
        if (! preg_match('/^(\d+)\.([a-f0-9]{24})$/', $normalized, $matches)) {
            return null;
        }

        $affiliateId = (int) ($matches[1] ?? 0);
        $providedSignature = (string) ($matches[2] ?? '');
        if ($affiliateId <= 0 || $providedSignature === '') {
            return null;
        }

        $expectedSignature = substr(hash_hmac('sha256', (string) $affiliateId, $this->signingKey()), 0, 24);
        if (! hash_equals(strtolower($expectedSignature), $providedSignature)) {
            return null;
        }

        return $affiliateId;
    }

    private function isEligibleAffiliate(int $userId): bool
    {
        $affiliate = User::query()->find($userId, ['id', 'role', 'name', 'email']);
        if (! $affiliate) {
            return false;
        }

        return $this->roleAccess->canAccessAffiliate($affiliate);
    }

    private function signingKey(): string
    {
        return (string) config('app.key', 'marketplace-affiliate-fallback-key');
    }
}

