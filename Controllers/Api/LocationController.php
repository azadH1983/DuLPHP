<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TimeZoneHelper;
use App\Http\Controllers\Controller;
use App\Http\Kernel;
use App\Http\Resources\CitiesResource;
use App\Http\Resources\CityResource;
use App\Http\Resources\CountriesResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\OfficialResource;
use App\Http\Resources\UserLocationResource;
use App\Http\Services\UserLocation;
use App\Http\Services\UserLocationService;
use App\Models\City;
use App\Models\Country;
use App\Traits\AppResponse;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class LocationController extends Controller
{
    use AppResponse;

    protected UserLocationService $userLocationService;

    public function __construct(UserLocationService $userLocationService)
    {
        $this->userLocationService = $userLocationService;
    }

    public function userLocationV2($lat, $lng): JsonResponse
    {
        $usrLocation = $this->userLocationService->userLocation($lat, $lng, true);
        if ($usrLocation->city != null) {
            $usrLocation->city = new CityResource($usrLocation->city);
        }
        if ($usrLocation->official != null) {
            $usrLocation->official = new OfficialResource($usrLocation->official);
        }

        return $this->success(new UserLocationResource($usrLocation));
    }

    public function userLocation($lat, $lng): JsonResponse
    {
        try {
            $usrLocation = $this->userLocationService->userLocation($lat, $lng, false);
        } catch (\Exception $e) {
            Log::info('userLocation ' . $e->getMessage());
            $usrLocation = UserLocation::default();
        }
        if ($usrLocation->city != null) {
            if ($usrLocation->city->time_zone == null) {
                $usrLocation->city->time_zone = $usrLocation->timezone;
            }
            $usrLocation->city = new CityResource($usrLocation->city);
        }
        if ($usrLocation->official != null) {
            $usrLocation->official = new OfficialResource($usrLocation->official);
        }

        return $this->success(new UserLocationResource($usrLocation));
    }

    public function userLocationByIP(): JsonResponse
    {

        $usrLocation = $this->userLocationService->userLocationByIP();
        if ($usrLocation->city != null) {
            if ($usrLocation->city->time_zone == null) {
                $usrLocation->city->time_zone = $usrLocation->timezone;
            }
            $usrLocation->city = new CityResource($usrLocation->city);
        }
        return $this->success($usrLocation);
    }

    public function countries(): JsonResponse
    {

        $page = request()->input('page');
        $countries = Cache::remember('countries_' . $page, Kernel::CACHE_TIME, function () {
            return Country::paginate(50);
        });
        return $this->success(new CountriesResource($countries));
    }

    public function showCountry(Country $country): JsonResponse
    {
        return $this->success(new CountryResource($country));
    }

    public function searchCountry($name): JsonResponse
    {
        if (empty($name)) {
            return $this->countries();
        }

        $page = request()->input('page');
        $countries = Cache::remember('countries_' . $page . 'search_' . $name, Kernel::CACHE_TIME, function () use ($name) {

            return Country::query()->search($name)->paginate(50);
        });
        return $this->success(new CountriesResource($countries));
    }

    public function cities(): JsonResponse
    {
        $page = request()->input('page');
        $cities = Cache::remember('cities_' . $page, Kernel::CACHE_TIME, function () {
            return City::paginate(50);
        });
        return $this->success(new CitiesResource($cities));
    }

    public function showCity(City $city): JsonResponse
    {
        return $this->success(new CityResource($city));
    }

    public function updateCity(City $city): JsonResponse
    {
        $input = request()->validate([
            'en_name' => 'string',
            'ar_name' => 'string',
            'searchable_string' => 'string',
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'altitude' => 'numeric',
            'time_zone' => 'string',
            'official_id' => 'nullable|numeric',
            'city_diameter' => 'numeric',
            'is_auto' => 'boolean',
            'population' => 'numeric',
            'geonameid' => 'numeric',
        ]);
        $en_name = $input['en_name'];
        $ar_name = $input['ar_name'];
        unset($input['en_name']);
        unset($input['ar_name']);
        $city->translate('en')->name = $en_name;
        $city->translate('ar')->name = $ar_name;
        $city->update($input);
        return $this->success($city);
    }

    public function updateCityWithJson(City $city): JsonResponse
    {
        $input = request()->validate([
            'en_name' => 'string',
            'ar_name' => 'string',
            'searchable_string' => 'string',
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'altitude' => 'numeric',
            'time_zone' => 'string',
            'official_id' => 'nullable|numeric',
            'city_diameter' => 'numeric',
            'is_auto' => 'boolean',
            'population' => 'numeric',
            'geonameid' => 'numeric',
        ]);
        $filePath = Storage::disk('databases')->path("data/cities.json");
        $json = File::get($filePath);
        $cities = collect(json_decode($json));
        $Jsoncity = $cities->where('id', $city->id)->first();
        $en_name = $input['en_name'];
        $ar_name = $input['ar_name'];
        unset($input['en_name']);
        unset($input['ar_name']);
        $city->translate('en')->name = $en_name;
        $city->translate('ar')->name = $ar_name;
        $city->update($input);
        $newCity = City::query()->where('id', $city->id)->first();

        $Jsoncity->en_name = $en_name ?? $newCity->getTranslation('en')->name;
        $Jsoncity->ar_name = $ar_name ?? $newCity->getTranslation('ar')->name;
        $Jsoncity->searchable_string = $input['searchable_string'] ?? $newCity->searchable_string;
        $Jsoncity->latitude = $input['latitude'] ?? $newCity->latitude;
        $Jsoncity->longitude = $input['longitude'] ?? $newCity->longitude;
        $Jsoncity->altitude = $input['altitude'] ?? $newCity->altitude;
        $Jsoncity->TimeZone = $input['time_zone'] ?? $newCity->time_zone;
        $Jsoncity->official_id = $input['official_id'] ?? $newCity->official_id;
        $Jsoncity->City_Diameter = $input['city_diameter'] ?? $newCity->city_diameter;
        $Jsoncity->isAuto = $input['is_auto'] ?? $newCity->is_auto;
        $Jsoncity->population = $input['population'] ?? $newCity->population;
        $Jsoncity->geonameid = $input['geonameid'] ?? $newCity->geonameid;
        //update the json file
//        File::put($filePath, json_encode($cities, JSON_UNESCAPED_UNICODE));
        File::put($filePath, $cities->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->success(new CityResource($city));
    }

    public function searchCityByCountry(Country $country, $query = ""): JsonResponse
    {
        $country_id = $country->id;
        $page = request()->input('page');
        $cities = Cache::remember('cities_' . $page . 'search_' . $query . "_country" . $country_id, Kernel::CACHE_TIME, function () use ($query, $country_id) {
            return City::query()->search($query)
                ->where('country_id', $country_id)->paginate(50);
        });

        if ($cities->isEmpty()) {
            return $this->notFound();
        }
        return $this->success(new CitiesResource($cities));
    }

    public function searchCity($name): JsonResponse
    {
        $page = request()->input('page');
        $cities = Cache::remember('cities_' . $page . 'search_' . $name, Kernel::CACHE_TIME, function () use ($name) {
            return City::query()->search($name)->paginate(50);
        });
        return $this->success(new CitiesResource($cities));
    }

    function isArabic($str)
    {
        // Check if the string contains Arabic characters
        return preg_match('/\p{Arabic}/u', $str);
    }

    public function checkCityName(Country $country): JsonResponse
    {
        $data = $country->cities;
        $cities = collect();
        $filePath = Storage::disk('databases')->path("data/cities.json");
        $json = File::get($filePath);
        $Jsoncities = collect(json_decode($json));
        $count = 0;
        foreach ($data as $city) {
            $ar_name = $city->translations->where('locale', 'ar')->first()->name;
            $en_name = $city->translations->where('locale', 'en')->first()->name;
            $city->ar_name = $ar_name;
            $city->en_name = $en_name;
            if (!$this->isArabic($ar_name)) {
                $cities->push($city);
                $count++;
            }
        }
        $output = $data->map(function ($city) use ($Jsoncities, $cities) {
            $Jsoncity = $Jsoncities->where('id', $city->id)->first();
            $fallback = $city->ar_name;
            $Jsoncity->ar_name = $this->getCityNameByLatitudeLongitude($city->latitude, $city->longitude, $fallback);

            return $Jsoncity;
        });
        File::put($filePath, $Jsoncities->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $this->success([
            'count' => $count,
            'total' => $data->count(),
            'cities' => $output,
        ]);
    }

    function getCityNameByLatitudeLongitude($lat, $long, $fallback)
    {

        $url = "https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=" . $lat . "&longitude=" . $long . "&localityLanguage=ar";
        $response = file_get_contents($url);
        $data = json_decode($response);
        if ($data->city != null) {
            return $data->city;
        }
        return $fallback;
    }

    private function mapCityToJson($city)
    {

        return [
            'id' => $city->id,
            'country_id' => $city->country_id,
            'en_name' => $city->en_name,
            'ar_name' => $city->ar_name,
            'serchable_string' => $city->searchable_string,
            'latitude' => $city->latitude,
            'longitude' => $city->longitude,
            'altitude' => $city->altitude,
            'TimeZone' => $city->time_zone,
            'official_id' => $city->official_id,
            'City_Diameter' => $city->city_diameter,
            'isAuto' => $city->is_auto,
            'population' => $city->population,
            'geonameid' => $city->geonameid,
        ];

    }

    public function checkTimeZone(Country $country): JsonResponse
    {
        $filePath = Storage::disk('databases')->path("data/cities.json");
        $json = File::get($filePath);
        $Jsoncities = collect(json_decode($json));
        $output = $country->cities->map(function ($city) use ($Jsoncities) {
            $Jsoncity = $Jsoncities->where('id', $city->id)->first();
            $newTimeZone = TimeZoneHelper::get_nearest_timezone($city->latitude, $city->longitude, $city->country->iso);
            $Jsoncity->TimeZone = $newTimeZone;
            return $Jsoncity;
        });
        File::put($filePath, $Jsoncities->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $this->success(
            $output);
    }


}
