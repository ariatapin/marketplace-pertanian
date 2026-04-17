<?php

namespace App\Http\Controllers;

use App\Services\ConsumerModeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileModeController extends Controller
{
    public function __construct(protected ConsumerModeService $service) {}

    public function requestAffiliate(Request $request)
    {
        try {
            $result = $this->service->requestAffiliate($request->user());
            return back()->with('status', "Request affiliate: {$result['status']}");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Gagal mengajukan mode affiliate.';
            return back()->withErrors($e->errors())->with('error', $message);
        }
    }

    public function requestFarmerSeller(Request $request)
    {
        try {
            $result = $this->service->requestFarmerSeller($request->user());
            return back()->with('status', "Request penjual: {$result['status']}");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Gagal mengajukan mode penjual P2P.';
            return back()->withErrors($e->errors())->with('error', $message);
        }
    }
}
