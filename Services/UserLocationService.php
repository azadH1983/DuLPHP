<?php

namespace App\Http\Services;


use App\Helpers\CacheHelper;
use App\Libraries\Helpers\LocationHelpers;
use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\MultiPolygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use stdClass;
use Throwable;

class UserLocationService
{
    const IP_KEY = 'ace697c239ee96';
    const keys = ["g_dxb_1", "g_dxb_2", "g_dxb_3"];
    const GEO_KEY = 'iacad';
    private GeoNamesClient $geoNamesClient;

    public function __construct()
    {
        $this->getGeoNamesClient(false);
    }

    function getGeoNamesClient($backup): void
    {
        if ($backup) {
            $this->geoNamesClient = new GeoNamesClient(self::GEO_KEY);
        } else {
            $this->geoNamesClient = new GeoNamesClient(self::keys[array_rand(self::keys)]);
        }
    }

    public function userLocationByIP(): UserLocation
    {
        $userLocation = new UserLocation();
        $ipLoc = $this->getLocationByIP();
        if ($ipLoc != null) {
            $userLocation->fromIp($ipLoc);
        }
        return $userLocation;
    }


    public function userLocation($lat, $lng, $isV2): UserLocation
    {
        $roundLat = round($lat, 3);
        $roundLng = round($lng, 3);
        $UserLocationKey = 'userLocation_' . $roundLat . '_' . $roundLng . '_' . app()->getLocale();
        $userLocation = CacheHelper::get($UserLocationKey);
        if ($userLocation) {
            $userLocation->source = 'redis_' . $UserLocationKey;
            if ($isV2) {
                if ($userLocation->area == null) {
                    $userLocation->getAreaName();
                }
            }
            $shouldUpdate = empty($userLocation->country_iso);
            $userLocation = $this->handleNoCountry($userLocation);
            if (!isset($userLocation->altitude)) {
                $altitude = $this->getAltitude($lat, $lng);
                if (isset($altitude->results)) {
                    $userLocation->altitude = $altitude->results[0]['elevation'];
                }
            }
            //check if the user city is near him

            if (isset($userLocation->city)) {
                $distance = LocationHelpers::distance($lat, $lng, $userLocation->city->latitude, $userLocation->city->longitude, 'K');
                if ($distance > 100) {
                    $userLocation->city = null;
                }
            }
            if ($shouldUpdate) {
                CacheHelper::set($UserLocationKey, $userLocation);
            }
            return $userLocation;
        }
        $userLocation = new UserLocation();
        $userLocation->lat = $lat;
        $userLocation->lng = $lng;

        try {
            $userLocation->country_iso = $this->countryCode($lat, $lng)->countryCode;
        } catch (Throwable $e) {
            Log::error($e->getMessage() . "$lat $lng");
            if ($e->getCode() == 15) {//no country found
                $userLocation->country_iso = $this->getOpenMapData($lat, $lng)?->countryCode;
            } else {
                $this->getGeoNamesClient(true);
            }
        }
        try {
            if (!isset($userLocation->country_iso)) {
                $userLocation->country_iso = $this->countryCode($lat, $lng)->countryCode;
            }
            $country = Country::query()->iso($userLocation->country_iso)->first();
            $userLocation->country = $country;
            $place = null;
            if ($country->has_geo_zones) {
                $place = Place::query()
                    ->whereContains('polygon', new Point($lat, $lng))
                    ->first();
            }

            if ($place != null) {
                $userLocation->fromPlace($place, $country);
            } else {
                $geoData = $this->getGeoNamesLoc($userLocation);
                if ($geoData != null) {
                    $userLocation->fromGeoNames($geoData);
                } else {
                    $data = $this->getOpenCageData($lat, $lng);
                    $userLocation->fromOpenCageData($data);
                }
            }
        } catch (Throwable $e) {
            Log::info('getUserLocation ' . $e->getMessage());
            $data = $this->getOpenCageData($lat, $lng);
            $userLocation->fromOpenCageData($data);
            try {
                if (!isset($userLocation->city_name)) {
                    $ipLoc = $this->getLocationByIP();
                    if ($ipLoc != null) {
                        $userLocation->fromIp($ipLoc);
                    }
                }
            } catch (\Exception $e) {
                Log::info('getUserLocation ' . $e->getMessage());
                return UserLocation::default();
            }
        }
        if ($isV2) {
            if ($userLocation->area == null) {
                try {
                    $userLocation->getAreaName();
                } catch (Throwable $e) {
                    Log::error('getUserLocation ' . $e->getMessage());
                }
            }
        }

        $userLocation = $this->handleNoCountry($userLocation);
        if (!isset($userLocation->altitude)) {
            $altitude = $this->getAltitude($lat, $lng);
            if (isset($altitude->results)) {
                $userLocation->altitude = $altitude->results[0]['elevation'];
            }
        }
        if (!empty($userLocation->country_iso)) {
            CacheHelper::set($UserLocationKey, $userLocation);
        }
        return $userLocation;
    }

    private function handleNoCountry(?UserLocation $userLocation): ?object
    {
        if (empty($userLocation->country_iso)) {
            $cities = City::nearest($userLocation->lat, $userLocation->lng)->get();
            if ($cities->isEmpty()) {
                return $userLocation;
            }
            $nearestCity = $cities->first();
            $userLocation->fromCity($nearestCity);
        }
        return $userLocation;
    }

    private function getOpenMapData($lat, $lng): ?object
    {
        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . $lat . '&lon=' . $lng . '&accept-language=' . app()->getLocale();
        $response = Http::get($url);
        if ($response->failed()) {
            return null;
        }
        $data = $response->object();
        if (!isset($data->address)) {
            return null;
        }
        if (!isset($data->address->country_code)) {
            return null;
        }
        return (object)['countryCode' => $data->address->country_code, 'source' => 'openstreetmap'];
    }

    private function getOpenCageData($lat, $lng): ?object
    {
        //TODO KEY
        $key = '65943a9a035b4bbcb9f82387d0b05058';
        $url = 'https://api.opencagedata.com/geocode/v1/json?key=' . $key . '&pretty=1&q=' . $lat . ',' . $lng . '&language=' . app()->getLocale();

        $response = Http::get($url);
        if ($response->failed()) {
            return null;
        }
        return $response->object();
    }

    private function getGeoNamesLoc(&$userLocation)
    {
        $lat = $userLocation->lat;
        $lng = $userLocation->lng;
        $geoData = null;
        $adminArea = $this->adminArea($lat, $lng);

        if (isset($adminArea->adminName1)) {
            $userLocation->city_name = $adminArea->adminName1;
        }
        $capitalArea = $this->capitalArea($lat, $lng);

        $distance = 0;
        if (isset($capitalArea[0]?->distance)) {
            $distance = $capitalArea[0]?->distance ?? 0;
        }
        if (empty($capitalArea) || $distance > 15) {
            $mainArea = $this->mainArea($lat, $lng);
            $inAdminArea = $this->checkInAdmin($mainArea, $adminArea, $userLocation->country_iso);
            if ($inAdminArea != null) {
                $geoData = $inAdminArea;
            } else {
                $mediumArea = $this->mediumArea($lat, $lng);
                $inAdminArea = $this->checkInAdmin($mediumArea, $adminArea, $userLocation->country_iso);
                if ($inAdminArea != null) {
                    $geoData = $inAdminArea;
                } else {
                    $smallArea = $this->smallArea($lat, $lng);
                    $inAdminArea = $this->checkInAdmin($smallArea, $adminArea, $userLocation->country_iso);
                    if ($inAdminArea != null) {
                        $geoData = $inAdminArea;
                    } else {
                        $lastArea = $this->lastArea($lat, $lng);
                        if (!empty($lastArea)) {
                            $geoData = $lastArea[0];
                        } else {
                            Log::info('geoData is null' . $lat . ' ' . $lng . ' ');
                        }
                    }
                }
            }

        } else {
            $geoData = $capitalArea[0];
        }

        return $geoData;
    }

    private function getLocationByIP(): ?array
    {
        $ip = request()->ip();
        if ($ip == '192.168.48.1') { //test local host
            $ip = "176.29.221.54";
        }

        $response = Http::get('https://ipinfo.io/' . $ip . '?token=' . self::IP_KEY);
        if ($response->failed()) {
            return null;
        }
        return $response->json();
    }

    function countryCode($lat, $lng): stdClass
    {
        $country = Country::query()
            ->whereContains('polygon', new Point($lat, $lng))
            ->first();
        if ($country) {
            return (object)['countryCode' => strtoupper($country->iso), 'source' => 'polygon'];
        }
//        $country = $this->getCountryFromGeo($lat, $lng);
//        if ($country) {
//            return (object)['countryCode' => $country->iso, 'source' => 'geojson'];
//        }
        $country = $this->getOpenMapData($lat, $lng)?->countryCode;
        if ($country) {
            return (object)['countryCode' => strtoupper($country), 'source' => 'openstreetmap'];
        }
        return $this->geoNamesClient->countryCode([
            'lat' => $lat,
            'lng' => $lng,
            'lang' => app()->getLocale()
        ]);
    }

    function updateCountry()
    {
        $json = File::get(storage_path("countries.geo.json"));
        $countries = collect(json_decode($json));
        foreach ($countries as $geocountry) {
            if (isset($geocountry->iso)) {
                $country = Country::query()->iso($geocountry->iso)->first();
                if ($country->polygon == null && isset($geocountry->geo)) {
                    if ($geocountry->geo->type == 'MultiPolygon') {
                        $geometry = MultiPolygon::fromJson(json_encode($geocountry->geo));
                    } else {
                        $geometry = new MultiPolygon([Polygon::fromJson(json_encode($geocountry->geo))]);
                    }
                    $country->polygon = $geometry;
                    $country->save();
                }
            }
        }
    }

    function getCountryFromGeo($lat, $lng)
    {
        $json = File::get(storage_path("countries.geo.json"));
        $countries = collect(json_decode($json));
        $pointLocation = new PointLocation();
        foreach ($countries as $country) {
            if (isset($country->geo)) {
                if ($country->geo->type == 'MultiPolygon') {
                    for ($i = 0; $i < count($country->geo->coordinates); $i++) {
                        $in = $pointLocation->inPolygon($lat . ' ' . $lng, $country->geo->coordinates[$i][0]);
                        if ($in) {
                            return $country;
                        }
                    }
                } else {
                    $in = $pointLocation->inPolygon($lat . ' ' . $lng, $country->geo->coordinates[0]);
                    if ($in) {
                        return $country;
                    }
                }
            }
        }
        return null;
    }

    function adminArea($lat, $lng): stdClass
    {
        return $this->geoNamesClient->countrySubdivision([
            'lat' => $lat,
            'lng' => $lng,
            'level' => 5,
            'lang' => app()->getLocale()
        ]);
    }

    function capitalArea($lat, $lng): array|stdClass
    {
        return $this->geoNamesClient->findNearbyPlaceName([
            'lat' => $lat,
            'lng' => $lng,
            'featureClass' => 'P',
            'featureCode' => 'PPLC',
            'radius' => 20,
            'style' => 'FULL',
            'lang' => app()->getLocale()
        ]);
    }

    function mainArea($lat, $lng): array
    {
        return $this->geoNamesClient->findNearbyPlaceName([
            'lat' => $lat,
            'lng' => $lng,
            'featureCode' => ['PPL', 'PPLC', 'PPLA', 'PPLA2'],
            'featureClass' => ['P'],
            'cities' => 'cities15000',
            'radius' => 15,
            'style' => 'FULL',
            'lang' => app()->getLocale()
        ]);
    }

    function mediumArea($lat, $lng): array|stdClass
    {
        return $this->geoNamesClient->findNearbyPlaceName([
            'lat' => $lat,
            'lng' => $lng,
            'featureClass' => 'P',
            'featureCode' => ['PPL', 'PPLC', 'PPLA', 'PPLA2'],
            'cities' => 'cities500',
            'radius' => 20,
            'style' => 'FULL',
            'lang' => app()->getLocale()
        ]);
    }

    function smallArea($lat, $lng): array|stdClass
    {
        return $this->geoNamesClient->findNearbyPlaceName([
            'lat' => $lat,
            'lng' => $lng,
            'radius' => 100,
            'style' => 'FULL',
            'lang' => app()->getLocale()
        ]);
    }

    function lastArea($lat, $lng): array|stdClass
    {
        return $this->geoNamesClient->findNearbyPlaceName([
            'lat' => $lat,
            'lng' => $lng,
            'style' => 'FULL',
            'lang' => app()->getLocale()
        ]);
    }


    function checkInAdmin($geonames, $admin, $countryCode)
    {
        if (!isset($admin->adminName1)) return null;
        if ($admin->adminName1 == null) return null;
        $inAdminArea = null;
        if (!empty($geonames)) {
            if ($admin->countryCode === $countryCode) {
                foreach ($geonames as $geoname) {
                    if ($geoname->adminName1 === $admin->adminName1) {
                        $inAdminArea = $geoname;
                        break;
                    }
                }
            } else {
                foreach ($geonames as $geoname) {
                    if ($geoname->countryCode === $countryCode) {
                        $inAdminArea = $geoname;
                        break;
                    }
                }
            }
        }

        return $inAdminArea;
    }

    function getAltitude($lat, $lng): stdClass
    {
        $externalApisService = new ExternalApisService();
        return $externalApisService->elevation($lat . ',' . $lng);
    }
}
