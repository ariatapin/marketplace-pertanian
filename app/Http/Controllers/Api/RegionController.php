<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\District;
use App\Models\Province;

class RegionController extends Controller
{
    use ApiResponse;

    public function provinces()
    {
        $rows = Province::select('id', 'name')->orderBy('name')->get();
        return $this->apiSuccess($rows, 'Data provinsi berhasil diambil.');
    }

    public function cities(int $province)
    {
        $rows = City::select('id', 'name', 'type', 'lat', 'lng')
            ->where('province_id', $province)
            ->orderBy('name')
            ->get();
        return $this->apiSuccess($rows, 'Data kota berhasil diambil.');
    }

    public function districts(int $city)
    {
        $rows = District::select('id', 'name', 'lat', 'lng')
            ->where('city_id', $city)
            ->orderBy('name')
            ->get();
        return $this->apiSuccess($rows, 'Data kecamatan berhasil diambil.');
    }
}
