<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\District;
use App\Models\Province;

class RegionController extends Controller
{
    public function provinces()
    {
        return Province::select('id', 'name')->orderBy('name')->get();
    }

    public function cities(int $province)
    {
        return City::select('id', 'name', 'type', 'lat', 'lng')
            ->where('province_id', $province)
            ->orderBy('name')
            ->get();
    }

    public function districts(int $city)
    {
        return District::select('id', 'name', 'lat', 'lng')
            ->where('city_id', $city)
            ->orderBy('name')
            ->get();
    }
}
