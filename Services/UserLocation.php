<?php

namespace App\Http\Services;

use App\Helpers\CacheHelper;
use App\Models\City;
use App\Models\Country;
use App\Models\Official;
use Illuminate\Database\Eloquent\Model;

class UserLocation
{
    public ?string $country_iso;
    public string $country_name;
    public string $city_name;
    public ?string $area = null;
    public ?float $lat;
    public ?float $lng;
    public float $altitude = 0;
    public string $timezone;
    public string $source;

    public $city;
    public $official;
    public ?Model $country;


    private function setCity($lat, $lng, $search): void
    {
        $this->city = City::query()->iso($this->country_iso)->searchNearest($lat, $lng, $search)->first();
    }

    private function setOfficial(): void
    {
        if ($this->city != null) {
            $this->altitude = $this->city->altitude;
        }
        if ($this->city?->official_id != null) {
            $official = Official::query()->find($this->city->official_id);
            $this->altitude = $official->altitude;
            $this->official = $official;
        }
    }

    public function fromIp(array $data): void
    {
        $coordinates = explode(",", $data['loc']);
        $this->country_iso = $data['country'] ?? '';
        $this->city_name = $data['city'] ?? '';
        $this->lat = $coordinates[0] ?? null;
        $this->lng = $coordinates[1] ?? null;
        $this->timezone = $data['timezone'] ?? '';
        $this->setCity($this->lat, $this->lng, $this->city_name);
        $country = Country::query()->iso($this->country_iso)->first();
        $this->country_name = $country->name ?? '';
        $this->source = 'ip';
        $this->setOfficial();
    }

    public function fromGeoNames($data): void
    {
        $this->country_iso = $data->countryCode ?? '';
        $cityName = null;

        if (!empty($data->alternateNames)) {
            foreach ($data->alternateNames as $name) {
                if ($name->lang == app()->getLocale()) {
                    $cityName = $name->name;
                }
            }
        }
        if ($cityName == null) {
            $cityName = $data->name ?? '';
        }
        $this->city_name = $cityName;
        $this->country_name = $this->country?->name ?? $data->countryName ?? '';
        $this->timezone = $data->timezone?->timeZoneId ?? '';
        $this->setCity($this->lat, $this->lng, $this->city_name);
        $this->source = 'geonames';
        $this->setOfficial();
    }

    public function fromOpenCageData($openCageData): void
    {
        if (!empty($openCageData->results)) {
            $data = $openCageData->results[0];
            $this->country_iso = $data->components->country_code ?? '';
            $this->country_name = $this->country?->name ?? $data->components->country ?? '';
            $this->city_name = $data->components->city ?? $data->components->state ?? '';
            $this->timezone = $data->annotations->timezone->name ?? '';
            $this->setCity($this->lat, $this->lng, $this->city_name);
            $this->setOfficial();
        }
        $this->source = 'opencagedata';
    }

    public function fromCity($nearestCity): void
    {
        $country = $nearestCity->country;
        $this->country_iso = $country->iso ?? '';
        $this->country_name = $country->name ?? '';
        $this->city_name = $nearestCity->name ?? '';
        $this->city = $nearestCity;
        $this->timezone = $this->city->time_zone ?? '';
        $this->source = 'nearestCity';
        $this->setOfficial();
    }

    public function fromPlace($place, $country): void
    {
        $this->country_iso = $country->iso ?? '';
        $this->country_name = $country->name ?? '';
        $this->city_name = $place->name ?? '';
        $this->setCity($this->lat, $this->lng, $this->city_name);
        $this->timezone = $this->city->time_zone ?? '';
        $this->source = 'place';
        if ($this->city != null) {
            $this->altitude = $this->city->altitude;
        }
        if ($place->official_id != null) {
            $official = Official::query()->find($place->official_id);
            $this->altitude = $official->altitude;
            $this->official = $official;
        }
        $this->timezone = $this->official->time_zone ?? '';
    }


    function getAreaName()
    {
        $res = (new ExternalApisService())->geocode($this->lat . ',' . $this->lng, $this->country_iso);
        if (!isset($res->results)) {
            return;
        }
        $this->area = $this->getLocality($res);
    }

    function getLocality($res)
    {
        // Check if the "results" key exists and has at least one element
        if (is_array($res->results) && !empty($res->results)) {
            // Iterate through the results array
            foreach ($res->results as $resultA) {
                $result = (object)$resultA;
                $shouldCheck = true;
                if (isset($result->types)) {
                    $shouldCheck = in_array("locality", $result->types) ||
                        in_array("sublocality", $result->types) ||
                        in_array("neighborhood", $result->types) ||
                        in_array("administrative_area_level_1", $result->types);
                }
                if ($shouldCheck) {
                    if (isset($result->address_components) && is_array($result->address_components)) {
                        foreach ($result->address_components as $addressComponentA) {
                            $addressComponent = (object)$addressComponentA;
                            if (isset($addressComponent->types)
                                && is_array($addressComponent->types)
                                && in_array("sublocality", $addressComponent->types)) {
                                return $addressComponent->long_name;
                            }
                            if (isset($addressComponent->types)
                                && is_array($addressComponent->types)
                                && in_array("neighborhood", $addressComponent->types)) {
                                return $addressComponent->long_name;
                            }
                            if (isset($addressComponent->types)
                                && is_array($addressComponent->types)
                                && in_array("locality", $addressComponent->types)) {
                                return $addressComponent->long_name;
                            }
                            if (isset($addressComponent->types)
                                && is_array($addressComponent->types)
                                && in_array("administrative_area_level_1", $addressComponent->types)) {
                                return $addressComponent->long_name;
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    static function fromDB($location): ?UserLocation
    {
        if ($location == null) {
            return null;
        }
        $userLocation = new UserLocation();
        $userLocation->country_iso = $location->country_iso;
        $userLocation->country_name = $location->country_name;
        $userLocation->city_name = $location->city_name;
        $userLocation->area = $location->area;
        $userLocation->lat = $location->lat;
        $userLocation->lng = $location->lng;
        $userLocation->timezone = $location->timezone;
        $userLocation->source = $location->source;
        $userLocation->city = $location->city;
        $userLocation->official = $location->official;
        return $userLocation;
    }

    static function default(): ?UserLocation
    {
        $UserLocationKey = 'userLocation_25.199_55.269_' . app()->getLocale();
        $userLoc = CacheHelper::get($UserLocationKey);
        if ($userLoc == null) { // if not found in cache
            return self::fromDB(json_decode('{
        "country_iso": "AE",
        "country_name": "الإمارات العربية المتحدة",
        "city_name": "مدينة دبي",
        "lat": 25.199,
        "lng": 55.269,
        "altitude": 0,
        "timezone": "Asia/Dubai",
        "source": "redis_userLocation_25.199_55.269_ar",
        "area": "وسط مدينة دبي",
        "city": null,
        "official": {
            "id": 69,
            "country_name": "الإمارات",
            "city_name": "دبي - دبي"
        }
    }'));
        }
        return $userLoc;
    }
}
