<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait DetectsJsonRequest
{
    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }
}
