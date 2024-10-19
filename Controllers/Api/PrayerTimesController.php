<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Http\Requests\OfficialRequest;
use App\Http\Requests\PrayerCustomCalculationRequest;
use App\Http\Services\PrayerCalculationService;
use App\Http\Services\UserLocationService;
use App\Libraries\Exceptions\HighLatitudeException;
use App\Libraries\Exceptions\InputException;
use App\Libraries\Helpers\Consts;
use App\Libraries\Helpers\Inputs\CityInput;
use App\Libraries\Helpers\Inputs\CustomInput;
use App\Libraries\Helpers\Inputs\OfficialInput;
use App\Traits\AppResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PrayerTimesController extends Controller
{
    use AppResponse;

    public function __construct(
        protected PrayerCalculationService $calculationService,
    )
    {
    }

    public function official(OfficialRequest $request): JsonResponse
    {
        try {
            $input = new OfficialInput($request->all());
            $prayers = $this->calculationService->calculate($input);
            return $this->success($prayers, __('Calculations_Successful'));
        } catch (InputException $e) {
            Log::error("OfficialRequest: " . $e->getMessage(), $request->all());
            return $this->badRequest($e->getMessage());
        } catch (HighLatitudeException $e) {
            Log::error("official: " . $e->getMessage(), $request->all());
            return $this->badRequest($e->getMessage());
        }
    }

    public function city(CityRequest $request): JsonResponse
    {
        try {
            $input = new CityInput($request->all());
            $prayers = $this->calculationService->calculate($input);
            return $this->success($prayers, __('Calculations_Successful'));
        } catch (InputException $e) {
            Log::error("CityRequest: " . $e->getMessage(), $request->all());
            return $this->badRequest($e->getMessage());
        } catch (HighLatitudeException $e) {
            Log::error("city: " . $e->getMessage(), $request->all());
            return $this->badRequest($e->getMessage());
        }
    }

    public function customCalculation(PrayerCustomCalculationRequest $request): JsonResponse
    {
        try {
            $inputRaw = $request->all();
            if (!$inputRaw[Consts::Country] || !$inputRaw[Consts::City]) { //if country or city is empty
                if ($inputRaw[Consts::Latitude] && $inputRaw[Consts::Longitude]) {
                    $usrLocation = (new UserLocationService())->userLocation($inputRaw[Consts::Latitude], $inputRaw[Consts::Longitude], false);
                    $inputRaw[Consts::Country] = $usrLocation->country_name;
                    $inputRaw[Consts::City] = $usrLocation->city_name;
                } else {
                    return $this->badRequest(__('Country_or_City_is_required'));
                }
            }
            $input = new CustomInput($inputRaw);
            $prayers = $this->calculationService->calculate($input);
            return $this->success($prayers, __('Calculations_Successful'));
        } catch (InputException $e) {
            Log::error("CustomCalculation: " . $e->getMessage(), $request->all());
            return $this->badRequest($e->getMessage());
        } catch (HighLatitudeException $e) {
            Log::error("Custom: " . $e->getMessage(), $request->all());
            return $this->badRequest($e->getMessage());
        }
    }
}
