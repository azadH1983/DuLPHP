<?php

namespace App\Http\Services;

use App\Helpers\CacheHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApisService
{
    const GEO_Country = [
        "AE" => "United Arab Emirates"
    ];
//    const GK_API_KEY = "AIzaSyBJxSBZZ9wVw7gl9bbBDXO6dKKQbLsTziE";//BACKUP Personal Key
    const GK_API_KEY = "AIzaSyDitxbO_1t9XjrAQ-n4YngClnz-eE1B8q8";
    const GEOFY_API_KEY = "b465ef81b86e41d5a1a2bb4a6779a306";
    const G_HEADERS = [
        'X-Android-Package' => 'ae.gov.iacad.PrayerTimings',
        'X-Android-Cert' => 'D6DF0C27E76F36604B70AA93A6D71DAEC5F4A8B0',
    ];

    const W_API_KEY = "95139933cfde42ebb22115425242702";

    const GEO_KEY = 'iacad';

    function canUseGApi($lat, $lng, $countryCode): bool
    {
        if (empty($countryCode)) {
            try {
                $countryCode = (new UserLocationService())->countryCode($lat, $lng)->countryCode;
            } catch (\Throwable $e) {
                Log::info("cant find country code for $lat,$lng", [$e->getMessage()]);
                $countryCode = null;
            }
        }
        return (array_key_exists($countryCode, self::GEO_Country));
    }

    function geocode($latlng, $countryCode = null, $source = 'open-map')
    {
        $lat = explode(",", $latlng)[0];
        $lng = explode(",", $latlng)[1];


        if ($lng == 0 || $lat == 0) {
            return array(
                'plus_code' => array(
                    "compound_code" => '',
                    "global_code" => '',
                ),
                'results' => array()
            );
        }
        $lang = App::getLocale();
        $roundLat = round($lat, 3);
        $roundLng = round($lng, 3);
        $geoLocationKey = 'GEOLocation_' . $roundLat . '_' . $roundLng . "_" . $lang;
        $geocode = CacheHelper::get($geoLocationKey);
        if ($geocode != null & is_object($geocode)) {
            $geocode->source = "redis_" . $geoLocationKey;
            if (!empty($geocode->results)) {
                try {
                    if (is_array($geocode->results[0])) {
                        if (!isset($geocode->results[0]['geometry'])) {
                            $geocode->results[0]['geometry'] = [
                                'location' => [
                                    'lat' => floatval($lat),
                                    'lng' => floatval($lng),
                                ]
                            ];
                        }
                    } else {
                        if (!isset($geocode->results[0]->geometry)) {
                            $geocode->results->geometry = [
                                'location' => [
                                    'lat' => floatval($lat),
                                    'lng' => floatval($lng),
                                ]
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("error in REDIS geocode $lat,$lng", [$e->getMessage(), $geocode]);
                }
            }
            return $geocode;
        } elseif (!empty($geocode) && !is_object($geocode)) {
            Log::error("error in redis  geocode $lat,$lng", [$geocode]);
        }
        if ($source == 'google') {
            $geocode = $this->getGoogleGeocode($latlng);
        } else {
            try {
                $geocode = $this->getOpenMapDataGeocode($latlng);
                $geocode->source = "open-map";
            } catch (\Throwable $e) {
                Log::error("error in OpenMapDataGeocode $lat,$lng", [$e->getMessage()]);
                try {
                    $geocode = $this->getGeoapifyGeocode($lat, $lng);
                    $geocode->source = "Geoapify";
                } catch (\Throwable $e) {
                    Log::error("error in Geoapify $lat,$lng", [$e->getMessage()]);
                    $geocode = $this->getGoogleGeocode($latlng);
                }
            }
        }
        if (!empty($geocode->results)) {
            CacheHelper::set($geoLocationKey, $geocode);
        }

        return $geocode;
    }

    function test_all_geo_services($latlng): array
    {
        $lat = explode(",", $latlng)[0];
        $lng = explode(",", $latlng)[1];
        if ($lng == 0 || $lat == 0) {
            return array(
                'plus_code' => array(
                    "compound_code" => '',
                    "global_code" => '',
                ),
                'results' => array()
            );
        }

        $geocodeGoogle = $this->getGoogleGeocode($latlng);
        $geocodeOpenMap = $this->getOpenMapDataGeocode($latlng);
        $geocodeGeoapify = $this->getGeoapifyGeocode($lat, $lng);

        return [
            'google' => $geocodeGoogle,
            'open-map' => $geocodeOpenMap,
            'geoapify' => $geocodeGeoapify,
        ];
    }

    private function getGoogleGeocode($latlng)
    {
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$latlng&key=" . self::GK_API_KEY . "&language=" . App::getLocale();
        $response = Http::withHeaders(self::G_HEADERS)->get($url);
        $userLocation = $response->object();
        $userLocation->source = "google";
        return $userLocation;
    }

    private function getOpenMapDataGeocode($latlng): ?object
    {
        $lat = explode(",", $latlng)[0];
        $lng = explode(",", $latlng)[1];
        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . $lat . '&lon=' . $lng . '&accept-language=' . app()->getLocale();
        $response = Http::timeout(5)->get($url);
        $data = $response->object();
        if (isset($data->address)) {
            $address_components = array();
            if (isset($data->address?->suburb)) {
                $address_components[] = [
                    'long_name' => $data->address->suburb,
                    'short_name' => $data->address->suburb,
                    'types' => array('sublocality', 'political')
                ];
            }
            if (isset($data->address?->town)) {
                $address_components[] = [
                    'long_name' => $data->address->town,
                    'short_name' => $data->address->town,
                    'types' => array('sublocality', 'political')
                ];
            }
            if (isset($data->address?->neighbourhood)) {
                $address_components[] = [
                    'long_name' => $data->address->neighbourhood,
                    'short_name' => $data->address->neighbourhood,
                    'types' => array('neighborhood', 'political')
                ];
            }
            if (isset($data->address?->village)) {
                $address_components[] = [
                    'long_name' => $data->address->village,
                    'short_name' => $data->address->village,
                    'types' => array('neighborhood', 'political')
                ];
            }
            if (isset($data->address->city)) {
                $address_components[] = [
                    'long_name' => $data->address->city,
                    'short_name' => $data->address->city,
                    'types' => array('locality', 'political')
                ];
            }
            if (isset($data->address->county)) {
                $address_components[] = [
                    'long_name' => $data->address->county,
                    'short_name' => $data->address->county,
                    'types' => array('locality', 'political')
                ];
            }
            if (isset($data->address->state)) {
                $address_components[] = [
                    'long_name' => $data->address->state,
                    'short_name' => $data->address->state,
                    'types' => array('administrative_area_level_1', 'political')
                ];
            }
            $data = array(
                'plus_code' => array(
                    "compound_code" => $data->display_name ?? '',
                    "global_code" => $data->place_id . '',
                ),
                'results' => array([
                    'address_components' => $address_components,
                    'types' => array('locality', 'political'),
                    'geometry' => [
                        'location' => [
                            'lat' => floatval($lat),
                            'lng' => floatval($lng),
                        ]
                    ],
                ])
            );
        }
        return (object)$data;
    }

    private function getGeoapifyGeocode($lat, $lng): ?object
    {
        $url = 'https://api.geoapify.com/v1/geocode/reverse?lat=' . $lat . '&lon=' . $lng . '&lang=' . app()->getLocale() . '&format=json&apiKey=' . self::GEOFY_API_KEY;
        $response = Http::timeout(5)->get($url);
        $data = $response->object();

        if (!empty($data->results)) {
            $address_components = array();
            $feature = $data->results[0];

            if (isset($feature->suburb)) {
                $address_components[] = [
                    'long_name' => $feature->suburb,
                    'short_name' => $feature->suburb,
                    'types' => array('sublocality', 'political')
                ];
            }

            if (isset($feature->city)) {
                $address_components[] = [
                    'long_name' => $feature->city,
                    'short_name' => $feature->city,
                    'types' => array('locality', 'political')
                ];
            }
            if (isset($feature->county)) {
                $address_components[] = [
                    'long_name' => $feature->county,
                    'short_name' => $feature->county,
                    'types' => array('locality', 'political')
                ];
            }
            if (isset($feature->state)) {
                $address_components[] = [
                    'long_name' => $feature->state,
                    'short_name' => $feature->state,
                    'types' => array('administrative_area_level_1', 'political')
                ];
            }
            $data = array(
                'plus_code' => array(
                    "compound_code" => $feature->plus_code ?? '',
                    "global_code" => $feature->place_id . '',
                ),
                'results' => array([
                    'address_components' => $address_components,
                    'types' => array('locality', 'political'),
                    'geometry' => [
                        'location' => [
                            'lat' => floatval($lat),
                            'lng' => floatval($lng),
                        ]
                    ],
                ])
            );
        }
        return (object)$data;
    }

    function placeDetails($placeid)
    {
        $lang = App::getLocale();
        $placeKey = 'PlaceDetails_' . $placeid . "_" . $lang;
        $placeDetails = CacheHelper::get($placeKey);
        if ($placeDetails) {
            $placeDetails->source = "redis_" . $placeKey;
            return $placeDetails;
        }
        $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$placeid&key=" . self::GK_API_KEY . "&language=$lang";
        $response = Http::withHeaders(self::G_HEADERS)->get($url);
        if ($response->failed() || $response->object()->status != "OK") {
            Log::error("details failed", [$response->object()]);
            return null;
        }
        $placeDetails = $response->object();
        CacheHelper::set($placeKey, $placeDetails);
        return $placeDetails;
    }

    function elevation($locations)
    {
        $lat = explode(",", $locations)[0];
        $lng = explode(",", $locations)[1];
        $roundLat = round($lat, 3);
        $roundLng = round($lng, 3);
        $ElevationKey = 'Elevation_' . $roundLat . '_' . $roundLng;
        $elevation = CacheHelper::get($ElevationKey);
        try {
            if ($elevation) {
                $elevation->key = "redis_" . $ElevationKey;
                if ($elevation->status == "REQUEST_DENIED") {
                    $elevation = $this->getOpenElevation($locations);
                    $elevation->status = "OK";
                    if (isset($elevation->results)) {
                        CacheHelper::set($ElevationKey, $elevation);
                    }
                }
                return $elevation;
            }
        } catch (\Throwable $e) {
            Log::error("error in redis  elevation $lat,$lng", [$e->getMessage()]);
        }

        try {
            $elevation = $this->getOpenElevation($locations);
            $elevation->status = "OK";
            $elevation->source = "open-elevation";
        } catch (\Throwable $e) {
            Log::error("error in open-elevation $lat,$lng", [$e->getMessage()]);
            try {
                $elevation = $this->getAstergdem($lat, $lng);
            } catch (\Throwable $e) {
                Log::error("error in open-elevation $lat,$lng", [$e->getMessage()]);
                $url = "https://maps.googleapis.com/maps/api/elevation/json?locations=$lat,$lng&key=" . self::GK_API_KEY;
                $response = Http::withHeaders(self::G_HEADERS)->get($url);
                if ($response->successful() && $response->object()->status == "OK") {
                    $elevation = $response->object();
                    $elevation->source = "google";
                }
            }
        }
//        if ($this->canUseGApi($lat, $lng, null)) {
//            $url = "https://maps.googleapis.com/maps/api/elevation/json?locations=$lat,$lng&key=" . self::GK_API_KEY;
//            $response = Http::withHeaders(self::G_HEADERS)->get($url);
//            if ($response->failed() || $response->object()->status != "OK") {
//                $elevation = $this->getOpenElevation($locations);
//                $elevation->status = "OK";
//                $elevation->source = "open-elevation";
//            } else {
//                $elevation = $response->object();
//                $elevation->source = "google";
//            }
//        } else {
//            $elevation = $this->getOpenElevation($locations);
//            $elevation->status = "OK";
//            $elevation->source = "open-elevation";
//        }
        if (isset($elevation->results)) {
            CacheHelper::set($ElevationKey, $elevation);
        }

        return $elevation;
    }

    private function getOpenElevation($locations)
    {
        $url = 'https://api.open-elevation.com/api/v1/lookup?locations=' . $locations;
        $response = Http::timeout(5)->get($url);
        $data = $response->object();
        if ($data) {
            $results = array([
                'elevation' => $data->results[0]->elevation,
                'location' => [
                    'lat' => $data->results[0]->latitude,
                    'lng' => $data->results[0]->longitude,
                ],
            ]);
            $data = array(
                'results' => $results
            );
        }
        return (object)$data;
    }

    private function getAstergdem($lat, $lng)
    {
        $geoNamesClient = new GeoNamesClient(self::GEO_KEY);
        $data = $geoNamesClient->astergdem([
            'lat' => $lat,
            'lng' => $lng,
            'lang' => app()->getLocale()
        ]);

        $results = array([
            'elevation' => $data->astergdem,
            'location' => [
                'lat' => $data->lat,
                'lng' => $data->lng,
            ],
        ]);
        $data = array(
            'results' => $results,
            "status" => "OK",
            "source" => "astergdem",
        );
        return (object)$data;
    }


    function placeAutocomplete($input)
    {
        $lang = App::getLocale();
        $input = strtolower($input);
        $autoCompleteInputKey = 'PlaceAutocomplete_' . $input . "_" . $lang;
        $inputResults = CacheHelper::get($autoCompleteInputKey);
        if ($inputResults) {
//            $inputResults = unserialize($inputResults);
            $inputResults->source = "redis_" . $autoCompleteInputKey;
            return $inputResults;
        }
        $url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=$input&key=" . self::GK_API_KEY . "&language=$lang";
        $response = Http::withHeaders(self::G_HEADERS)->get($url);
        if ($response->failed()) {
            return null;
        }
        $inputResults = $response->object();
        CacheHelper::set($autoCompleteInputKey, $inputResults);
        return $inputResults;

    }

    function weatherV2($latlng)
    {
        $lat = explode(",", $latlng)[0];
        $lng = explode(",", $latlng)[1];
        $roundLat = round($lat, 1);
        $roundLng = round($lng, 1);
        $WeatherKey = 'WeatherV2_' . $roundLat . '_' . $roundLng;
        $weather = CacheHelper::get($WeatherKey);
        if ($weather) {
            if ($weather->current->last_updated_epoch > now()->subHours(2)->timestamp) {
                if (!isset($weather->data->error)) {
                    $response = [
                        'temp_c' => $weather->current->temp_c,
                        'pressure' => $weather->current->pressure_mb,
                        'last_updated_epoch' => $weather->current->last_updated_epoch,
                        'source' => 'redis_' . $WeatherKey,
                    ];
                    return $response;
                }
            }
        }
        $url = 'https://api.weatherapi.com/v1/current.json?key=' . self::W_API_KEY . "&q=$lat,$lng&aqi=yes";
        $response = Http::get($url);
        if ($response->failed()) {
            return null;
        }
        $weather = $response->object();
        CacheHelper::set($WeatherKey, $weather);
        $response = [
            'temp_c' => $weather->current->temp_c,
            'pressure' => $weather->current->pressure_mb,
            'last_updated_epoch' => $weather->current->last_updated_epoch,
        ];
        return $response;
    }

    function weather($latlng)
    {
        $lat = explode(",", $latlng)[0];
        $lng = explode(",", $latlng)[1];
        $roundLat = round($lat, 1);
        $roundLng = round($lng, 1);
        $WeatherKey = 'Weather_' . $roundLat . '_' . $roundLng;
        $weather = CacheHelper::get($WeatherKey);
        if ($weather) {
            if ($weather->data->current_condition[0]->last_updated_epoch > now()->subHours(2)->timestamp) {
                if (!isset($weather->data->error)) {
                    $weather->source = "redis_" . $WeatherKey;
                    return $weather;
                }
            }
        }
        $url = 'https://api.weatherapi.com/v1/current.json?key=' . self::W_API_KEY . "&q=$lat,$lng&aqi=yes";
        $response = Http::get($url);
        if ($response->failed()) {
            return null;
        }
        $data = $response->object();
        $date = $data->current->last_updated;
        $date = Carbon::parse($date);
        $data->current->observation_time = $date->format('h:i A');
        $weather = (object)['data' => (object)[
            'request' => [],
            'weather' => [],
            'ClimateAverages' => [],
            'current_condition' => [
                (object)[
                    "observation_time" => $date->format('h:i A'),
                    "last_updated_epoch" => $data->current->last_updated_epoch,
                    "temp_C" => $data->current->temp_c . "",
                    "temp_F" => $data->current->temp_f . "",
                    "pressure" => $data->current->pressure_mb . "",
                    "weatherCode" => $data->current->condition->code . "",
                    "weatherIconUrl" => [
                        [
                            "value" => $data->current->condition->icon,
                        ]
                    ],
                    "weatherDesc" => [
                        [
                            "value" => $data->current->condition->text,
                        ]
                    ],
                    "windspeedMiles" => $data->current->wind_mph . "",
                    "windspeedKmph" => $data->current->wind_kph . "",
                    "winddirDegree" => $data->current->wind_degree . "",
                    "winddir16Point" => $data->current->wind_dir . "",
                    "precipMM" => $data->current->precip_mm . "",
                    "precipInches" => $data->current->precip_in . "",
                    "humidity" => $data->current->humidity . "",
                    "visibility" => $data->current->vis_km . "",
                    "visibilityMiles" => $data->current->vis_miles . "",
                    "pressureInches" => $data->current->pressure_in . "",
                    "cloudcover" => $data->current->cloud . "",
                    "FeelsLikeC" => $data->current->feelslike_c . "",
                    "FeelsLikeF" => $data->current->feelslike_f . "",
                    "uvIndex" => $data->current->uv . "",
                ]
            ],
        ]];
        CacheHelper::set($WeatherKey, $weather);
        return $weather;
    }


}
