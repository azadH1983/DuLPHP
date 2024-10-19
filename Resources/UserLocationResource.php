<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'country_iso' => $this->country_iso,
            'country_name' => $this->country_name,
            'city_name' => $this->city_name,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'altitude' => $this->altitude,
            'timezone' => $this->timezone,
            'source' => $this->source,
            'area' => $this->area,
            'city' => $this->city,
            'official' => $this->official,
//            'country' => $this->country,
        ];
    }
}
