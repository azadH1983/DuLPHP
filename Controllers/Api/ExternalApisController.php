<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\ExternalApisService;
use App\Traits\AppResponse;

class ExternalApisController extends Controller
{
    use AppResponse;

    protected ExternalApisService $externalApisService;

    public function __construct(ExternalApisService $externalApisService)
    {
        $this->externalApisService = $externalApisService;
    }

    function geocode()
    {
        $latlng = request('latlng');
        $source = request('source');
        return $this->externalApisService->geocode($latlng, null, $source);
    }

    function test_all_geo_services()
    {
        $latlng = request('latlng');
        return $this->externalApisService->test_all_geo_services($latlng);
    }


    function placeDetails()
    {
        $place_id = request('placeid');
        return $this->externalApisService->placeDetails($place_id);
    }

    function elevation()
    {
        $locations = request('locations');
        return $this->externalApisService->elevation($locations);
    }

    function placeAutocomplete()
    {
        $input = request('input');
        return $this->externalApisService->placeAutocomplete($input);
    }

    function weatherV2()
    {
        $latlng = request('latlng');
        $weather = $this->externalApisService->weatherV2($latlng);
        if ($weather) {
            return $this->success($weather);
        } else {
            return $this->noContent("get weather failed");
        }
    }

    function weather()
    {
        $latlng = request('latlng');
        return $this->externalApisService->weather($latlng);
    }
}
