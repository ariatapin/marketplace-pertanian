<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AdminWithdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWithdrawApiController extends Controller
{
    use ApiResponse;

    public function pending()
    {
        $rows = DB::table('withdraw_requests')
            ->join('users','users.id','=','withdraw_requests.user_id')
            ->where('withdraw_requests.status','pending')
            ->select('withdraw_requests.*','users.name','users.email')
            ->orderBy('withdraw_requests.created_at')
            ->get();

        return $this->apiSuccess($rows, 'Daftar withdraw pending berhasil diambil.');
    }

    public function approve(Request $request, int $withdrawId)
    {
        return app(AdminWithdrawController::class)->approve($request, $withdrawId);
    }

    public function paid(Request $request, int $withdrawId)
    {
        return app(AdminWithdrawController::class)->markPaid($request, $withdrawId);
    }

    public function reject(Request $request, int $withdrawId)
    {
        return app(AdminWithdrawController::class)->reject($request, $withdrawId);
    }
}
