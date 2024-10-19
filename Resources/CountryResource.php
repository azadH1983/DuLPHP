<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $iso
 * @property mixed $calc_methoud_id
 */
class CountryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
//            'flag' => 'https://countryflagsapi.com/png/' . $this->iso,
            'flag' => 'https://flagsapi.com/' . $this->iso . '/flat/64.png',
            'calculation_method_id' => $this->calculation_method_id,
            'iso' => $this->iso,
            'iso3' => $this->iso3,

        ];
    }
}
