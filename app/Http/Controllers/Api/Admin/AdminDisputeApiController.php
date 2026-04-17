<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\AdminDisputeController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminDisputeApiController extends Controller
{
    public function review(Request $request, int $reportId)
    {
        return app(AdminDisputeController::class)->review($request, $reportId);
    }

    public function markRefundPaid(Request $request, int $refundId)
    {
        return app(AdminDisputeController::class)->markRefundPaid($request, $refundId);
    }
}

