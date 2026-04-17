<?php

namespace App\Http\Controllers;

use App\Services\ConsumerModeService;
use Illuminate\Http\Request;

class AdminModeApprovalController extends Controller
{
    public function __construct(protected ConsumerModeService $service) {}

    public function approve(Request $request, int $userId)
    {
        $request->validate([
            'mode' => 'required|string|in:affiliate,farmer_seller',
        ]);

        $this->service->approveMode($request->user(), $userId, $request->mode);
        return back()->with('status', "Approved user {$userId} => {$request->mode}");
    }

    public function reject(Request $request, int $userId)
    {
        $this->service->rejectMode($request->user(), $userId);
        return back()->with('status', "Rejected user {$userId}");
    }
}
