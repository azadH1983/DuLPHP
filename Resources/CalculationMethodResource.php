<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $show_angles
 * @property mixed $fajer_angle
 * @property mixed $isha_angle
 * @property mixed $add_faj
 * @property mixed $add_sho
 * @property mixed $add_dho
 * @property mixed $add_asr
 * @property mixed $add_mag
 * @property mixed $add_ish
 * @property mixed $is_time
 * @property mixed $alt_effect
 * @property mixed $aser_madhhab
 */
class CalculationMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = collect();
        $data->put('id', $this->id);
        $data->put('name', $this->name);
        if ($this->show_angles) {
            $data->put('fajer_angle', $this->fajer_angle);
            $data->put('isha_angle', $this->isha_angle);
            $data->put('add_faj', $this->add_faj);
            $data->put('add_sho', $this->add_sho);
            $data->put('add_dho', $this->add_dho);
            $data->put('add_asr', $this->add_asr);
            $data->put('add_mag', $this->add_mag);
            $data->put('add_ish', $this->add_ish);
            $data->put('is_time', $this->is_time);
            $data->put('alt_effect', $this->alt_effect);
            $data->put('aser_madhhab', $this->aser_madhhab);
        }
        return $data->toArray();
    }
}
