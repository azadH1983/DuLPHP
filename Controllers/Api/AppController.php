<?php

namespace App\Http\Controllers\Api;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Kernel;
use App\Http\Services\PrayerCalculationService;
use App\Http\Services\UserLocation;
use App\Libraries\Helpers\Consts;
use App\Libraries\Helpers\Inputs\OfficialInput;
use App\Models\AppConfig;
use App\Models\AppUpdate;
use App\Models\Country;
use App\Traits\AppResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppController extends Controller
{
    use AppResponse;

    public function getAppVersion(): JsonResponse
    {
        $appUpdate = Cache::remember('appUpdate', Kernel::CACHE_TIME, function () {
            return AppUpdate::all();
        });
        return $this->success($appUpdate);
    }

    public function getAppConfig(): JsonResponse
    {
        $appConfig = Cache::remember('appConfig', Kernel::CACHE_TIME, function () {
            return AppConfig::first();
        });
        return $this->success($appConfig);
    }

    public function setAppVersion(Request $request): JsonResponse
    {
        $android = $request->input('android');
        $ios = $request->input('ios');
        $androidModel = AppUpdate::where('platform', 'android')->first();
        $iosModel = AppUpdate::where('platform', 'ios')->first();
        if ($androidModel) {
            $androidModel->version = $android['version'];
            $androidModel->force_update = $android['force_update'];
            $androidModel->save();
        } else {
            AppUpdate::create([
                'version' => $android['version'],
                'force_update' => $android['force_update'],
                'platform' => 'android',
            ]);
        }
        if ($iosModel) {
            $iosModel->version = $ios['version'];
            $iosModel->force_update = $ios['force_update'];
            $iosModel->save();
        } else {
            AppUpdate::create([
                'version' => $ios['version'],
                'force_update' => $ios['force_update'],
                'platform' => 'ios',
            ]);
        }
        return $this->success(AppUpdate::all());
    }

    public function mapHighlat()
    {
        $json = File::get(storage_path("AltTimes.json"));

        $countries = collect(json_decode($json));
        $id = 1;
        foreach ($countries as $country) {
            if ($country->country != null) {
                $countryModel = Country::where('iso', $country->country)->first();
                $country->country_id = $countryModel->id;
            }
            $country->id = $id;
            $id++;
        }

        return $this->success($countries);
    }

    public function downloadLogs($name)
    {
        $file = storage_path('logs/' . $name . '.log');
        return response()->download($file);
    }

    public function clearRides($key)
    {
        CacheHelper::del($key);
//        Cache::clear();
        return $this->success('success');
    }

    public function clearCache()
    {
        Cache::clear();
        return $this->success('success');
    }

    public function clearLogs()
    {
        $files = Arr::where(Storage::disk('log')->files(), function ($filename) {
            return Str::endsWith($filename, '.log');
        });
        $count = count($files);
        $res = collect();
        if (Storage::disk('log')->delete($files)) {
            $res->push(sprintf('Deleted %s %s!', $count, Str::plural('file', $count)));
        } else {
            $res->push('Error in deleting log files!');
        }
        return $this->success($res);
    }

    public function testOfficial($minset = 1)
    {

        Redis::flushDB();
        Cache::clear();
        if ($minset == 0) {
            $file = storage_path('/official_1pt_s.json');
        } else {
            $file = storage_path('/official_1pt.json');
        }

        $json = File::get($file);
        $data = json_decode($json);
        $input = new OfficialInput([Consts::StartDate => "01/01/2024",
            Consts::EndDate => "31/12/2024",
            Consts::minset => $minset,
            "official_id" => "1"]);
        $calculationService = new PrayerCalculationService();
        $prayers = $calculationService->calculate($input)["prayer_times"]->toArray();
        $output = collect();
        for ($i = 0; $i < count($prayers); $i++) {
            $ipt = (object)$prayers[$i];

            $salat = $data[$i];
            if ($salat->Fajer != $ipt->fajer) {
                $output->push(["fajer", $ipt->date, $salat->Fajer, $ipt->fajer]);
            }
            if ($salat->Shuroq != $ipt->shuroq) {
                $output->push(["shuroq", $ipt->date, $salat->Shuroq, $ipt->shuroq]);
            }
            if ($salat->Dhohur != $ipt->dhohur) {
                $output->push(["dhohur", $ipt->date, $salat->Dhohur, $ipt->dhohur]);
            }
            if ($salat->Aser != $ipt->aser) {
                $output->push(["aser", $ipt->date, $salat->Aser, $ipt->aser]);
            }
            if ($salat->Maghreb != $ipt->maghreb) {
                $output->push(["maghreb", $ipt->date, $salat->Maghreb, $ipt->maghreb]);
            }
            if ($salat->Isha != $ipt->isha) {
                $output->push(["isha", $ipt->date, $salat->Isha, $ipt->isha]);
            }
        }
        if ($output->count() > 0) {
            dd($output);
        }
        return $this->success(true);
    }

    public function getRides($key)
    {
        return $this->success(CacheHelper::get($key));
    }
}
