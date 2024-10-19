<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Kernel;
use App\Models\HijriStandard;
use App\Traits\AppResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HijriStandardController extends Controller
{
    use AppResponse;

    public function all(Request $request)
    {
        $hijriStandard = Cache::remember('hijriStandard', Kernel::CACHE_TIME, function () {
            return HijriStandard::all();
        });
        return $this->success($hijriStandard);
    }
}
