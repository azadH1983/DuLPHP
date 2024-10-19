<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Kernel;
use App\Http\Resources\CalculationMethodResource;
use App\Models\CalculationMethod;
use App\Models\HighLatitude;
use App\Traits\AppResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class CalculationMethodsController extends Controller
{
    use AppResponse;

    public function index(): JsonResponse
    {
        $methods = Cache::remember('CalculationMethod', Kernel::CACHE_TIME, function () {
            return CalculationMethod::orderBy('view_order')->get();
        });
        return $this->success(CalculationMethodResource::collection($methods));
    }

    public function show(CalculationMethod $method): JsonResponse
    {

        return $this->success(new CalculationMethodResource($method));
    }

    public function getHighLatMethods(): JsonResponse
    {
        $hMethods = Cache::remember('HighLatitude_methods_' . App::getLocale(), Kernel::CACHE_TIME, function () {
            $all = HighLatitude::all();
            $sorted = $all;
            $isAr = App::getLocale() == "ar";
            foreach ($sorted as $method) {
                if ($method->country_code == null) {
                    $method->sortName = "0";
                } else if ($method->country_code == "NL") {
                    if ($isAr) {
                        $method->sortName = "هولندا";
                    } else {
                        $method->sortName = "Netherlands";
                    }

                } else if ($method->country_code == "DE") {
                    if ($isAr) {
                        $method->sortName = "ألمانيا";
                    } else {
                        $method->sortName = "Germany";
                    }
                } else {
                    if ($isAr) {
                        $method->sortName = str_replace("ال", "", $method->name);
                    } else {
                        $method->sortName = $method->name;
                    }
                }
            }
            return $sorted->sortBy('sortName')->flatten();
        });
        return $this->success($hMethods);
    }
}
