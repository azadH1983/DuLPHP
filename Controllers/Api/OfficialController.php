<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Kernel;
use App\Http\Resources\OfficialResource;
use App\Models\Official;
use App\Traits\AppResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class OfficialController extends Controller
{
    use AppResponse;

    public function index(): JsonResponse
    {
        $official = Cache::remember('Official', Kernel::CACHE_TIME, function () {
            return Official::all();
        });
        return $this->success(OfficialResource::collection($official));
    }

    public function show(Official $official): JsonResponse
    {
        return $this->success(new OfficialResource($official));
    }
}
