<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ConsumerModeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(protected ConsumerModeService $service) {}

    public function pending()
    {
        $rows = DB::table('consumer_profiles')
            ->join('users', 'users.id', '=', 'consumer_profiles.user_id')
            ->where('consumer_profiles.mode_status', 'pending')
            ->select('users.id','users.name','users.email','consumer_profiles.requested_mode','consumer_profiles.updated_at')
            ->orderByDesc('consumer_profiles.updated_at')
            ->get();

        return $this->apiSuccess($rows, 'Daftar pengajuan pending berhasil diambil.');
    }

    public function approve(Request $request, int $userId)
    {
        $data = $request->validate([
            'mode' => 'required|in:affiliate,farmer_seller',
        ]);

        $this->service->approveMode($request->user(), $userId, $data['mode']);
        return $this->apiSuccess([
            'status' => 'approved',
            'mode' => $data['mode'],
        ], 'Pengajuan berhasil disetujui.');
    }

    public function reject(Request $request, int $userId)
    {
        $this->service->rejectMode($request->user(), $userId);
        return $this->apiSuccess([
            'status' => 'rejected',
        ], 'Pengajuan berhasil ditolak.');
    }
}
