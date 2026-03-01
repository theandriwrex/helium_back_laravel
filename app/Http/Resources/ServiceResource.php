<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,

            'category' => $this->category->name,

            'freelancer' => [
                'id' => $this->freelancerProfile->user->id,
                'name' => $this->freelancerProfile->user->names . ' ' . $this->freelancerProfile->user->last_names,
                'photo' => $this->freelancerProfile->user->photo,
                'profession' => $this->freelancerProfile->profession,
            ]
        ];
    }
}
