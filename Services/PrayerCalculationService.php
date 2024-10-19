<?php

namespace App\Http\Services;

use App\Helpers\CacheHelper;
use App\Libraries\Exceptions\InputException;
use App\Libraries\Helpers\Consts;
use App\Libraries\Helpers\Inputs\OfficialInput;
use App\Libraries\Helpers\Inputs\PrayerTimesInput;
use App\Libraries\PrayersInput;
use App\Libraries\PrayerTimes;
use App\Models\OfficialPrayer;
use Illuminate\Support\Facades\Redis;

class PrayerCalculationService
{
    /**
     * @throws InputException
     */
    function calculate(PrayerTimesInput $prayerInput): array
    {

        if ($prayerInput instanceof OfficialInput) {
            if ($prayerInput->official->has_static_calc) {
                return $this->calculateStatic($prayerInput->getPrayersInput(), $prayerInput->official->id);
            }
        }
        $prayers = $this->calculateCache($prayerInput);
        if ($prayers != null) {
            return $prayers;
        }

        $PrayerTime = new PrayerTimes($prayerInput->getPrayersInput());
        return $PrayerTime->getResult();
    }

    /**
     * @throws InputException
     */
    function calculateCache(PrayerTimesInput $prayersTimeInput): ?array
    {


        if ($prayersTimeInput->getCacheKey() == "") {
            return null;
        }
        $prayerInput = $prayersTimeInput->getPrayersInput();
        if ($prayerInput->StartDate->format("Y") != $prayerInput->EndDate->format("Y")) {
            return null;

        }

        $cacheKey = $prayersTimeInput->getNewCacheKey();
        $prayers = CacheHelper::getPrayer($cacheKey, $prayersTimeInput->getCacheKey());

        if (empty($prayers)) {
            $ReqStartDate = $prayerInput->StartDate;
            $ReqEndDate = $prayerInput->EndDate;
            $prayerInput->rowInput[Consts::StartDate] = "01/01/" . $prayerInput->StartDate->format("Y");
            $prayerInput->rowInput[Consts::EndDate] = "31/12/" . $prayerInput->StartDate->format("Y");
            $prayerInput->setDates();
            $PrayerTime = new PrayerTimes($prayerInput);
            $prayers = $PrayerTime->getResult()["prayer_times"];
            CacheHelper::set($cacheKey, $prayers);
            $prayerInput->rowInput[Consts::StartDate] = $ReqStartDate->format("d/m/Y");
            $prayerInput->rowInput[Consts::EndDate] = $ReqEndDate->format("d/m/Y");
            $prayerInput->setDates();
        } else {
            $prayers["source"] = $cacheKey;
        }
        return PrayerTimes::getPrayerTimesCache($prayerInput, $prayers);
    }

    function calculateStatic(PrayersInput $prayersInput, $officialId): array
    {
        $prayers = OfficialPrayer::query()
            ->where('official_id', $officialId)
//            ->whereBetween('date', [$prayersInput->StartDate->format("m/d"), $prayersInput->EndDate->format("m/d")])
            ->get();
        return PrayerTimes::getPrayerTimesStatic($prayersInput, $prayers);
    }
}
